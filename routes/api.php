<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InvoiceActionController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\InvoicePdfController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectInvoiceController;
use App\Http\Controllers\Api\V1\ProjectMilestoneController;
use App\Http\Controllers\Api\V1\ProjectTaskController;
use App\Http\Controllers\Api\V1\ProjectTimelineController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', MeController::class)->name('me');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('users', UserController::class)
            ->only(['index', 'store', 'update']);

        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

        Route::post('projects/{project}/invoices', [ProjectInvoiceController::class, 'store'])
            ->name('projects.invoices.store');
        // Declared before the resource so "status-counts" is not read as an invoice id.
        Route::get('invoices/status-counts', [InvoiceController::class, 'statusCounts'])->name('invoices.status-counts');
        Route::apiResource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/send', [InvoiceActionController::class, 'send'])->name('invoices.send');
        Route::post('invoices/{invoice}/cancel', [InvoiceActionController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
        Route::get('invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])->name('invoices.pdf');

        Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
        Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::apiResource('clients', ClientController::class);
        // Declared before the resource so "status-counts" is not read as a project id.
        Route::get('projects/status-counts', [ProjectController::class, 'statusCounts'])->name('projects.status-counts');
        Route::apiResource('projects', ProjectController::class);

        Route::get('projects/{project}/timeline', [ProjectTimelineController::class, 'show'])
            ->name('projects.timeline');
        Route::apiResource('projects.tasks', ProjectTaskController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->scoped(['task' => 'id']);
        Route::patch('projects/{project}/tasks/{task}/schedule', [ProjectTaskController::class, 'schedule'])
            ->scopeBindings()
            ->name('projects.tasks.schedule');
        Route::apiResource('projects.milestones', ProjectMilestoneController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->scoped(['milestone' => 'id']);
    });
});
