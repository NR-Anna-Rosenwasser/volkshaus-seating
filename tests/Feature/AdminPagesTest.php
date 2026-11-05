<?php

use App\Enums\BlockReason;
use App\Enums\MailType;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Infodesk;
use App\Filament\Pages\Saalplan;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Widgets\SeatOverview;
use App\Filament\Widgets\SystemStatus;
use App\Mail\SeatMail;
use App\Models\Attendee;
use App\Models\MailLog;
use App\Models\Order;
use App\Models\Seat;
use App\Models\User;
use App\Services\AdminSeats;
use Database\Seeders\SeatSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->withoutVite();
    $this->seed(SeatSeeder::class);
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel('admin');

    config([
        'seating.selection_opens_at' => '2026-12-20 10:00',
        'seating.selection_deadline' => '2027-01-25 23:59',
        'seating.event_date' => '2027-02-01 20:00',
        'seating.contact_email' => 'hallo@example.ch',
    ]);
    $this->travelTo('2027-01-26 12:00:00');
});

function adminOrder(int $tickets = 2, array $attributes = []): Order
{
    $order = Order::factory()->create(['first_name' => 'Rita', 'last_name' => 'Muster', ...$attributes]);
    Attendee::factory()->count($tickets)->for($order)->create(['first_name' => 'Rita', 'last_name' => 'Muster']);

    return $order;
}

it('sends guests to the login', function (string $path) {
    auth()->logout();

    $this->get($path)->assertRedirect('/admin/login');
})->with(['/admin', '/admin/saalplan', '/admin/bestellungen', '/admin/infodesk', '/admin/exporte', '/admin/exporte/platzliste.csv']);

it('runs the login check on Livewire requests too', function () {
    $auth = collect(Filament::getPanel('admin')->getAuthMiddleware());

    expect(Livewire::getPersistentMiddleware())->toContain(...$auth->all());
});

it('shows every admin page in Swiss German', function (string $path, string $text) {
    $this->get($path)->assertOk()->assertSeeText($text)->assertDontSee('ß');
})->with([
    ['/admin', 'Übersicht'],
    ['/admin/saalplan', 'verkaufbare Plätze'],
    ['/admin/bestellungen', 'Bestellungen'],
    ['/admin/infodesk', 'Infodesk'],
    ['/admin/exporte', 'Namensliste'],
    ['/admin/profile', 'E-Mail'],
]);

it('shows the login in Swiss German', function () {
    auth()->logout();

    $this->get('/admin/login')->assertOk()->assertSeeText('Anmelden')->assertDontSee('ß');
});

it('stores two-factor secrets encrypted', function () {
    $user = auth()->user();
    $user->saveAppAuthenticationSecret('SECRET');
    $user->saveAppAuthenticationRecoveryCodes(['code-1']);

    $row = DB::table('users')->find($user->id);

    expect($user->refresh()->getAppAuthenticationSecret())->toBe('SECRET')
        ->and($user->getAppAuthenticationRecoveryCodes())->toBe(['code-1'])
        ->and($row->app_authentication_secret)->not->toContain('SECRET')
        ->and($row->app_authentication_recovery_codes)->not->toContain('code-1');
});

it('finds orders in the global search', function () {
    adminOrder(1, ['short_id' => 'o_rita']);

    $results = Filament::getGlobalSearchProvider()->getResults('o_rita');

    expect($results->getCategories()->flatten()->first()->title)->toContain('Rita Muster');
});

it('shows the seat numbers on the dashboard', function () {
    adminOrder(2);

    Livewire::test(SeatOverview::class)->assertSee('Verkauft')->assertSee('Ohne Platz');
    Livewire::test(SystemStatus::class)->assertSee('Letzter Webhook')->assertSee('Noch nie');
});

it('warns on the dashboard when there are more tickets than seats', function () {
    Seat::whereNull('block_reason')->update(['block_reason' => BlockReason::Other]);
    adminOrder(2);

    Livewire::test(SeatOverview::class)->assertSee('2 Tickets mehr als Plätze');
});

it('previews auto-assign and runs it from the dashboard', function () {
    $order = adminOrder(2);

    Livewire::test(Dashboard::class)
        ->mountAction('autoAssign')
        ->assertMountedActionModalSee(['Noch ist nichts geändert', 'Rita Muster'])
        ->callMountedAction()
        ->assertNotified('1 Bestellungen zugeteilt');

    expect($order->seats()->count())->toBe(2);
});

