<?php

use App\Console\Commands\BackupDatabase;
use App\Enums\AssignedBy;
use App\Enums\BlockReason;
use App\Enums\MailType;
use App\Http\Controllers\HealthController;
use App\Jobs\RecordQueueHeartbeat;
use App\Mail\AdminAlertMail;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\Order;
use App\Models\Seat;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\AutoAssign;
use App\Services\BuyerMailer;
use App\Services\Hievents\OrderSync;
use App\Support\DataRetention;
use App\Support\StatusLog;
use Database\Seeders\SeatSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Mail::fake();
    Cache::flush();
    RateLimiter::clear('public');
    $this->withoutVite();
    $this->seed(SeatSeeder::class);
    $this->travelTo('2027-01-15 12:00:00');

    config([
        'seating.event_id' => '1',
        'seating.seated_product_ids' => ['10'],
        'seating.event_date' => '2027-02-01 20:00',
        'seating.purge_after_days' => 30,
        'seating.admin_emails' => ['admin@example.ch'],
        'seating.hievents.webhook_secret' => 'secret',
        'seating.hievents.base_url' => 'https://hi.test',
        'seating.hievents.api_key' => 'key',
        'seating.backup_keep_days' => 14,
    ]);
});

function personalOrder(): Order
{
    $order = Order::factory()->create([
        'first_name' => 'Rita', 'last_name' => 'Muster', 'email' => 'rita@example.ch',
        'needs_wheelchair' => true, 'wheelchair_notified_at' => now(),
    ]);
    $attendee = Attendee::factory()->for($order)->create(['first_name' => 'Hans', 'last_name' => 'Gast']);
    Seat::find('P-1-1')->update(['attendee_id' => $attendee->id, 'assigned_by' => AssignedBy::Buyer]);
    MailLog::create(['order_id' => $order->id, 'type' => MailType::Link, 'sent_at' => now()]);

    return $order;
}

function signedWebhook(array $data)
{
    $body = json_encode($data);

    return test()->call('POST', '/webhooks/hievents', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_SIGNATURE' => hash_hmac('sha256', $body, 'secret'),
    ], $body);
}

describe('rate limits', function () {
    it('limits the public plan', function (string $path) {
        foreach (range(1, 120) as $request) {
            $this->get($path)->assertOk();
        }

        $this->get($path)->assertTooManyRequests();
    })->with(['/api/plan', '/plan']);

    it('limits the webhook', function () {
        foreach (range(1, 600) as $request) {
            $this->postJson('/webhooks/hievents', [])->assertUnauthorized();
        }

        $this->postJson('/webhooks/hievents', [])->assertTooManyRequests();
    });

    it('limits per visitor', function () {
        foreach (range(1, 120) as $request) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->get('/api/plan')->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get('/api/plan')->assertOk();
    });
});

describe('health', function () {
    function beat(int $schedulerAge = 10, int $queueAge = 10): void
    {
        Cache::forever(HealthController::SCHEDULER_KEY, now()->getTimestamp() - $schedulerAge);
        Cache::forever(HealthController::QUEUE_KEY, now()->getTimestamp() - $queueAge);
    }

    it('is ok when everything is alive', function () {
        beat();

        $this->getJson('/health')->assertOk()->assertExactJson([
            'status' => 'ok',
            'checks' => ['database' => 'ok', 'queue' => 'ok', 'scheduler' => 'ok'],
        ]);
    });

    it('fails without a scheduler or queue heartbeat', function (int $schedulerAge, int $queueAge, string $broken) {
        beat($schedulerAge, $queueAge);

        $this->getJson('/health')->assertServiceUnavailable()->assertJsonPath('status', 'failed')->assertJsonPath("checks.{$broken}", 'failed');
    })->with([[181, 10, 'scheduler'], [10, 601, 'queue']]);

    it('fails before the first heartbeat', function () {
        $this->getJson('/health')->assertServiceUnavailable();
    });

    it('fails, and does not crash, when the database is gone', function () {
        beat();
        DB::shouldReceive('select')->andThrow(new RuntimeException('down'));

        $this->getJson('/health')->assertServiceUnavailable()->assertJsonPath('checks.database', 'failed');
    });

    it('leaves no session behind', function () {
        beat();
        config(['session.driver' => 'database']);

        $this->get('/health');

        expect(DB::table('sessions')->count())->toBe(0);
    });

    it('records both heartbeats through the scheduler and the queue', function () {
        Queue::fake();

        $this->artisan('seating:heartbeat')->assertSuccessful();

        Queue::assertPushed(RecordQueueHeartbeat::class);
        expect(Cache::get(HealthController::SCHEDULER_KEY))->toBe(now()->getTimestamp());

        (new RecordQueueHeartbeat)->handle();
        expect(Cache::get(HealthController::QUEUE_KEY))->toBe(now()->getTimestamp());
    });
});

