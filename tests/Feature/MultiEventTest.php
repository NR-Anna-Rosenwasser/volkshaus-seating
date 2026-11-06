<?php

use App\Enums\BlockReason;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\EventInfodesk;
use App\Filament\Resources\Events\Pages\EventSaalplan;
use App\Filament\Resources\Events\Pages\ManageEventOrders;
use App\Filament\Widgets\SeatOverview;
use App\Filament\Widgets\UpcomingEvents;
use App\Mail\AdminAlertMail;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminSeats;
use App\Services\AutoAssign;
use App\Services\BestSeats;
use App\Services\Hievents\OrderSync;
use App\Services\SeatSelection;
use App\Services\SeatStats;
use App\Support\StatusLog;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->withoutVite();
    config(['seating.admin_emails' => ['admin@example.ch'], 'seating.hievents.base_url' => 'https://hi.test']);

    $this->first = seatedEvent();
    $this->second = otherEvent(['slug' => 'zweites']);
});

function orderIn(Event $event, int $tickets = 1, array $attributes = []): Order
{
    $order = Order::factory()->create(['event_id' => $event->id, ...$attributes]);
    Attendee::factory()->count($tickets)->for($order)->create();

    return $order;
}

function stateIn(Event $event, string $code): string
{
    return test()->getJson("/api/e/{$event->slug}/plan")->json("seats.{$code}");
}

it('gives every event its own seats under the same codes', function () {
    app(SeatSelection::class)->save(orderIn($this->first), ['P-5-1']);
    Cache::flush();

    expect(stateIn($this->first, 'P-5-1'))->toBe('taken')
        ->and(stateIn($this->second, 'P-5-1'))->toBe('free');
});

it('keeps the cached plans of two events apart', function () {
    $this->first->seats()->where('code', 'P-5-1')->update(['block_reason' => BlockReason::Press]);

    expect(stateIn($this->first, 'P-5-1'))->toBe('blocked')
        ->and(stateIn($this->second, 'P-5-1'))->toBe('free');
});

it('lets the same Hi.Events order id exist in two events', function () {
    orderIn($this->first, attributes: ['hievents_order_id' => '500']);

    expect(orderIn($this->second, attributes: ['hievents_order_id' => '500'])->exists)->toBeTrue();
});

it('looks for gaps within the buyer\'s own event only', function () {
    $this->second->seats()->where('code', 'P-5-2')->update(['block_reason' => BlockReason::Press]);

    expect(fn () => app(SeatSelection::class)->save(orderIn($this->first, 2), ['P-5-1', 'P-5-3']))
        ->toThrow(ValidationException::class);
});

it('suggests seats from the event alone', function () {
    $this->second->seats()->update(['block_reason' => BlockReason::Press]);

    expect(app(BestSeats::class)->find($this->first, 1))->toHaveCount(1)
        ->and(app(BestSeats::class)->find($this->second, 1))->toBe([]);
});

it('counts seats and orders per event', function () {
    orderIn($this->first, 3);

    expect(app(SeatStats::class)->snapshot($this->first)['sold'])->toBe(3)
        ->and(app(SeatStats::class)->snapshot($this->second)['sold'])->toBe(0);
});

it('only moves seats of the event it was asked about', function () {
    $order = orderIn($this->first);
    app(AdminSeats::class)->assign($order, ['P-1-1']);

    expect(fn () => app(AdminSeats::class)->move($this->second, 'P-1-1', 'P-1-2'))
        ->toThrow(ValidationException::class);
});

it('tracks each event\'s auto-assign on its own', function () {
    setEvent(['selection_deadline' => now()->subHours(4)]);
    $this->second->update(['selection_deadline' => now()->subHours(4)]);
    orderIn($this->first);
    orderIn($this->second);

    app(AutoAssign::class)->run($this->first);
    $this->artisan('seating:check-auto-assign')->assertSuccessful();

    Mail::assertSent(AdminAlertMail::class, 1);
    Mail::assertSent(AdminAlertMail::class, fn (AdminAlertMail $mail) => str_contains($mail->title, $this->second->name));
    expect(StatusLog::get(AutoAssign::lastRunKey($this->second)))->toBeNull();
});

it('purges only the event that is due', function () {
    $this->first->update(['starts_at' => now()->subDays(40)]);
    $old = orderIn($this->first, attributes: ['email' => 'alt@example.ch']);
    $current = orderIn($this->second, attributes: ['email' => 'neu@example.ch']);

    $this->artisan('seating:purge')->assertSuccessful();

    expect($old->refresh()->email)->toBeNull()
        ->and($current->refresh()->email)->toBe('neu@example.ch')
        ->and($this->first->refresh()->purged_at)->not->toBeNull()
        ->and($this->second->refresh()->purged_at)->toBeNull();
});

