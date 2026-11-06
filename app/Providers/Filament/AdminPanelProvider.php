<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Tenancy\EditEvent;
use App\Filament\Pages\Tenancy\RegisterEvent;
use App\Http\Controllers\Admin\ExportController;
use App\Models\Event;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Sitzplatzwahl')
            ->tenant(Event::class, slugAttribute: 'slug', ownershipRelationship: 'event')
            ->tenantRoutePrefix('event')
            ->tenantRegistration(RegisterEvent::class)
            ->tenantProfile(EditEvent::class)
            ->login()
            // Optional: the login is shared with the info desk on the night.
            ->profile()
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()])
            ->colors([
                'primary' => Color::hex('#a05c50'),
            ])
            ->font('Poppins', provider: LocalFontProvider::class)
            ->darkMode(false)
            ->navigationItems([
                NavigationItem::make('Einstellungen')
                    ->url(fn (): string => EditEvent::getUrl())
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->isActiveWhen(fn (): bool => request()->routeIs(EditEvent::getRouteName()))
                    ->sort(6),
                NavigationItem::make('Warteschlange')
                    ->url('/horizon', shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedQueueList)
                    ->sort(7),
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render("@fonts @vite(['resources/css/admin.css', 'resources/js/admin.js'])"))
            ->authenticatedTenantRoutes(function (): void {
                Route::get('exporte/{type}.{format}', ExportController::class)
                    ->whereIn('format', ['csv', 'html'])
                    ->name('exports');
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persistent: without it, Livewire requests would skip the login check.
            ->authMiddleware([
                Authenticate::class,
            ], isPersistent: true);
    }
}