describe('purge', function () {
    beforeEach(function () {
        $this->order = personalOrder();
        $this->oldToken = $this->order->issueToken();
    });

    it('leaves everything alone before the date', function () {
        $this->travelTo('2027-03-03 19:59:00');

        $this->artisan('seating:purge')->expectsOutputToContain('Not due yet')->assertSuccessful();

        expect($this->order->refresh()->email)->toBe('rita@example.ch');
    });

    it('stays idle without an event date', function () {
        config(['seating.event_date' => null]);
        $this->travelTo('2030-01-01 12:00:00');

        $this->artisan('seating:purge')->assertSuccessful();

        expect($this->order->refresh()->email)->toBe('rita@example.ch');
    });

    it('warns once a week ahead, in case the event date is wrong', function () {
        $this->travelTo('2027-02-20 12:00:00');
        $this->artisan('seating:purge');
        Mail::assertNotSent(AdminAlertMail::class);

        $this->travelTo('2027-02-25 12:00:00');
        $this->artisan('seating:purge');
        $this->artisan('seating:purge');
        Mail::assertSent(AdminAlertMail::class, 1);
    });

    it('removes everything that points to a person and keeps the counts', function () {
        $seat = Seat::find('P-1-1');
        $attendeeId = $seat->attendee_id;
        WebhookEvent::create(['event_type' => 'order.created', 'payload_hash' => str_repeat('a', 64), 'payload' => ['email' => 'rita@example.ch'], 'received_at' => now()]);
        DB::table('failed_jobs')->insert(['uuid' => 'x', 'connection' => 'db', 'queue' => 'default', 'payload' => 'rita@example.ch', 'exception' => 'x', 'failed_at' => now()]);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => 'rita@example.ch', 'attempts' => 0, 'available_at' => 0, 'created_at' => 0]);
        DB::table('sessions')->insert(['id' => 'guest', 'ip_address' => '10.0.0.1', 'payload' => 'x', 'last_activity' => 0]);
        DB::table('sessions')->insert(['id' => 'admin', 'user_id' => User::factory()->create()->id, 'payload' => 'x', 'last_activity' => 0]);

        $this->travelTo('2027-03-03 20:01:00');
        $this->artisan('seating:purge')->expectsOutputToContain('Removed: 1 orders, 1 attendees')->assertSuccessful();

        $order = $this->order->refresh();

        expect($order->only(['email', 'first_name', 'last_name', 'short_id', 'hievents_public_id']))->each->toBeNull()
            ->and($order->needs_wheelchair)->toBeFalse()
            ->and($order->wheelchair_notified_at)->toBeNull()
            ->and($order->hievents_order_id)->toBe("purged-{$order->id}")
            ->and(Attendee::find($attendeeId)->only(['first_name', 'last_name']))->each->toBeNull()
            ->and(Attendee::find($attendeeId)->hievents_attendee_id)->toBe("purged-{$attendeeId}")
            ->and(WebhookEvent::count())->toBe(0)
            ->and(DB::table('jobs')->count())->toBe(0)
            ->and(DB::table('failed_jobs')->count())->toBe(0)
            ->and(DB::table('sessions')->pluck('id')->all())->toBe(['admin'])
            ->and(Order::count())->toBe(1)
            ->and(Attendee::count())->toBe(1)
            ->and($seat->refresh()->attendee_id)->toBe($attendeeId)
            ->and(MailLog::count())->toBe(1);

        $dump = json_encode([DB::table('orders')->get(), DB::table('attendees')->get(), DB::table('audit_log')->get(), DB::table('mail_log')->get()]);

        expect($dump)->not->toContain('rita')->not->toContain('Rita')->not->toContain('Muster')->not->toContain('Hans');
    });

    it('kills the personal link', function () {
        $this->get("/s/{$this->oldToken}")->assertOk();

        $this->travelTo('2027-03-04 12:00:00');
        $this->artisan('seating:purge');

        $this->get("/s/{$this->oldToken}")->assertNotFound();
    });

    it('tells the admins once, and does nothing the next day', function () {
        $this->travelTo('2027-03-04 12:00:00');

        $this->artisan('seating:purge');
        $this->artisan('seating:purge')->expectsOutputToContain('Nothing to remove');

        Mail::assertSent(AdminAlertMail::class, 1);
    });

    it('previews without changing anything', function () {
        $this->travelTo('2027-03-04 12:00:00');

        $this->artisan('seating:purge', ['--dry-run' => true])->expectsOutputToContain('Would remove: 1 orders')->assertSuccessful();

        expect($this->order->refresh()->email)->toBe('rita@example.ch');
    });

    it('lets no webhook bring personal data back', function () {
        $this->travelTo('2027-03-04 12:00:00');
        $this->artisan('seating:purge');

        signedWebhook(['event_type' => 'order.updated', 'payload' => ['id' => 1, 'email' => 'rita@example.ch']])->assertOk();

        expect(WebhookEvent::count())->toBe(0);
    });

    it('still rejects unsigned webhooks after the date', function () {
        $this->travelTo('2027-03-04 12:00:00');

        $this->postJson('/webhooks/hievents', ['event_type' => 'x'])->assertUnauthorized();
    });

    it('lets no reconcile bring personal data back', function () {
        $this->travelTo('2027-03-04 12:00:00');
        $this->artisan('seating:purge');
        Http::fake();

        $this->artisan('seating:reconcile')->assertSuccessful();

        Http::assertNothingSent();
    });

    it('knows the date', function () {
        expect(app(DataRetention::class)->purgeDate()->format('Y-m-d H:i'))->toBe('2027-03-03 20:00');
    });
});

