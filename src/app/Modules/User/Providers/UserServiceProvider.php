<?php

namespace App\Modules\User\Providers;

use App\Core\DocType\DocTypeRegistry;
use App\Modules\User\Controllers\AdminUserController;
use App\Modules\User\Controllers\AuthController;
use App\Modules\User\Controllers\ProfileController;
use App\Modules\User\Controllers\UserDocumentController;
use App\Modules\User\Middleware\AuthenticatePascalUser;
use App\Modules\User\Services\UserAuthService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserAuthService::class);
    }

    public function boot(): void
    {
        // ── 1. Register the "User" DocType ───────────────────────────────────
        DocTypeRegistry::register(
            doctype: 'User',
            controllerClass: UserDocumentController::class,
            options: [
                'module'         => 'User',
                'is_submittable' => false,
                'track_changes'  => true,
            ]
        );

        // ── 2. Register middleware alias ──────────────────────────────────────
        $this->app['router']->aliasMiddleware('pascal.auth', AuthenticatePascalUser::class);

        // ── 3. Register module routes ─────────────────────────────────────────
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        // Public auth routes
        Route::prefix('api/v1/auth')
            ->name('user.auth.')
            ->middleware('api')
            ->group(function () {
                Route::post('register',        [AuthController::class, 'register'])->name('register');
                Route::post('login',           [AuthController::class, 'login'])->name('login');
                Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
                Route::post('reset-password',  [AuthController::class, 'resetPassword'])->name('reset-password');
            });

        // Authenticated user routes
        Route::prefix('api/v1')
            ->name('user.')
            ->middleware(['api', 'pascal.auth'])
            ->group(function () {
                // Auth actions
                Route::prefix('auth')->group(function () {
                    Route::post('logout',          [AuthController::class, 'logout'])->name('logout');
                    Route::post('logout-all',      [AuthController::class, 'logoutAll'])->name('logout-all');
                    Route::get('me',               [AuthController::class, 'me'])->name('me');
                    Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
                });

                // Own profile
                Route::prefix('user')->group(function () {
                    Route::get('profile',              [ProfileController::class, 'show'])->name('profile');
                    Route::put('profile',              [ProfileController::class, 'update'])->name('profile.update');
                    Route::post('avatar',              [ProfileController::class, 'uploadAvatar'])->name('avatar.upload');
                    Route::delete('avatar',            [ProfileController::class, 'deleteAvatar'])->name('avatar.delete');
                    Route::get('login-history',        [ProfileController::class, 'loginHistory'])->name('login-history');
                    Route::get('sessions',             [ProfileController::class, 'sessions'])->name('sessions');
                    Route::delete('sessions/{id}',     [ProfileController::class, 'revokeSession'])->name('sessions.revoke');
                });

                // Admin routes (require 'admin' or 'manager' role)
                Route::prefix('admin')
                    ->middleware('permission:User.admin')
                    ->group(function () {
                        Route::get('users',                   [AdminUserController::class, 'index'])->name('admin.users.index');
                        Route::post('users',                  [AdminUserController::class, 'store'])->name('admin.users.store');
                        Route::get('users/{name}',            [AdminUserController::class, 'show'])->name('admin.users.show');
                        Route::put('users/{name}',            [AdminUserController::class, 'update'])->name('admin.users.update');
                        Route::delete('users/{name}',         [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
                        Route::post('users/{name}/ban',       [AdminUserController::class, 'ban'])->name('admin.users.ban');
                        Route::post('users/{name}/unban',     [AdminUserController::class, 'unban'])->name('admin.users.unban');
                        Route::get('users/{name}/audit-trail',[AdminUserController::class, 'auditTrail'])->name('admin.users.audit');
                    });
            });
    }
}
