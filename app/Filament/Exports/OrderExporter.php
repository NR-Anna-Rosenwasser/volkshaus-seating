<?php

namespace App\Filament\Exports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Seat;
use App\Support\VenueLayout;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('short_id')->label('Nummer'),
            ExportColumn::make('first_name')->label('Vorname'),
            ExportColumn::make('last_name')->label('Nachname'),
            ExportColumn::make('email')->label('E-Mail'),
            ExportColumn::make('status')->label('Status')
                ->formatStateUsing(fn (OrderStatus $state): string => $state === OrderStatus::Active ? 'Aktiv' : 'Storniert'),
            ExportColumn::make('tickets')->label('Tickets'),
            ExportColumn::make('seats_list')->label('Plätze')
                ->state(fn (Order $record): string => static::seatLabels($record)),
            ExportColumn::make('needs_wheelchair')->label('Rollstuhl')
                ->formatStateUsing(fn (bool $state): string => $state ? 'Ja' : 'Nein'),
            ExportColumn::make('seats_confirmed_at')->label('Plätze gespeichert am')
                ->formatStateUsing(fn ($state): string => $state?->format('d.m.Y H:i') ?? ''),
            ExportColumn::make('created_at')->label('Bestellt am')
                ->formatStateUsing(fn ($state): string => $state->format('d.m.Y H:i')),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('seats')->withTicketCount();
    }

    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public function getFileDiskName(): string
    {
        // Never public: the file holds names and addresses. In Docker,
        // storage/app/private is a volume shared by app, horizon and scheduler.
        return 'local';
    }

    public function getFileName(Export $export): string
    {
        return "bestellungen-{$export->getKey()}";
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Export bereit';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = Number::format($export->successful_rows, locale: 'de_CH').' Bestellungen exportiert.';

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failed, locale: 'de_CH').' konnten nicht exportiert werden.';
        }

        return $body;
    }

    private static function seatLabels(Order $order): string
    {
        $layout = app(VenueLayout::class);

        return $order->seats
            ->sortBy(['section', 'row', 'number'])
            ->map(fn (Seat $seat): string => $layout->seatLabel($seat))
            ->implode(', ');
    }
}
