<?php

namespace App\Filament\Resources\Orders;

use App\Enums\MailType;
use App\Enums\OrderStatus;
use App\Filament\Pages\Saalplan;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Attendee;
use App\Models\MailLog;
use App\Models\Order;
use App\Models\Seat;
use App\Services\BuyerMailer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;
use Livewire\Component;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'Bestellung';

    protected static ?string $pluralModelLabel = 'Bestellungen';

    protected static ?string $slug = 'bestellungen';

    protected static ?string $recordTitleAttribute = 'short_id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 3;

    public static function getGloballySearchableAttributes(): array
    {
        return ['short_id', 'first_name', 'last_name', 'email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->first_name} {$record->last_name} ({$record->short_id})";
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('seats')->withTicketCount()
                ->withCount(['mailLogs as reminders' => fn (Builder $logs) => $logs->where('type', MailType::Reminder)]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('short_id')->label('Nummer')->searchable()->copyable(),
                TextColumn::make('last_name')->label('Name')
                    ->formatStateUsing(fn (Order $record): string => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('email')->label('E-Mail')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tickets')->label('Tickets')->alignCenter(),
                TextColumn::make('seats_summary')->label('Plätze')
                    ->state(fn (Order $record): array => static::seatLabels($record))
                    ->placeholder('Keine')
                    ->listWithLineBreaks(),
                TextColumn::make('reminders')->label('Erinnerungen')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('needs_wheelchair')->label('Rollstuhl')->boolean()->trueIcon(Heroicon::OutlinedCheckCircle)->falseIcon('')->alignCenter(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => static::statusLabel($state))
                    ->color(fn (OrderStatus $state): string => $state === OrderStatus::Active ? 'success' : 'danger'),
            ])
            ->filters([
                Filter::make('missing')->label('Fehlende Plätze')
                    ->query(fn (Builder $query) => $query->where('status', OrderStatus::Active)->missingSeats()),
                TernaryFilter::make('needs_wheelchair')->label('Rollstuhl'),
                SelectFilter::make('status')->label('Status')->options([
                    OrderStatus::Active->value => 'Aktiv',
                    OrderStatus::Cancelled->value => 'Storniert',
                ]),
            ])
            ->recordActions([
                Action::make('open')->label('Öffnen')->icon(Heroicon::OutlinedEye)
                    ->url(fn (Order $record): string => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bestellung')->columns(2)->schema([
                TextEntry::make('name')->label('Name')->state(fn (Order $record): string => "{$record->first_name} {$record->last_name}"),
                TextEntry::make('email')->label('E-Mail')->copyable(),
                TextEntry::make('short_id')->label('Nummer')->copyable(),
                TextEntry::make('created_at')->label('Bestellt am')->dateTime('d.m.Y, H:i'),
                TextEntry::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => static::statusLabel($state))
                    ->color(fn (OrderStatus $state): string => $state === OrderStatus::Active ? 'success' : 'danger'),
                IconEntry::make('needs_wheelchair')->label('Rollstuhl')->boolean(),
            ]),
            Section::make('Tickets und Plätze')->schema([
                TextEntry::make('tickets_list')->hiddenLabel()->listWithLineBreaks()
                    ->state(fn (Order $record): array => $record->attendees()->with('seat')->orderBy('id')->get()
                        ->filter(fn (Attendee $attendee): bool => $attendee->needs_seat)
                        ->map(fn (Attendee $attendee): string => static::ticketLine($attendee))
                        ->values()
                        ->all()),
            ]),
            Section::make('E-Mails')->collapsed()->schema([
                TextEntry::make('mail_list')->hiddenLabel()->listWithLineBreaks()->placeholder('Noch keine')
                    ->state(fn (Order $record): array => $record->mailLogs()->latest('sent_at')->get()
                        ->map(fn (MailLog $log): string => $log->sent_at->format('d.m.Y, H:i')." · {$log->type->value}")
                        ->all()),
            ]),
        ]);
    }

    /**
     * @return list<Action>
     */
    public static function orderActions(): array
    {
        return [
            Action::make('assign')
                ->label('Plätze im Saalplan zuweisen')
                ->icon(Heroicon::OutlinedMap)
                ->visible(fn (Order $record): bool => $record->status === OrderStatus::Active && $record->seatsNeeded() > 0)
                ->url(fn (Order $record): string => Saalplan::getUrl(['bestellung' => $record->id])),
            Action::make('newLink')
                ->label('Neuer Link')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->visible(fn (Order $record): bool => $record->status === OrderStatus::Active)
                ->requiresConfirmation()
                ->modalHeading('Neuen persönlichen Link erstellen?')
                ->modalDescription('Der bisherige Link funktioniert danach nicht mehr, auch nicht der aus den E-Mails. Der neue wird in die Zwischenablage kopiert.')
                ->modalSubmitActionLabel('Link erstellen')
                ->action(function (Order $record, Component $livewire): void {
                    $url = route('selection.show', $record->issueToken());

                    $livewire->js('navigator.clipboard?.writeText('.Js::from($url).')');

                    Notification::make()->title('Neuer Link kopiert')->body($url)->success()->persistent()->send();
                }),
            Action::make('resendConfirmation')
                ->label('Bestätigung senden')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->visible(fn (Order $record): bool => $record->status === OrderStatus::Active && $record->seats()->exists())
                ->requiresConfirmation()
                ->modalHeading('Bestätigung senden?')
                ->modalDescription(fn (Order $record): string => "Geht an {$record->email}.")
                ->modalSubmitActionLabel('Senden')
                ->action(function (Order $record): void {
                    app(BuyerMailer::class)->send($record, MailType::Confirmation);

                    Notification::make()->title('Bestätigung versendet')->success()->send();
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function statusLabel(OrderStatus $status): string
    {
        return $status === OrderStatus::Active ? 'Aktiv' : 'Storniert';
    }

    /**
     * @return list<string>
     */
    private static function seatLabels(Order $order): array
    {
        $layout = $order->event->venueLayout();

        return $order->seats
            ->sortBy(['section', 'row', 'number'])
            ->map(fn (Seat $seat): string => $layout->seatLabel($seat))
            ->values()
            ->all();
    }

    private static function ticketLine(Attendee $attendee): string
    {
        $name = trim("{$attendee->first_name} {$attendee->last_name}");

        if ($attendee->status->value === 'cancelled') {
            return "{$name}: storniert";
        }

        return $name.': '.($attendee->seat ? $attendee->order->event->venueLayout()->seatLabel($attendee->seat) : 'kein Platz');
    }
}
