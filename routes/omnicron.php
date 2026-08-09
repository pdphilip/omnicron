<?php

use Illuminate\Support\Facades\Route;
use PDPhilip\OmniCron\Http\HeartbeatController;
use PDPhilip\OmniCron\Http\VerifySecret;

Route::prefix(config('omnicron.endpoint.path', 'omnicron'))
    ->middleware(array_merge([VerifySecret::class], config('omnicron.endpoint.middleware', [])))
    ->group(function () {
        Route::get('/tick', [HeartbeatController::class, 'tick'])->name('omnicron.tick');
        Route::get('/status', [HeartbeatController::class, 'status'])->name('omnicron.status');
        Route::get('/run/{task}', [HeartbeatController::class, 'run'])->name('omnicron.run');
    });
