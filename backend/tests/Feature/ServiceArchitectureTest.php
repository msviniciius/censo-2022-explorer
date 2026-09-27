<?php

use App\Services\MunicipalCensusService;
use App\Services\StateCensusService;

arch('census composition services depend only on concrete queries')
    ->expect([MunicipalCensusService::class, StateCensusService::class])
    ->toOnlyUse('App\Queries');

arch('services cannot access database drivers, builders, facades or HTTP')
    ->expect('App\Services')
    ->not->toUse([
        'Illuminate\Database',
        'Illuminate\Contracts\Database',
        'Illuminate\Support\Facades',
        'Illuminate\Http',
        'PDO',
        'SQLite3',
        'DB',
        'app',
        'resolve',
    ]);