it('offers no way to run auto-assign before the deadline', function () {
    $this->travelTo('2027-01-10 12:00:00');
    $order = adminOrder(2);

    Livewire::test(Dashboard::class)
        ->mountAction('autoAssign')
        ->assertMountedActionModalSee('Die Frist läuft noch')
        ->callMountedAction()
        ->assertForbidden();

    expect($order->seats()->count())->toBe(0);
});

it('sends reminders from the dashboard, not twice in a row', function () {
    $this->travelTo('2027-01-02 12:00:00');
    $order = adminOrder(2);
    MailLog::create(['order_id' => $order->id, 'type' => MailType::Link, 'sent_at' => '2026-12-21 12:00:00']);

    Livewire::test(Dashboard::class)->callAction('reminders')->assertNotified('1 Erinnerungen versendet');
    Livewire::test(Dashboard::class)->callAction('reminders')->assertNotified('0 Erinnerungen versendet');

    Mail::assertQueuedCount(1);
    expect($order->refresh()->reminders_sent)->toBe(0);
});

it('sends the final mail from the dashboard', function () {
    $order = adminOrder(1);
    app(AdminSeats::class)->assign($order, ['P-1-1']);

    Livewire::test(Dashboard::class)->callAction('finalMail')->assertNotified('1 Schlussmails versendet');

    expect(MailLog::where(['order_id' => $order->id, 'type' => MailType::Final])->count())->toBe(1);
});

it('reports a failed reconciliation', function () {
    config(['seating.hievents.base_url' => 'https://hi.test', 'seating.hievents.api_key' => 'key', 'seating.event_id' => '1', 'seating.seated_product_ids' => ['1']]);
    Http::fake(['*' => Http::response([], 500)]);

    Livewire::test(Dashboard::class)->callAction('reconcile')->assertNotified('Abgleich fehlgeschlagen');
});

it('finds orders by name, email and number', function () {
    $rita = adminOrder(1, ['email' => 'rita@example.ch', 'short_id' => 'o_rita']);
    $beat = adminOrder(1, ['first_name' => 'Beat', 'last_name' => 'Keller', 'email' => 'beat@example.ch', 'short_id' => 'o_beat']);

    foreach (['Keller', 'beat@example.ch', 'o_beat'] as $search) {
        Livewire::test(ListOrders::class)
            ->searchTable($search)
            ->assertCanSeeTableRecords([$beat])
            ->assertCanNotSeeTableRecords([$rita]);
    }
});

it('filters orders that are missing seats', function () {
    $missing = adminOrder(2);
    $seated = adminOrder(1);
    app(AdminSeats::class)->assign($seated, ['P-1-1']);

    Livewire::test(ListOrders::class)
        ->filterTable('missing')
        ->assertCanSeeTableRecords([$missing])
        ->assertCanNotSeeTableRecords([$seated]);
});

it('shows seats and tickets on the order page', function () {
    $order = adminOrder(2);
    app(AdminSeats::class)->assign($order, ['P-1-1']);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertSee('Parterre · Reihe 1 · Platz 1')
        ->assertSee('kein Platz');
});

it('links from the order page to the plan', function () {
    $order = adminOrder(2);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->assertActionHasUrl('assign', Saalplan::getUrl(['bestellung' => $order->id]));
});

it('offers no seats for an order that needs none', function () {
    $order = adminOrder(1, ['status' => 'cancelled']);

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertActionHidden('assign');
});

it('opens the plan for one order, with its seats', function () {
    $order = adminOrder(2, ['short_id' => 'o_rita']);
    app(AdminSeats::class)->assign($order, ['P-1-1']);

    $this->get("/admin/saalplan?bestellung={$order->id}")
        ->assertOk()
        ->assertSeeText('Plätze für Rita Muster')
        ->assertSeeText('o_rita')
        ->assertSeeText('Bestätigung per E-Mail')
        ->assertDontSeeText('Bereich wählen');
});

it('says wheelchair orders get no automatic mail on the plan', function () {
    $order = adminOrder(1, ['needs_wheelchair' => true]);

    $this->get("/admin/saalplan?bestellung={$order->id}")->assertSeeText('keine automatische E-Mail');
});

it('shows the normal plan for an unknown or cancelled order', function () {
    $cancelled = adminOrder(1, ['status' => 'cancelled']);

    foreach ([$cancelled->id, 999999] as $id) {
        $this->get("/admin/saalplan?bestellung={$id}")->assertOk()->assertDontSeeText('Plätze für')->assertSeeText('Bereich wählen');
    }
});

