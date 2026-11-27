<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MaintenancePlanController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\PlanActivationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VesselController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/vessel', [VesselController::class, 'show'])->name('vessel.show');

    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');

    Route::middleware('role:chief_engineer')->group(function (): void {
        Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
        Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    });

    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');

    Route::get('/assets/{asset}/readings', [MeterReadingController::class, 'index'])->name('assets.readings.index');

    Route::post('/assets/{asset}/readings', [MeterReadingController::class, 'store'])
        ->middleware('role:chief_engineer,technician')
        ->name('assets.readings.store');

    Route::get('/plans', [MaintenancePlanController::class, 'index'])->name('plans.index');

    Route::middleware('role:chief_engineer')->group(function (): void {
        Route::get('/plans/create', [MaintenancePlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [MaintenancePlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [MaintenancePlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan}', [MaintenancePlanController::class, 'update'])->name('plans.update');
        Route::post('/plans/{plan}/activate', [PlanActivationController::class, 'store'])->name('plans.activate');
        Route::delete('/plans/{plan}/activate', [PlanActivationController::class, 'destroy'])->name('plans.deactivate');
    });

    Route::get('/plans/{plan}', [MaintenancePlanController::class, 'show'])->name('plans.show');

    Route::get('/work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');

    Route::middleware('role:chief_engineer')->group(function (): void {
        Route::get('/work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
        Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    });

    Route::post('/work-orders/{workOrder}/start', [WorkOrderController::class, 'start'])->name('work-orders.start');
    Route::post('/work-orders/{workOrder}/submit', [WorkOrderController::class, 'submit'])->name('work-orders.submit');
    Route::post('/work-orders/{workOrder}/return', [WorkOrderController::class, 'returnToTechnician'])->name('work-orders.return');
    Route::post('/work-orders/{workOrder}/close', [WorkOrderController::class, 'close'])->name('work-orders.close');

    Route::get('/work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');

    Route::get('/admin/users', [UserController::class, 'index'])
        ->middleware('role:chief_engineer')
        ->name('admin.users.index');
});
