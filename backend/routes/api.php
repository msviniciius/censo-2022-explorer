<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\MunicipalityController;
use App\Http\Controllers\StateController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/municipios', [MunicipalityController::class, 'index']);
Route::get('/municipios/{cd_mun}', [MunicipalityController::class, 'show'])->where('cd_mun', '[0-9]{7}');

Route::get('/ufs', [StateController::class, 'index']);
Route::get('/ufs/{cd_uf}', [StateController::class, 'show'])->where('cd_uf', '[0-9]{2}');