it('saves seats from the plan and returns to the order', function () {
    $order = adminOrder(2);

    Livewire::withQueryParams(['bestellung' => $order->id])
        ->test(Saalplan::class)
        ->call('saveOrderSeats', ['P-1-1', 'P-1-2'])
        ->assertReturned(true)
        ->assertNotified('Plätze gespeichert')
        ->assertRedirect(OrderResource::getUrl('view', ['record' => $order]));

    expect($order->seats()->count())->toBe(2);
});

it('explains when seats from the plan are gone', function () {
    $order = adminOrder(1);
    app(AdminSeats::class)->assign(adminOrder(1), ['P-1-1']);

    Livewire::withQueryParams(['bestellung' => $order->id])
        ->test(Saalplan::class)
        ->call('saveOrderSeats', ['P-1-1'])
        ->assertReturned(false)
        ->assertNotified('Mindestens ein Platz wurde gerade vergeben oder gesperrt');

    expect($order->seats()->count())->toBe(0);
});

it('saves nothing without an order', function () {
    Livewire::test(Saalplan::class)->call('saveOrderSeats', ['P-1-1'])->assertReturned(false);

    expect(Seat::whereNotNull('attendee_id')->count())->toBe(0);
});

it('issues a new link and retires the old one', function () {
    $order = adminOrder(1);
    $old = $order->issueToken();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->callAction('newLink')
        ->assertNotified('Neuer Link kopiert');

    $this->get("/s/{$old}")->assertNotFound();
    $this->get($order->refresh()->selectionUrl())->assertOk();
});

it('resends a confirmation', function () {
    $order = adminOrder(1);
    app(AdminSeats::class)->assign($order, ['P-1-1']);
    $confirmations = fn (): int => Mail::queued(SeatMail::class, fn (SeatMail $mail) => $mail->type === MailType::Confirmation)->count();
    $before = $confirmations();

    Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('resendConfirmation');

    expect($confirmations())->toBe($before + 1);
});

it('cannot create orders by hand', function () {
    expect(OrderResource::canCreate())->toBeFalse();
});

it('blocks seats on the plan with a capacity reminder', function () {
    $sellable = Seat::sellable()->count();

    Livewire::test(Saalplan::class)
        ->call('blockSeats', ['P-1-1', 'P-1-2'], 'press', 'Radio')
        ->assertNotified(Notification::make()->title('2 Plätze gesperrt')->body('Kapazität in Hi.Events anpassen: neu '.($sellable - 2).'.')->warning()->seconds(8));

    expect(Seat::find('P-1-1')->block_reason)->toBe(BlockReason::Press);
});

it('releases seats on the plan', function () {
    Seat::whereKey('P-1-1')->update(['block_reason' => BlockReason::Vip]);

    Livewire::test(Saalplan::class)->call('unblockSeats', ['P-1-1'])->assertNotified();

    expect(Seat::find('P-1-1')->block_reason)->toBeNull();
});

it('gives the plan seat states and block reasons', function () {
    Seat::whereKey('P-1-1')->update(['block_reason' => BlockReason::Vip]);

    $data = (new Saalplan)->mapData();

    expect($data['seats']['P-1-1'])->toBe('blocked')
        ->and($data['reasons']['P-1-1'])->toBe('vip')
        ->and($data['seats']['P-1-2'])->toBe('free');
});

it('shows who sits on a seat and moves them', function () {
    $order = adminOrder(1, ['short_id' => 'o_rita']);
    app(AdminSeats::class)->assign($order, ['P-1-1']);

    $details = (new Saalplan)->seatDetails('P-1-1');

    expect($details['person'])->toBe('Rita Muster')
        ->and($details['orderNumber'])->toBe('o_rita')
        ->and($details['group'])->toBe(['Parterre · Reihe 1 · Platz 1'])
        ->and((new Saalplan)->seatDetails('P-1-2'))->toBeNull();

    Livewire::test(Saalplan::class)->call('moveSeat', 'P-1-1', 'P-5-5')->assertReturned(true);

    expect(Seat::find('P-5-5')->attendee_id)->not->toBeNull();
});

it('reports a failed move', function () {
    app(AdminSeats::class)->assign(adminOrder(1), ['P-1-1']);
    app(AdminSeats::class)->assign(adminOrder(1), ['P-1-2']);

    Livewire::test(Saalplan::class)
        ->call('moveSeat', 'P-1-1', 'P-1-2')
        ->assertReturned(false)
        ->assertNotified('Dieser Platz ist nicht frei');
});

