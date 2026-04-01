<?php

namespace App\Providers\Filament;

use App\Filament\Pages\AuditTrail;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\FormBuilder;
use App\Filament\Pages\WorkflowManager;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\DocTypeRegistryWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors(['primary' => Color::Violet])
            ->brandName('Pascal Platform')
            ->darkMode(true)

            ->pages([
                Dashboard::class,
                AuditTrail::class,
                FormBuilder::class,
                WorkflowManager::class,
            ])
            ->resources([UserResource::class])
            ->widgets([StatsOverviewWidget::class, DocTypeRegistryWidget::class])

            ->userMenuItems([
                MenuItem::make()
                    ->label('API')
                    ->url('/api/v1/mcp/tools')
                    ->icon('heroicon-o-code-bracket')
                    ->openUrlInNewTab(),
            ])

            ->navigationGroups([
                'User Management',
                'System',
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class])
            ->authGuard('pascal');
    }
}
