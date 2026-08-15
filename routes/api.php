<?php

use App\Http\Controllers\Api\ReniecController;
use App\Http\Controllers\Api\SunatController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::post('/reniec/consultar', [ReniecController::class, 'checkDni'])
        ->name('api.reniec.consultar');

    Route::post('/sunat/consultar', [SunatController::class, 'checkRuc'])
        ->name('api.sunat.consultar');
});
