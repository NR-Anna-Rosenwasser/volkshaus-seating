<?php

namespace App\Filament\Pages;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\Order;
use App\Models\Seat;
use App\Support\VenueLayout;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class Infodesk extends Page
{
    private const MIN_SEARCH = 2;

    private const MAX_RESULTS = 15;

    protected string $view = 'filament.pages.infodesk';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?string $title = 'Infodesk';

    protected static ?string $slug = 'infodesk';

    protected static ?int $navigationSort = 4;

    public string $search = '';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['hits' => $this->hits(), 'searched' => mb_strlen(trim($this->search)) >= self::MIN_SEARCH];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hits(): array
    {
        $search = trim($this->search);

        if (mb_strlen($search) < self::MIN_SEARCH) {
            return [];
        }

        $like = '%'.addcslashes($search, '%_\\').'%';
        $named = fn (Builder $query) => $query
            ->where('first_name', 'ilike', $like)
            ->orWhere('last_name', 'ilike', $like)
            ->orWhereRaw("concat(first_name, ' ', last_name) ilike ?", [$like]);

        $layout = app(VenueLayout::class);

        return Order::query()
            ->where(fn (Builder $query) => $query
                ->where($named)
                ->orWhere('short_id', 'ilike', $like)
                ->orWhereHas('attendees', $named))
            ->with(['seats', 'attendees'])
            ->withTicketCount()
            ->orderBy('last_name')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'name' => "{$order->first_name} {$order->last_name}",
                'number' => $order->short_id,
                'cancelled' => $order->status === OrderStatus::Cancelled,
                'wheelchair' => $order->needs_wheelchair,
                'seats' => $order->seats->sortBy(['section', 'row', 'number'])
                    ->map(fn (Seat $seat): string => $layout->seatLabel($seat))
                    ->values()
                    ->all(),
                'missing' => $order->status === OrderStatus::Active ? max($order->tickets - $order->seats->count(), 0) : 0,
                'people' => $order->attendees
                    ->map(fn (Attendee $attendee): string => trim("{$attendee->first_name} {$attendee->last_name}"))
                    ->unique()
                    ->implode(', '),
            ])
            ->all();
    }
}
