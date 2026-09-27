<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminTenantController;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| These routes are for the tenant management admin panel.
| Completely separate from the main application.
|
*/

// Admin Guest Routes
Route::middleware('admin.guest')->group(function () {
    Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');
});

// Admin Authenticated Routes
Route::middleware('admin.auth')->group(function () {
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

    // Tenant Management — requires manage-tenants ability (super_admin + admin)
    Route::prefix('tenants')->name('tenants.')->middleware('admin.role:manage-tenants')->group(function () {
        Route::get('/', [AdminTenantController::class, 'index'])->name('index');
        Route::get('/list', [AdminTenantController::class, 'list'])->name('list');
        Route::get('/{tenant}', [AdminTenantController::class, 'show'])->name('show');
        Route::put('/{tenant}/subscription', [AdminTenantController::class, 'updateSubscription'])->name('update-subscription');
        Route::patch('/{tenant}/extend', [AdminTenantController::class, 'extendSubscription'])->name('extend-subscription');
        Route::patch('/{tenant}/toggle-status', [AdminTenantController::class, 'toggleStatus'])->name('toggle-status');
        Route::delete('/{tenant}/subscription', [AdminTenantController::class, 'cancelSubscription'])->name('cancel-subscription');
    });

    // Admin Users Management — requires manage-admin-users ability (super_admin only)
    Route::prefix('users')->name('users.')->middleware('admin.role:manage-admin-users')->group(function () {
        Route::get('/', [AdminUserController::class, 'index'])->name('index');
        Route::get('/create', [AdminUserController::class, 'create'])->name('create');
        Route::post('/', [AdminUserController::class, 'store'])->name('store');
        Route::get('/{adminUser}/edit', [AdminUserController::class, 'edit'])->name('edit');
        Route::put('/{adminUser}', [AdminUserController::class, 'update'])->name('update');
        Route::patch('/{adminUser}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('toggle-status');
        Route::delete('/{adminUser}', [AdminUserController::class, 'destroy'])->name('destroy');
    });
});
