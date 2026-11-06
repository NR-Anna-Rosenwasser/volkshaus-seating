<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Exceptions\SeatConflict;
use App\Models\Event;
use App\Models\Order;
use App\Models\Seat;
use App\Services\BestSeats;
use App\Services\SeatPlan;
use App\Services\SeatSelection;
use App\Support\SelectionWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SelectionController extends Controller
{
    public function __construct(
        private readonly SeatPlan $plan,
        private readonly SeatSelection $selection,
    ) {}

    public function show(Order $order): View
    {
        $seats = $this->seatsOf($order);
        $event = $order->event;

        return view('selection.show', [
            'event' => $event,
            'order' => $order,
            'layout' => $event->venueLayout(),
            'mode' => $this->mode($order),
            'needed' => $order->seatsNeeded(),
            'seats' => $seats,
            'states' => $this->states($event, $seats),
            'deadline' => SelectionWindow::format($event->window()->deadline()),
            'opensAt' => SelectionWindow::format($event->window()->opensAt()),
        ]);
    }

    public function availability(Order $order): JsonResponse
    {
        return response()->json(['seats' => $this->states($order->event, $this->seatsOf($order))]);
    }

    public function suggestion(Order $order, BestSeats $bestSeats): JsonResponse
    {
        abort_unless($this->selection->mayChoose($order), 403);

        return response()->json([
            'seats' => $bestSeats->find($order->event, $order->seatsNeeded(), $this->seatsOf($order)->pluck('code')->all()),
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
        $event = $order->event;

        return view('selection.ticket', [
            'event' => $event,
            'order' => $order,
            'layout' => $event->venueLayout(),
            'seats' => $this->seatsOf($order),
            'eventDate' => SelectionWindow::format($event->starts_at),
            'doorsAt' => SelectionWindow::format($event->doors_at),
        ]);
    }

    private function mode(Order $order): string
    {
        return match (true) {
            $order->status === OrderStatus::Cancelled => 'cancelled',
            $order->needs_wheelchair => 'wheelchair',
            $order->seatsNeeded() === 0 => 'none',
            ! $order->event->window()->hasStarted() => 'not-open',
            ! $order->event->window()->isOpen() => 'closed',
            default => 'choose',
        };
    }

    /**
     * Current states, in which a group's own seats count as free for itself.
     *
     * @param  Collection<int, Seat>  $own
     * @return array<string, string>
     */
    private function states(Event $event, Collection $own): array
    {
        return [...$this->plan->fresh($event)['seats'], ...$own->mapWithKeys(fn (Seat $seat): array => [$seat->code => 'free'])->all()];
    }

    /**
     * @return Collection<int, Seat>
     */
    private function seatsOf(Order $order): Collection
    {
        return $order->seats()->orderBy('section')->orderBy('row')->orderBy('number')->get();
    }
}
