<?php

namespace App\Filament\Pages;

use App\Console\Commands\Reconcile;
use App\Services\AutoAssign;
use App\Support\SelectionWindow;
use App\Support\StatusLog;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Übersicht';

    protected static ?string $navigationLabel = 'Übersicht';

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
        $deadlinePassed = app(SelectionWindow::class)->hasEnded();

        return Action::make('autoAssign')
            ->label('Auto-Zuteilung')
            ->icon(Heroicon::OutlinedSparkles)
            ->modalHeading('Auto-Zuteilung: Vorschau')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalContent(fn () => view('filament.partials.auto-assign-preview', [
                'results' => app(AutoAssign::class)->preview(),
                'deadlinePassed' => $deadlinePassed,
            ]))
            ->modalCancelActionLabel('Schliessen')
            ->modalSubmitActionLabel('Ausführen')
            ->modalSubmitAction($deadlinePassed ? null : false)
            ->action(function () use ($deadlinePassed): void {
                abort_unless($deadlinePassed, 403);

                $results = app(AutoAssign::class)->run();
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
                Artisan::call('seating:reconcile');
                $result = StatusLog::get(Reconcile::LAST_RESULT_KEY);

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
            ->disabled(fn (): bool => ! app(SelectionWindow::class)->isOpen())
            ->action(function (): void {
                Artisan::call('seating:send-reminders', ['--now' => true]);

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
                Artisan::call('seating:send-final', ['--now' => true]);

                Notification::make()
                    ->title((int) trim(Artisan::output()).' Schlussmails versendet')
                    ->success()
                    ->send();
            });
    }
}