it('stops syncing a purged event but not the others', function () {
    $this->first->update(['starts_at' => now()->subDays(40)]);
    Http::fake(['hi.test/*' => Http::response(['data' => [], 'meta' => ['last_page' => 1]])]);

    $this->artisan('seating:reconcile')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), "/events/{$this->second->hievents_event_id}/orders"));
});

it('carries on with the next event when one fails', function () {
    Http::fake([
        "hi.test/api/events/{$this->first->hievents_event_id}/*" => Http::response('nope', 500),
        "hi.test/api/events/{$this->second->hievents_event_id}/*" => Http::response(['data' => [], 'meta' => ['last_page' => 1]]),
    ]);

    $this->artisan('seating:reconcile')->assertFailed();

    Http::assertSent(fn ($request) => str_contains($request->url(), "/events/{$this->second->hievents_event_id}/orders"));
    Mail::assertSent(AdminAlertMail::class, fn (AdminAlertMail $mail) => str_contains($mail->title, $this->first->name));
});

it('ignores orders of another event when syncing', function () {
    $payload = ['id' => 1, 'event_id' => (int) $this->second->hievents_event_id, 'status' => 'COMPLETED'];

    app(OrderSync::class)->syncOrder($this->first, $payload);

    expect(Order::count())->toBe(0);
});

it('refuses to skip the schedule for all events at once', function (string $command) {
    $this->artisan($command, ['--now' => true])->expectsOutputToContain('--now needs --event')->assertFailed();
})->with(['seating:send-final', 'seating:send-reminders']);

describe('home page', function () {
    it('lists upcoming events', function () {
        $this->get('/')->assertOk()->assertSeeText($this->first->name)->assertSeeText($this->second->name);
    });

    it('goes straight to the plan when there is only one', function () {
        $this->second->update(['purged_at' => now()]);

        $this->get('/')->assertRedirect('/e/test');
    });

    it('leaves out past events', function () {
        $this->second->update(['starts_at' => now()->subDay()]);
        $this->first->update(['starts_at' => now()->subDay()]);

        $this->get('/')->assertOk()->assertSeeText('keine Veranstaltungen');
    });

    it('shows nothing for an unknown event', function () {
        $this->get('/e/gibt-es-nicht')->assertNotFound();
    });
});

