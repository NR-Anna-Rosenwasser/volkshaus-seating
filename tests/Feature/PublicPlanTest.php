<?php

use App\Enums\BlockReason;
use App\Models\Attendee;
use App\Models\Order;
use App\Models\Seat;
use App\Services\SeatPlan;
use App\Support\VenueLayout;
use Database\Seeders\SeatSeeder;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(SeatSeeder::class);
});

function seatSomeone(string $seatId): Attendee
{
    $order = Order::factory()->create([
        'first_name' => 'Vreni',
        'last_name' => 'Geheimnis',
        'email' => 'vreni@example.ch',
    ]);
    $attendee = Attendee::factory()->for($order)->create(['first_name' => 'Vreni', 'last_name' => 'Geheimnis']);

    Seat::findOrFail($seatId)->update(['attendee_id' => $attendee->id]);

    return $attendee;
}

it('sends visitors from the root to the plan', function () {
    $this->get('/')->assertRedirect('/plan');
});

it('shows the whole hall and the reservation note', function () {
    $response = $this->get('/plan')->assertOk();

    expect(substr_count($response->getContent(), 'data-seat="'))->toBe(1139)
        ->and(substr_count($response->getContent(), 'data-section="'))->toBe(4);

    $response
        ->assertSeeText('Bühne')
        ->assertSeeText("Noch 1'115 von 1'115 Plätzen frei")
        ->assertSeeText('Die Platzwahl erfolgt nach dem Ticketkauf. Freie Plätze werden nicht reserviert.');
});

it('draws every seat in its current state', function () {
    seatSomeone('P-12-7');

    $content = $this->get('/plan')->getContent();

    expect($content)
        ->toMatch('/data-seat="P-12-7"\s+data-segment="\d+"\s+data-state="taken"/')
        ->toMatch('/data-seat="P-1-1"\s+data-segment="\d+"\s+data-state="free"/')
        ->toMatch('/data-seat="P-4-26"\s+data-segment="\d+"\s+data-state="blocked"/');
});

it('marks exactly the obstructed seats', function () {
    $content = $this->get('/plan')->getContent();

    expect(substr_count($content, 'seat--obstructed"'))->toBe(81);
});

it('counts free and sellable seats per section and in total', function () {
    seatSomeone('P-12-7');
    Seat::findOrFail('GM-1-1')->update(['block_reason' => BlockReason::Press]);

    $this->getJson('/api/plan')
        ->assertOk()
        ->assertJsonPath('seats.P-12-7', 'taken')
        ->assertJsonPath('seats.GM-1-1', 'blocked')
        ->assertJsonPath('seats.P-1-1', 'free')
        ->assertJsonPath('sections.parterre', ['free' => 617, 'sellable' => 618])
        ->assertJsonPath('sections.galerie-mitte', ['free' => 213, 'sellable' => 213])
        ->assertJsonPath('total', ['free' => 1113, 'sellable' => 1114]);
});

it('exposes seat states only', function () {
    $attendee = seatSomeone('P-12-7');
    Seat::findOrFail('GM-1-1')->update(['block_reason' => BlockReason::Vip, 'block_note' => 'Frau Stadtpräsidentin']);

    foreach ([$this->get('/plan'), $this->get('/api/plan')] as $response) {
        $response
            ->assertDontSee('Vreni')
            ->assertDontSee('Geheimnis')
            ->assertDontSee('vreni@example.ch')
            ->assertDontSee('Stadtpräsidentin')
            ->assertDontSee('"vip"', false);
    }

    expect(array_keys($this->getJson('/api/plan')->json()))->toBe(['seats', 'sections', 'total'])
        ->and(array_unique(array_values($this->getJson('/api/plan')->json('seats'))))
        ->each->toBeIn(['free', 'taken', 'blocked'])
        ->and($this->getJson('/api/plan')->content())->not->toContain((string) $attendee->hievents_attendee_id);
});

it('caches the plan for 30 seconds', function () {
    expect(SeatPlan::CACHE_SECONDS)->toBe(30);

    $this->getJson('/api/plan')->assertJsonPath('seats.P-12-7', 'free');

    seatSomeone('P-12-7');

    $this->travel(29)->seconds();
    $this->getJson('/api/plan')->assertJsonPath('seats.P-12-7', 'free');

    $this->travel(2)->seconds();
    $this->getJson('/api/plan')->assertJsonPath('seats.P-12-7', 'taken');
});

it('labels side gallery rows above their columns and hall rows at both ends', function () {
    $sections = collect(app(VenueLayout::class)->sections())->keyBy('id');

    expect($sections['parterre']['rowLabels'])->toHaveCount(48)
        ->and($sections['galerie-rechts']['rowLabels'])->toHaveCount(4);
});

it('fits the whole hall into the view box', function () {
    $layout = app(VenueLayout::class);
    $view = $layout->viewBox();

    foreach ($layout->sections() as $section) {
        expect($section['box']['x'])->toBeGreaterThanOrEqual($view['x'])
            ->and($section['box']['x'] + $section['box']['width'])->toBeLessThanOrEqual($view['x'] + $view['width'])
            ->and($section['labelY'])->toBeLessThan($view['y'] + $view['height']);
    }
});
