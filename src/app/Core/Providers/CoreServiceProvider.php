<?php

namespace App\Core\Providers;

use App\Core\FormBuilder\Services\FormBuilderService;
use App\Core\Services\AuditService;
use App\Core\Services\DocumentService;
use App\Core\Services\PermissionService;
use App\Core\Workflow\Services\WorkflowService;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditService::class);
        $this->app->singleton(PermissionService::class);
        $this->app->singleton(DocumentService::class);
        $this->app->singleton(FormBuilderService::class);
        $this->app->singleton(WorkflowService::class);

        $this->mergeConfigFrom(__DIR__ . '/../../../../config/pascal.php', 'pascal');
    }

    public function boot(): void
    {
        // Boot all DB-persisted DocTypes into the in-memory registry.
        // Runs on every request so custom DocTypes from Form Builder are always available.
        $this->app->booted(function () {
            try {
                $this->app->make(FormBuilderService::class)->bootAllFromDatabase();
            } catch (\Throwable) {
                // Silent during fresh migration
            }
        });
    }
}