describe('admin', function () {
    beforeEach(function () {
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('admin');
    });

    it('lists only the orders of the current event', function () {
        $own = orderIn($this->first, attributes: ['short_id' => 'o_eigen']);
        $foreign = orderIn($this->second, attributes: ['short_id' => 'o_fremd']);

        Livewire::test(ManageEventOrders::class, ['record' => 'test'])->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);

        $this->get('/admin/events/test/bestellungen')->assertOk()->assertSeeText('o_eigen')->assertDontSeeText('o_fremd');
        $this->get('/admin/events/zweites/bestellungen')->assertOk()->assertSeeText('o_fremd')->assertDontSeeText('o_eigen');
    });

    it('keeps the event through a Livewire round trip', function () {
        Livewire::test(EventSaalplan::class, ['record' => 'zweites'])
            ->call('blockSeats', ['P-1-1'], BlockReason::Press->value)
            ->assertHasNoErrors();

        expect($this->first->seats()->where('code', 'P-1-1')->first()->block_reason)->toBeNull()
            ->and($this->second->seats()->where('code', 'P-1-1')->first()->block_reason)->toBe(BlockReason::Press);
    });

    it('searches the infodesk of the current event only', function () {
        orderIn($this->second, attributes: ['first_name' => 'Fremda', 'last_name' => 'Gast']);

        Livewire::test(EventInfodesk::class, ['record' => 'test'])->set('search', 'Fremda')->assertSee('Niemand gefunden');
        Livewire::test(EventInfodesk::class, ['record' => 'zweites'])->set('search', 'Fremda')->assertSee('Fremda Gast');
    });

    it('does not assign seats to an order of another event from the plan', function () {
        $foreign = orderIn($this->second);

        Livewire::withQueryParams(['bestellung' => $foreign->id])
            ->test(EventSaalplan::class, ['record' => 'test'])
            ->call('saveOrderSeats', ['P-1-1'])
            ->assertReturned(false);

        expect($foreign->seats()->count())->toBe(0);
    });

    it('shows the event\'s own numbers in its widget', function () {
        orderIn($this->first, 3);

        expect(Livewire::test(SeatOverview::class, ['record' => $this->first])->instance()->record->is($this->first))->toBeTrue();
        expect(app(SeatStats::class)->snapshot($this->second)['sold'])->toBe(0);
    });

    it('lists every event on the overview', function () {
        $this->get('/admin')->assertOk();

        Livewire::test(UpcomingEvents::class)->loadTable()->assertSeeText($this->first->name)->assertSeeText($this->second->name);
    });

    it('finds orders of all events in the global search, and links to the right event', function () {
        orderIn($this->second, attributes: ['short_id' => 'o_suche', 'first_name' => 'Rita', 'last_name' => 'Muster']);

        $result = Filament::getGlobalSearchProvider()->getResults('o_suche')->getCategories()->flatten()->first();

        expect($result->title)->toContain($this->second->name)
            ->and($result->url)->toContain('/admin/events/zweites/bestellungen/');
    });

    it('does not open an order of another event through the URL', function () {
        $foreign = orderIn($this->second);

        $this->get("/admin/events/test/bestellungen/{$foreign->id}")->assertNotFound();
        $this->get("/admin/events/test/saalplan?bestellung={$foreign->id}")->assertOk()->assertDontSeeText('Plätze für');
    });

    it('does not reveal who sits on the same seat in another event', function () {
        app(AdminSeats::class)->assign(orderIn($this->second), ['P-1-1']);

        Livewire::test(EventSaalplan::class, ['record' => 'test'])->call('seatDetails', 'P-1-1')->assertReturned(null);
    });

    it('exports the current event, named after it', function () {
        $order = orderIn($this->second, attributes: ['short_id' => 'o_fremd']);
        app(AdminSeats::class)->assign($order, ['P-1-1']);

        $response = $this->get('/admin/events/test/exporte/platzliste.csv')->assertOk();

        expect($response->streamedContent())->not->toContain('o_fremd')
            ->and($response->headers->get('content-disposition'))->toContain('test-platzliste.csv');
    });

    it('heads the event page with its name and shows no form', function () {
        $this->get('/admin/events/test')
            ->assertOk()
            ->assertSeeText($this->first->name)
            ->assertDontSeeText('ansehen')
            ->assertDontSeeText('Event-ID in Hi.Events');
    });

    it('finds events by URL', function () {
        $this->get('/admin/events/test')->assertOk()->assertSeeText($this->first->name);
        $this->get('/admin/events/zweites')->assertOk()->assertSeeText($this->second->name);
        $this->get('/admin/events/gibt-es-nicht')->assertNotFound();
    });

    it('creates an event with its seats', function () {
        Livewire::test(CreateEvent::class)
            ->fillForm(['name' => 'Drittes Event', 'hievents_event_id' => '333', 'venue_name' => 'Volkshaus'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin/events/drittes-event');

        expect(Event::where('slug', 'drittes-event')->sole()->seats()->count())->toBe(1139);
    });

    it('keeps the name of the create page free', function () {
        expect(Event::factory()->create(['name' => 'Neu'])->slug)->toBe('event-neu');
    });

    it('saves the event settings', function () {
        Livewire::test(EditEvent::class, ['record' => 'test'])
            ->fillForm([
                'contact_email' => 'neu@example.ch',
                'selection_opens_at' => '2027-01-01 10:00',
                'selection_deadline' => '2027-01-10 23:59',
                'reminder_offsets_days' => ['5', '1'],
                'seated_product_ids' => ['77'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->first->refresh())
            ->contact_email->toBe('neu@example.ch')
            ->reminder_offsets_days->toBe([5, 1])
            ->seated_product_ids->toBe(['77'])
            ->and($this->first->selection_deadline->format('d.m.Y H:i'))->toBe('10.01.2027 23:59');
    });

    it('refuses a deadline before the opening', function () {
        Livewire::test(EditEvent::class, ['record' => 'test'])
            ->fillForm(['selection_opens_at' => '2027-01-10 10:00', 'selection_deadline' => '2027-01-01 10:00'])
            ->call('save')
            ->assertHasFormErrors(['selection_deadline']);
    });

    it('links the settings in the navigation', function () {
        $this->get('/admin/events/test')->assertOk()->assertSee('/admin/events/test/einstellungen')->assertSee('/admin/events/test/saalplan');
    });

    it('keeps the deletion promise in the event settings', function () {
        Livewire::test(EditEvent::class, ['record' => 'test'])
            ->fillForm(['purge_after_days' => 40])
            ->call('save')
            ->assertHasFormErrors(['purge_after_days']);
    });
});

it('reaches seats only through their event', function () {
    $files = collect(File::allFiles(app_path()))
        ->reject(fn ($file) => $file->getFilename() === 'SeatImporter.php')
        ->filter(fn ($file) => preg_match('/\bSeat::(?!class\b)/', $file->getContents()))
        ->map->getRelativePathname()
        ->values()
        ->all();

    expect($files)->toBe([]);
});
