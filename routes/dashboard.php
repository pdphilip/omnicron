<?php

use Illuminate\Support\Facades\Route;
use PDPhilip\OmniCron\Http\AuthorizeDashboard;
use PDPhilip\OmniCron\Http\DashboardController;

Route::prefix(config('omnicron.dashboard.path', 'omnicron'))
    ->middleware(array_merge(
        config('omnicron.dashboard.middleware', ['web']),
        [AuthorizeDashboard::class],
    ))
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('omnicron.dashboard');
        Route::get('/api/overview', [DashboardController::class, 'overview'])->name('omnicron.dashboard.overview');
        Route::get('/api/runs', [DashboardController::class, 'runs'])->name('omnicron.dashboard.runs');
        Route::get('/api/queue', [DashboardController::class, 'queue'])->name('omnicron.dashboard.queue');
        Route::post('/api/run/{task}', [DashboardController::class, 'run'])->name('omnicron.dashboard.run');
        Route::post('/api/job/{task}', [DashboardController::class, 'updateJob'])->name('omnicron.dashboard.job');
    });
