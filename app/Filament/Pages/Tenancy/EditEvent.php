<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditEvent extends EditTenantProfile
{
    /** The privacy note promises deletion within this many days of the event. */
    private const PROMISED_DAYS = 45;

    protected static ?string $slug = 'einstellungen';

    public static function getLabel(): string
    {
        return 'Event-Einstellungen';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event')->columns(2)->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(255),
                TextInput::make('venue_name')->label('Ort')->maxLength(255),
                DateTimePicker::make('starts_at')->label('Beginn')->seconds(false)
                    ->helperText('Bestimmt auch, wann die Personendaten gelöscht werden.'),
                DateTimePicker::make('doors_at')->label('Türöffnung')->seconds(false),
                TextInput::make('contact_email')->label('Kontakt-E-Mail')->email(),
            ]),
            Section::make('Platzwahl')->columns(2)->schema([
                DateTimePicker::make('selection_opens_at')->label('Offen ab')->seconds(false),
                DateTimePicker::make('selection_deadline')->label('Frist')->seconds(false)
                    ->after('selection_opens_at'),
                TagsInput::make('reminder_offsets_days')->label('Erinnerungen (Tage vor der Frist)')->placeholder('Hinzufügen')
                    ->nestedRecursiveRules(['integer', 'min:0'])
                    ->dehydrateStateUsing(fn (?array $state): array => array_map('intval', $state ?? [])),
                TextInput::make('final_mail_offset_days')->label('Letzte Info (Tage vor dem Event)')
                    ->integer()->minValue(0)->required(),
            ]),
            Section::make('Hi.Events')->columns(2)->schema([
                TagsInput::make('seated_product_ids')->label('Produkte mit Sitzplatz (IDs)')->placeholder('Hinzufügen'),
                TagsInput::make('wheelchair_question_ids')->label('Fragen zum Rollstuhlplatz (IDs)')->placeholder('Hinzufügen'),
                TagsInput::make('wheelchair_notify_emails')->label('Empfänger für Rollstuhlplätze')->placeholder('Hinzufügen')
                    ->nestedRecursiveRules(['email']),
            ]),
            Section::make('Datenschutz')->schema([
                TextInput::make('purge_after_days')->label('Personendaten löschen nach (Tage)')
                    ->integer()->minValue(1)->required()
                    ->maxValue(self::PROMISED_DAYS - (int) config('seating.backup_keep_days'))
                    ->helperText('Backups laufen danach noch '.config('seating.backup_keep_days').' Tage. Zusammen dürfen es höchstens '.self::PROMISED_DAYS.' Tage sein.'),
            ]),
        ]);
    }
}