describe('backup', function () {
    beforeEach(function () {
        $this->folder = storage_path('framework/testing/backups-'.uniqid());
        config(['seating.backup_path' => $this->folder]);
    });

    afterEach(function () {
        File::deleteDirectory($this->folder);
    });

    it('writes a real dump and remembers when', function () {
        // A real file gets today's real date, which must not look expired.
        $this->travelBack();

        $this->artisan('seating:backup')->assertSuccessful();

        $files = File::glob("{$this->folder}/seating-*.dump");

        expect($files)->toHaveCount(1)
            ->and(substr(File::get($files[0]), 0, 5))->toBe('PGDMP')
            ->and(File::glob("{$this->folder}/*.partial"))->toBe([])
            ->and(StatusLog::get(BackupDatabase::LAST_BACKUP_KEY))->not->toBeNull();
    });

    it('removes dumps past their time and keeps the rest', function () {
        File::ensureDirectoryExists($this->folder);
        File::put("{$this->folder}/seating-old.dump", 'x');
        File::put("{$this->folder}/seating-recent.dump", 'x');
        File::put("{$this->folder}/notes.txt", 'x');
        touch("{$this->folder}/seating-old.dump", now()->subDays(15)->getTimestamp());
        touch("{$this->folder}/seating-recent.dump", now()->subDays(13)->getTimestamp());
        touch("{$this->folder}/notes.txt", now()->subDays(99)->getTimestamp());

        $this->artisan('seating:backup')->assertSuccessful();

        expect(File::exists("{$this->folder}/seating-old.dump"))->toBeFalse()
            ->and(File::exists("{$this->folder}/seating-recent.dump"))->toBeTrue()
            ->and(File::exists("{$this->folder}/notes.txt"))->toBeTrue();
    });

    it('alerts when the dump fails, and never leaves a broken file or removes old ones', function () {
        Process::fake(['*' => Process::result(errorOutput: 'connection refused', exitCode: 1)]);
        File::ensureDirectoryExists($this->folder);
        File::put("{$this->folder}/seating-old.dump", 'x');
        touch("{$this->folder}/seating-old.dump", now()->subDays(99)->getTimestamp());

        $this->artisan('seating:backup')->assertFailed();

        Mail::assertSent(AdminAlertMail::class, fn (AdminAlertMail $mail) => str_contains($mail->render(), 'Backup fehlgeschlagen'));
        expect(File::exists("{$this->folder}/seating-old.dump"))->toBeTrue()
            ->and(StatusLog::get(BackupDatabase::LAST_BACKUP_KEY))->toBeNull();
    });

    it('keeps the password off the command line', function () {
        Process::fake(['*' => Process::result(exitCode: 1)]);
        config(['database.connections.pgsql.password' => 'geheim']);

        $this->artisan('seating:backup');

        Process::assertRan(fn ($process) => ! str_contains(json_encode($process->command), 'geheim') && $process->environment['PGPASSWORD'] === 'geheim');
    });
});

it('runs everything on a schedule', function () {
    $commands = collect(app(Schedule::class)->events())->map->command->implode(' ');

    expect($commands)->toContain('seating:heartbeat', 'seating:backup', 'seating:purge');
});

