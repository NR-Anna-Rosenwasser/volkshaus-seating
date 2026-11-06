<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ManageRelatedRecords;

class ManageEventOrders extends ManageRelatedRecords
{
    protected static string $resource = EventResource::class;

    protected static string $relationship = 'orders';

    protected static ?string $relatedResource = OrderResource::class;

    protected static ?string $navigationLabel = 'Bestellungen';

    protected static ?string $title = 'Bestellungen';
}
