<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Exports\OrderExporter;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Exports what the table shows, filters and search included.
            ExportAction::make()
                ->label('Als Excel exportieren')
                ->exporter(OrderExporter::class)
                ->modalHeading('Bestellungen exportieren')
                ->modalDescription('Die Datei enthält Namen und E-Mail-Adressen. Bitte nach Gebrauch löschen.'),
        ];
    }
}