it('keeps the audit trail through a late sale before the purge', function () {
    config(['seating.selection_opens_at' => '2026-12-01 10:00', 'seating.selection_deadline' => '2027-01-10 23:59']);

    app(OrderSync::class)->syncOrder([
        'id' => 77, 'event_id' => 1, 'short_id' => 'o_late', 'public_id' => 'O', 'status' => 'COMPLETED', 'first_name' => 'L', 'last_name' => 'B',
        'email' => 'l@example.ch', 'created_at' => '2027-01-15T10:00:00.000000Z', 'question_answers' => [],
        'attendees' => [['id' => 78, 'order_id' => 77, 'event_id' => 1, 'product_id' => 10, 'status' => 'ACTIVE', 'first_name' => 'L', 'last_name' => 'B']],
    ]);

    expect(AuditLog::where('action', 'seat_auto_assigned')->count())->toBe(1);
});

describe('redis', function () {
    beforeEach(function () {
        config(['queue.default' => 'redis']);
        Redis::connection()->flushdb();
    });

    afterEach(function () {
        Redis::connection()->flushdb();
    });

    it('releases a job only when its transaction commits', function () {
        $queue = Queue::connection('redis');

        DB::transaction(function () use ($queue): void {
            RecordQueueHeartbeat::dispatch();

            expect($queue->size())->toBe(0);
        });

        expect($queue->size())->toBe(1);
    });

    it('sends no mail for a rolled-back change', function () {
        // The fake records mails at the call, so only the real mailer shows what a rollback does.
        Mail::swap($this->app->make('mail.manager'));
        $order = personalOrder();
        $queue = Queue::connection('redis');

        try {
            DB::transaction(function () use ($order): void {
                app(BuyerMailer::class)->send($order, MailType::Confirmation);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        expect($queue->size())->toBe(0)
            ->and(MailLog::where('type', MailType::Confirmation)->count())->toBe(0);
    });

    it('clears queued jobs and job history in the purge', function () {
        personalOrder();
        Redis::connection()->set('horizon-job-history', 'rita@example.ch');
        RecordQueueHeartbeat::dispatch();

        $this->travelTo('2027-03-04 12:00:00');
        $this->artisan('seating:purge')->assertSuccessful();

        expect(Redis::connection()->dbsize())->toBe(0);
    });
});

it('removes nothing and alerts if Redis is unreachable during the purge', function () {
    $order = personalOrder();
    config(['database.redis.default.host' => '127.0.0.1', 'database.redis.default.port' => 1, 'database.redis.default.max_retries' => 0]);
    Redis::purge();

    $this->travelTo('2027-03-04 12:00:00');
    $this->artisan('seating:purge')->assertFailed();

    expect($order->refresh()->email)->toBe('rita@example.ch');
    Mail::assertSent(AdminAlertMail::class, fn (AdminAlertMail $mail) => str_contains($mail->render(), 'Anonymisierung fehlgeschlagen'));
});

describe('horizon', function () {
    it('is closed to guests', function () {
        $this->get('/horizon')->assertForbidden();
        $this->getJson('/horizon/api/stats')->assertForbidden();
        $this->getJson('/horizon/api/jobs/recent')->assertForbidden();
    });

    it('stays closed to guests on a server left in local mode', function () {
        $this->app['env'] = 'local';

        $this->get('/horizon')->assertForbidden();
    });

    it('is open to the admin', function () {
        $this->actingAs(User::factory()->create())->get('/horizon')->assertOk();
    });
});

it('keeps run status when the cache is lost', function () {
    config(['seating.selection_deadline' => '2027-01-10 23:59']);
    $order = Order::factory()->create();
    Attendee::factory()->count(2)->for($order)->create();
    MailLog::create(['order_id' => $order->id, 'type' => MailType::Link, 'sent_at' => now()]);
    Seat::whereNull('block_reason')->update(['block_reason' => BlockReason::Other]);

    app(AutoAssign::class)->run();
    Cache::flush();
    Mail::fake();

    $this->artisan('seating:check-auto-assign')->assertSuccessful();

    Mail::assertNotSent(AdminAlertMail::class);
});

it('promises the time the data really stays', function () {
    $days = config('seating.purge_after_days') + config('seating.backup_keep_days');
    $order = Order::factory()->create();
    Attendee::factory()->for($order)->create();

    expect($days)->toBeLessThanOrEqual(45);
    $this->get('/s/'.$order->issueToken())->assertSeeText('spätestens 45 Tage nach der Veranstaltung');
});
