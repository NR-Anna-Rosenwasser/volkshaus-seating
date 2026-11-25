<?php

use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Mail\SeatMail;
use App\Models\Attendee;
use App\Models\Order;
use App\Models\Seat;
use Database\Seeders\SeatSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * One seat means one person, addressed as «du», and «Platz». Everything else
 * is «ihr» and «Plätze». This renders what buyers see and checks both ways.
 */

const IHR_WORDS = '/\b(ihr|Ihr|euch|Euch|euer|eure[mnrs]?|Eure[mnrs]?|habt|könnt|wählt|Wählt|tippt|Tippt|seht|schreibt|meldet|prüft|versucht|leitet)\b|\b(Sitz)?[pP]lätze\b/u';

const DU_WORDS = '/\b(du|Du|dich|dir|Dir|dein[emnrs]?|Dein[emnrs]?|hast|kannst|Wähle|wähle|tippe|Tippe|siehst|schreib|melde|prüfe|versuche|leite)\b/u';

beforeEach(function () {
    Mail::fake();
    $this->withoutVite();
    $this->seed(SeatSeeder::class);

    config([
        'seating.event_name' => 'Hässig am Mittwoch',
        'seating.event_date' => now()->addMonth()->toDateTimeString(),
        'seating.doors_at' => now()->addMonth()->subHour()->toDateTimeString(),
        'seating.selection_opens_at' => now()->subDay()->toDateTimeString(),
        'seating.selection_deadline' => now()->addWeek()->toDateTimeString(),
        'seating.contact_email' => 'hallo@example.ch',
        'seating.hievents.public_url' => 'https://tickets.example.ch',
        'seating.event_id' => '7',
    ]);
});

function wordingOrder(int $tickets, array $seats = []): Order
{
    $order = Order::factory()->create(['first_name' => 'Rita', 'short_id' => 'o_abc']);

    foreach (Attendee::factory()->count($tickets)->for($order)->create() as $index => $attendee) {
        if (isset($seats[$index])) {
            Seat::find($seats[$index])->update(['attendee_id' => $attendee->id]);
        }
    }

    return $order;
}

function visibleText(string $html): string
{
    $html = preg_replace('#<(script|style)\b.*?</\1>#s', '', $html);

    return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
}

/**
 * @return array<string, string>
 */
function everythingBuyersSee(int $tickets): array
{
    $seats = array_slice(['P-12-7', 'P-12-8', 'P-12-9'], 0, $tickets);
    $texts = [];

    $order = wordingOrder($tickets, $seats);
    $token = $order->issueToken();
    $texts['page, choose'] = test()->get("/s/{$token}")->getContent();

    test()->withSession(['seats_saved' => true]);
    $texts['pass, just saved'] = test()->get("/s/{$token}/ticket")->getContent();

    foreach ([MailType::Link, MailType::Confirmation, MailType::Reminder, MailType::AutoAssigned, MailType::Final] as $type) {
        $mail = new SeatMail(Order::find($order->id), $type);
        $content = $mail->content();
        $texts["mail {$type->value}, subject"] = $mail->envelope()->subject;
        $texts["mail {$type->value}, html"] = $mail->render();
        $texts["mail {$type->value}, text"] = view($content->text, $content->with)->render();
    }

    $empty = wordingOrder($tickets);
    $texts['pass, no seats'] = test()->get("/s/{$empty->issueToken()}/ticket")->getContent();

    $wheelchair = wordingOrder($tickets);
    $wheelchair->update(['needs_wheelchair' => true]);
    $texts['page, wheelchair'] = test()->get("/s/{$wheelchair->issueToken()}")->getContent();

    $cancelled = wordingOrder($tickets);
    $cancelled->update(['status' => OrderStatus::Cancelled]);
    $cancelled->attendees()->update(['status' => 'cancelled']);
    $texts['page, cancelled'] = test()->get("/s/{$cancelled->issueToken()}")->getContent();

    config(['seating.selection_deadline' => now()->subMinute()->toDateTimeString()]);
    $texts['page, closed'] = test()->get("/s/{$token}")->getContent();

    config(['seating.selection_opens_at' => now()->addDay()->toDateTimeString(), 'seating.selection_deadline' => now()->addWeek()->toDateTimeString()]);
    $texts['page, not open'] = test()->get("/s/{$token}")->getContent();

    return array_map(visibleText(...), $texts);
}

it('says du and Platz to a single ticket, everywhere', function () {
    foreach (everythingBuyersSee(1) as $where => $text) {
        preg_match_all(IHR_WORDS, $text, $found);

        expect($found[0])->toBe([], "«ihr» or «Plätze» in {$where}: ".implode(', ', array_unique($found[0])));
    }
});

it('says ihr to a group, everywhere', function () {
    foreach (everythingBuyersSee(3) as $where => $text) {
        preg_match_all(DU_WORDS, $text, $found);

        expect($found[0])->toBe([], "«du» in {$where}: ".implode(', ', array_unique($found[0])));
    }
});

it('never uses ß', function () {
    foreach ([...everythingBuyersSee(1), ...everythingBuyersSee(3)] as $where => $text) {
        expect($text)->not->toContain('ß', "ß in {$where}");
    }
});

it('keeps the du and ihr rule in the page script', function () {
    $script = file_get_contents(resource_path('js/seat-selection.js'));

    // Every buyer-facing sentence with a du or ihr word sits inside say(), unless it is only shown to groups.
    $groupOnly = [
        'Für eure Gruppe gibt es keine Plätze mehr am Stück. Das ist die beste Aufteilung.',
        'Eure Plätze liegen nicht nebeneinander.',
    ];
    preg_match_all("/(['`])((?:(?!\\1).)*?)\\1/u", $script, $strings);
    $offenders = [];

    foreach ($strings[2] as $index => $string) {
        $hasWord = preg_match(IHR_WORDS, $string) || preg_match(DU_WORDS, $string);
        $before = substr($script, 0, strpos($script, $strings[0][$index]));
        $inSay = preg_match('/say\(\s*(?:\'[^\']*\',\s*)?$/u', $before) === 1;

        if ($hasWord && ! $inSay && ! in_array($string, $groupOnly, true)) {
            $offenders[] = $string;
        }
    }

    expect($offenders)->toBe([]);
});