it('finds people at the info desk by name, fellow guest and number', function () {
    $order = adminOrder(2, ['short_id' => 'o_rita']);
    $order->attendees()->first()->update(['first_name' => 'Hans', 'last_name' => 'Gast']);
    app(AdminSeats::class)->assign($order, ['P-3-3', 'P-3-4']);
    $other = adminOrder(1, ['first_name' => 'Beat', 'last_name' => 'Keller']);
    $other->attendees()->update(['first_name' => 'Beat', 'last_name' => 'Keller']);

    foreach (['muster', 'Hans Gast', 'o_rita', 'rita mus'] as $search) {
        Livewire::test(Infodesk::class)
            ->set('search', $search)
            ->assertSee('Parterre · Reihe 3 · Platz 3')
            ->assertDontSee('Beat Keller');
    }
});

it('flags missing seats, cancelled orders and wheelchair needs at the info desk', function () {
    adminOrder(2, ['last_name' => 'Ohneplatz']);
    adminOrder(1, ['last_name' => 'Storno', 'status' => 'cancelled']);
    adminOrder(1, ['last_name' => 'Rollator', 'needs_wheelchair' => true]);

    Livewire::test(Infodesk::class)->set('search', 'Ohneplatz')->assertSee('2 Tickets ohne Platz');
    Livewire::test(Infodesk::class)->set('search', 'Storno')->assertSee('storniert');
    Livewire::test(Infodesk::class)->set('search', 'Rollator')->assertSee('Rollstuhlplatz');
    Livewire::test(Infodesk::class)->set('search', 'Niemand')->assertSee('Niemand gefunden');
});

it('treats % and _ as plain characters in the search', function () {
    adminOrder(1);

    Livewire::test(Infodesk::class)->set('search', '%%')->assertSee('Niemand gefunden');
});

it('waits for two characters before searching', function () {
    adminOrder(1);

    Livewire::test(Infodesk::class)->set('search', 'M')->assertDontSee('Rita')->assertDontSee('Niemand gefunden');
});

describe('exports', function () {
    beforeEach(function () {
        $order = adminOrder(1);
        $order->attendees()->first()->update(['first_name' => 'Äbi', 'last_name' => 'Zürcher']);
        app(AdminSeats::class)->assign($order, ['P-2-2']);

        $early = adminOrder(1, ['short_id' => 'o_a']);
        $early->attendees()->first()->update(['first_name' => 'Anna', 'last_name' => 'Ärni']);
        app(AdminSeats::class)->assign($early, ['P-1-1']);

        $none = adminOrder(1);
        $none->attendees()->first()->update(['first_name' => 'Noch', 'last_name' => 'Keiner']);

        app(AdminSeats::class)->block(['P-3-1'], BlockReason::Press, 'Radio');
    });

    it('lists who sits where, by seat', function () {
        $csv = $this->get('/admin/exporte/platzliste.csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();

        expect($csv)->toStartWith("\xEF\xBB\xBFBereich;Reihe;Platz;Nachname")
            ->not->toContain('Keiner')
            ->and(strpos($csv, 'Ärni'))->toBeLessThan(strpos($csv, 'Zürcher'));
    });

    it('lists everyone by last name, with or without a seat', function () {
        $csv = $this->get('/admin/exporte/namensliste.csv')->assertOk()->streamedContent();

        expect(strpos($csv, 'Ärni'))->toBeLessThan(strpos($csv, 'Keiner'))
            ->and(strpos($csv, 'Keiner'))->toBeLessThan(strpos($csv, 'Zürcher'))
            ->and($csv)->toContain('Keiner;Noch;"Kein Platz"');
    });

    it('lists blocked seats with their reason', function () {
        $csv = $this->get('/admin/exporte/sperrliste.csv')->assertOk()->streamedContent();

        expect($csv)->toContain('Parterre;3;1;Presse;Radio');
    });

    it('prints the same lists, and caches none', function (string $type, string $text) {
        $this->get("/admin/exporte/{$type}.html")
            ->assertOk()
            ->assertSeeText($text)
            ->assertHeader('cache-control', 'no-store, private');
    })->with([['platzliste', 'Zürcher'], ['namensliste', 'Keiner'], ['sperrliste', 'Radio']]);

    it('keeps spreadsheet formulas in names from running', function () {
        $order = adminOrder(1);
        $order->attendees()->first()->update(['first_name' => 'Evil', 'last_name' => '=HYPERLINK("http://bad")']);

        expect($this->get('/admin/exporte/namensliste.csv')->streamedContent())->toContain("'=HYPERLINK");
    });

    it('rejects unknown exports', function () {
        $this->get('/admin/exporte/geheim.csv')->assertNotFound();
        $this->get('/admin/exporte/platzliste.pdf')->assertNotFound();
    });
});
