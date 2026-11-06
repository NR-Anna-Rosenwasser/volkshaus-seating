<?php

namespace App\Filament\Resources\Events\Pages;

use App\Enums\BlockReason;
use App\Enums\OrderStatus;
use App\Exceptions\SeatConflict;
use App\Filament\Resources\Events\Pages\Concerns\ShowsOneEvent;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Seat;
use App\Services\AdminSeats;
use App\Services\SeatStats;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;

class EventSaalplan extends Page
{
    use ShowsOneEvent;

    /** Long enough to read, short enough not to pile up over the map. */
    private const REMINDER_SECONDS = 8;

    protected string $view = 'filament.pages.saalplan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $title = 'Saalplan';

    protected static ?string $navigationLabel = 'Saalplan';

    /** Set when the page is opened to choose seats for one order. */
    #[Url(as: 'bestellung')]
    public ?int $orderId = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'layout' => $this->event()->venueLayout(),
            'plan' => $this->mapData(),
            'assigning' => $this->assignment(),
            'reasons' => collect(BlockReason::cases())
                ->mapWithKeys(fn (BlockReason $reason): array => [$reason->value => $reason->label()])
                ->all(),
        ];
    }

    /**
     * @return array{seats: array<string, string>, reasons: array<string, string>, sellable: int, sold: int}
     */
    #[Renderless]
    public function mapData(): array
    {
        $seats = $this->event()->seats()->get(['code', 'block_reason', 'attendee_id']);
        $stats = app(SeatStats::class)->snapshot($this->event());

        return [
            'seats' => $seats->mapWithKeys(fn (Seat $seat): array => [$seat->code => $seat->state()->value])->all(),
            'reasons' => $seats->whereNotNull('block_reason')
                ->mapWithKeys(fn (Seat $seat): array => [$seat->code => $seat->block_reason->value])
                ->all(),
            'sellable' => $stats['sellable'],
            'sold' => $stats['sold'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Renderless]
    public function seatDetails(string $seatId): ?array
    {
        $seat = $this->event()->seats()->with('attendee.order')->where('code', $seatId)->first();
        $attendee = $seat?->attendee;

        if ($attendee === null) {
            return null;
        }

        $layout = $this->event()->venueLayout();

        return [
            'id' => $seat->code,
            'label' => $layout->seatLabel($seat),
            'person' => trim("{$attendee->first_name} {$attendee->last_name}"),
            'buyer' => trim("{$attendee->order->first_name} {$attendee->order->last_name}"),
            'orderNumber' => $attendee->order->short_id,
            'orderUrl' => OrderResource::viewUrl($attendee->order),
            'group' => $attendee->order->seats()->orderBy('section')->orderBy('row')->orderBy('number')->get()
                ->map(fn (Seat $groupSeat): string => $layout->seatLabel($groupSeat))
                ->all(),
        ];
    }

    /**
     * @param  list<string>  $seatIds
     */
    public function blockSeats(array $seatIds, string $reason, ?string $note = null): void
    {
        $blockReason = BlockReason::tryFrom($reason);

        if ($blockReason === null) {
            return;
        }

        $result = app(AdminSeats::class)->block($this->event(), array_map('strval', $seatIds), $blockReason, mb_substr((string) $note, 0, 255));

        $this->capacityReminder("{$result['changed']} Plätze gesperrt", $result['skipped'] !== []
            ? count($result['skipped']).' belegte Plätze wurden ausgelassen.'
            : null);
    }

    /**
     * @param  list<string>  $seatIds
     */
    public function unblockSeats(array $seatIds): void
    {
        $changed = app(AdminSeats::class)->unblock($this->event(), array_map('strval', $seatIds));

        $this->capacityReminder("{$changed} Plätze freigegeben");
    }

    public function moveSeat(string $from, string $to): bool
    {
        try {
            app(AdminSeats::class)->move($this->event(), $from, $to);
        } catch (SeatConflict) {
            Notification::make()->title('Dieser Platz ist nicht frei')->danger()->send();

            return false;
        } catch (ValidationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return false;
        }

        Notification::make()->title('Platz verschoben')->success()->send();

        return true;
    }

    /**
     * @param  list<string>  $seatIds
     */
    public function saveOrderSeats(array $seatIds): bool
    {
        $order = $this->orderBeingAssigned();

        if ($order === null) {
            return false;
        }

        try {
            app(AdminSeats::class)->assign($order, array_map('strval', $seatIds));
        } catch (SeatConflict) {
            Notification::make()->title('Mindestens ein Platz wurde gerade vergeben oder gesperrt')->danger()->send();

            return false;
        } catch (ValidationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return false;
        }

        Notification::make()->title('Plätze gespeichert')->success()->send();
        $this->redirect(OrderResource::viewUrl($order));

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function assignment(): ?array
    {
        $order = $this->orderBeingAssigned();

        return $order === null ? null : [
            'name' => trim("{$order->first_name} {$order->last_name}"),
            'number' => $order->short_id,
            'needed' => $order->seatsNeeded(),
            'seats' => $order->seats()->pluck('seats.code')->all(),
            'wheelchair' => $order->needs_wheelchair,
            'backUrl' => OrderResource::viewUrl($order),
        ];
    }

    private function orderBeingAssigned(): ?Order
    {
        $order = $this->orderId === null ? null : $this->event()->orders()->find($this->orderId);

        return $order?->status === OrderStatus::Active && $order->seatsNeeded() > 0 ? $order : null;
    }

    private function capacityReminder(string $title, ?string $detail = null): void
    {
        $sellable = app(SeatStats::class)->snapshot($this->event())['sellable'];

        Notification::make()
            ->title($title)
            ->body(trim(($detail ? "{$detail} " : '')."Kapazität in Hi.Events anpassen: neu {$sellable}."))
            ->warning()
            ->seconds(self::REMINDER_SECONDS)
            ->send();
    }
}
