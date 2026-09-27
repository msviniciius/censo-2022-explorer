<?php

use App\Services\MunicipalCensusService;
use App\Services\SectorMetricsService;
use App\Services\StateCensusService;

arch('census composition services depend only on concrete queries and sector metrics')
    ->expect([MunicipalCensusService::class, StateCensusService::class])
    ->toOnlyUse(['App\Queries', SectorMetricsService::class]);

arch('sector metrics have no external dependencies')
    ->expect(SectorMetricsService::class)
    ->toUseNothing();

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
