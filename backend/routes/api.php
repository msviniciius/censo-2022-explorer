<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\MunicipalityController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/municipios', [MunicipalityController::class, 'index']);
Route::get('/municipios/{cd_mun}', [MunicipalityController::class, 'show'])->where('cd_mun', '[0-9]{7}');
