<?php

namespace App\Filament\Widgets;

use App\Core\DocType\DocTypeRegistry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

// ── Stats Overview ────────────────────────────────────────────────────────────

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalUsers   = DB::table('pascal_users')->whereNull('deleted_at')->count();
        $activeUsers  = DB::table('pascal_users')->where('status', 'Active')->whereNull('deleted_at')->count();
        $adminUsers   = DB::table('pascal_users')->where('role', 'admin')->whereNull('deleted_at')->count();
        $bannedUsers  = DB::table('pascal_users')->where('status', 'Banned')->whereNull('deleted_at')->count();

        $loginsToday  = DB::table('pascal_login_histories')
            ->where('status', 'success')
            ->whereDate('logged_in_at', today())
            ->count();

        $auditToday   = DB::table('pascal_audit_logs')
            ->whereDate('created_at', today())
            ->count();

        return [
            Stat::make('Total Users', $totalUsers)
                ->description("{$activeUsers} active")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('violet')
                ->icon('heroicon-o-users'),

            Stat::make('Admins', $adminUsers)
                ->description("{$bannedUsers} banned")
                ->color($bannedUsers > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-shield-check'),

            Stat::make('Logins Today', $loginsToday)
                ->description('Successful sessions')
                ->color('success')
                ->icon('heroicon-o-arrow-right-on-rectangle'),

            Stat::make('Audit Events Today', $auditToday)
                ->description('DocType changes recorded')
                ->color('gray')
                ->icon('heroicon-o-clock'),
        ];
    }
}


// ── DocType Registry Widget ───────────────────────────────────────────────────

class DocTypeRegistryWidget extends Widget
{
    protected static ?int $sort = 2;
    protected static string $view = 'filament.widgets.doctype-registry';
    protected int | string | array $columnSpan = 'full';

    public function getRegisteredDocTypes(): array
    {
        $result = [];
        foreach (DocTypeRegistry::allSchemas() as $name => $schema) {
            $table = $schema->getTable();
            $count = 0;
            try {
                $count = DB::table($table)->count();
            } catch (\Exception) {}

            $result[] = [
                'name'           => $name,
                'module'         => $schema->module,
                'table'          => $table,
                'is_submittable' => $schema->isSubmittable,
                'track_changes'  => $schema->trackChanges,
                'count'          => $count,
            ];
        }
        return $result;
    }
}
