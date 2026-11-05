<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Exceptions\SeatConflict;
use App\Models\Order;
use App\Models\Seat;
use App\Services\BestSeats;
use App\Services\SeatPlan;
use App\Services\SeatSelection;
use App\Support\SelectionWindow;
use App\Support\VenueLayout;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SelectionController extends Controller
{
    public function __construct(
        private readonly SeatPlan $plan,
        private readonly SeatSelection $selection,
        private readonly SelectionWindow $window,
        private readonly VenueLayout $layout,
    ) {}

    public function show(Order $order): View
    {
        $seats = $this->seatsOf($order);

        return view('selection.show', [
            'order' => $order,
            'layout' => $this->layout,
            'mode' => $this->mode($order),
            'needed' => $order->seatsNeeded(),
            'seats' => $seats,
            'states' => $this->states($seats),
            'deadline' => SelectionWindow::format($this->window->deadline()),
            'opensAt' => SelectionWindow::format($this->window->opensAt()),
        ]);
    }

    public function availability(Order $order): JsonResponse
    {
        return response()->json(['seats' => $this->states($this->seatsOf($order))]);
    }

    public function suggestion(Order $order, BestSeats $bestSeats): JsonResponse
    {
        abort_unless($this->selection->mayChoose($order), 403);

        return response()->json([
            'seats' => $bestSeats->find($order->seatsNeeded(), $this->seatsOf($order)->pluck('id')->all()),
        ]);
    }

    public function save(Request $request, Order $order): JsonResponse
    {
        abort_unless($this->selection->mayChoose($order), 403);

        $seatIds = $request->validate([
            'seats' => ['required', 'array'],
            'seats.*' => ['required', 'string', 'distinct'],
        ])['seats'];

        try {
            $this->selection->save($order, $seatIds);
        } catch (SeatConflict $conflict) {
            return response()->json(['conflicts' => $conflict->seatIds], 409);
        }

        return response()->json(['seats' => $seatIds]);
    }

    public function ticket(Order $order): View
    {
        return view('selection.ticket', [
            'order' => $order,
            'layout' => $this->layout,
            'seats' => $this->seatsOf($order),
            'eventDate' => SelectionWindow::format($this->date('seating.event_date')),
            'doorsAt' => SelectionWindow::format($this->date('seating.doors_at')),
        ]);
    }

    private function mode(Order $order): string
    {
        return match (true) {
            $order->status === OrderStatus::Cancelled => 'cancelled',
            $order->needs_wheelchair => 'wheelchair',
            $order->seatsNeeded() === 0 => 'none',
            ! $this->window->hasStarted() => 'not-open',
            ! $this->window->isOpen() => 'closed',
            default => 'choose',
        };
    }

    /**
     * Current states, in which a group's own seats count as free for itself.
     *
     * @param  Collection<int, Seat>  $own
     * @return array<string, string>
     */
    private function states(Collection $own): array
    {
        return [...$this->plan->fresh()['seats'], ...$own->mapWithKeys(fn (Seat $seat): array => [$seat->id => 'free'])->all()];
    }

    /**
     * @return Collection<int, Seat>
     */
    private function seatsOf(Order $order): Collection
    {
        return $order->seats()->orderBy('section')->orderBy('row')->orderBy('number')->get();
    }

    private function date(string $key): ?CarbonInterface
    {
        $value = config($key);

        return $value ? Carbon::parse($value, config('app.timezone')) : null;
    }
}
