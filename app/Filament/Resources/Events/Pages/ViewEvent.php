<?php

namespace App\Filament\Resources\Events\Pages;

use App\Console\Commands\Reconcile;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Widgets\EventStatus;
use App\Filament\Widgets\SeatOverview;
use App\Models\Event;
use App\Services\AutoAssign;
use App\Support\StatusLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected static ?string $navigationLabel = 'Übersicht';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected function event(): Event
    {
        /** @var Event */
        return $this->getRecord();
    }

    public function getTitle(): string
    {
        return $this->event()->name;
    }

    public function getBreadcrumb(): string
    {
        return 'Übersicht';
    }

    /**
     * The widgets are the page. Without this, Filament adds the create form, read-only.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getHeaderWidgets(): array
    {
        return [SeatOverview::class, EventStatus::class];
    }

    public function getHeaderWidgetsColumns(): int
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->autoAssignAction(),
            ActionGroup::make([
                $this->reconcileAction(),
                $this->remindersAction(),
                $this->finalMailAction(),
            ])->label('Aktionen')->button()->color('gray')->icon(Heroicon::OutlinedEllipsisVertical),
        ];
    }

    private function autoAssignAction(): Action
    {
        $deadlinePassed = $this->event()->window()->hasEnded();

        return Action::make('autoAssign')
            ->label('Auto-Zuteilung')
            ->icon(Heroicon::OutlinedSparkles)
            ->modalHeading('Auto-Zuteilung: Vorschau')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalContent(fn () => view('filament.partials.auto-assign-preview', [
                'results' => app(AutoAssign::class)->preview($this->event()),
                'deadlinePassed' => $deadlinePassed,
            ]))
            ->modalCancelActionLabel('Schliessen')
            ->modalSubmitActionLabel('Ausführen')
            ->modalSubmitAction($deadlinePassed ? null : false)
            ->action(function () use ($deadlinePassed): void {
                abort_unless($deadlinePassed, 403);

                $results = app(AutoAssign::class)->run($this->event());
                $seated = $results->filter->isSeated();
                $unseated = $results->count() - $seated->count();

                Notification::make()
                    ->title("{$seated->count()} Bestellungen zugeteilt")
                    ->body($unseated > 0 ? "{$unseated} Bestellungen haben keinen Platz bekommen." : null)
                    ->status($unseated > 0 ? 'warning' : 'success')
                    ->persistent()
                    ->send();
            });
    }

    private function reconcileAction(): Action
    {
        return Action::make('reconcile')
            ->label('Abgleich mit Hi.Events')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalHeading('Abgleich mit Hi.Events starten?')
            ->modalDescription('Holt alle Bestellungen und korrigiert Abweichungen. Das kann einen Moment dauern.')
            ->modalSubmitActionLabel('Starten')
            ->action(function (): void {
                Artisan::call('seating:reconcile', ['--event' => $this->event()->slug]);
                $result = StatusLog::get(Reconcile::lastResultKey($this->event()));

                Notification::make()
                    ->title($result['failed'] ?? true ? 'Abgleich fehlgeschlagen' : 'Abgleich abgeschlossen')
                    ->status($result['failed'] ?? true ? 'danger' : 'success')
                    ->send();
            });
    }

    private function remindersAction(): Action
    {
        return Action::make('reminders')
            ->label('Erinnerungen senden')
            ->icon(Heroicon::OutlinedBellAlert)
            ->requiresConfirmation()
            ->modalHeading('Erinnerungen jetzt senden?')
            ->modalDescription('Geht an alle Bestellungen, denen noch Plätze fehlen. Ausgenommen sind Bestellungen, die in den letzten 24 Stunden den Link oder eine Erinnerung bekommen haben. Die geplanten Erinnerungen bleiben bestehen.')
            ->modalSubmitActionLabel('Senden')
            ->disabled(fn (): bool => ! $this->event()->window()->isOpen())
            ->action(function (): void {
                Artisan::call('seating:send-reminders', ['--now' => true, '--event' => $this->event()->slug]);

                Notification::make()
                    ->title((int) trim(Artisan::output()).' Erinnerungen versendet')
                    ->success()
                    ->send();
            });
    }

    private function finalMailAction(): Action
    {
        return Action::make('finalMail')
            ->label('Schlussmail senden')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Schlussmail jetzt senden?')
            ->modalDescription('Geht an alle Bestellungen mit Plätzen, die sie noch nicht bekommen haben. Das lässt sich nicht rückgängig machen.')
            ->modalSubmitActionLabel('Senden')
            ->action(function (): void {
                Artisan::call('seating:send-final', ['--now' => true, '--event' => $this->event()->slug]);

                Notification::make()
                    ->title((int) trim(Artisan::output()).' Schlussmails versendet')
                    ->success()
                    ->send();
            });
    }
}
