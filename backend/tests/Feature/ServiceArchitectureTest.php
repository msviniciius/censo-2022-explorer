<?php

use App\Services\DemographicMetricsService;
use App\Services\MunicipalCensusService;
use App\Services\SectorMetricsService;
use App\Services\StateCensusService;

arch('census composition services depend only on concrete queries and metric services')
    ->expect([MunicipalCensusService::class, StateCensusService::class])
    ->toOnlyUse(['App\Queries', SectorMetricsService::class, DemographicMetricsService::class]);

arch('metric services have no external dependencies')
    ->expect([SectorMetricsService::class, DemographicMetricsService::class])
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


arch('municipal search service delegates only to its concrete query')
    ->expect(App\Services\MunicipalitySearchService::class)
    ->toOnlyUse([App\Queries\MunicipalitySearchQuery::class]);

arch('controllers cannot bypass services to access census data')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'App\Queries', 'Illuminate\Database', 'Illuminate\Contracts\Database',
        'Illuminate\Support\Facades\DB', 'PDO', 'SQLite3', 'DB', 'app', 'resolve',
    ]);

arch('queries do not depend on HTTP or services')
    ->expect('App\Queries')
    ->not->toUse(['App\Http', 'App\Services', 'Illuminate\Http', 'Illuminate\Support\Facades\Response']);

arch('state list service delegates only to its concrete query')
    ->expect(App\Services\StateListService::class)
    ->toOnlyUse([App\Queries\StateListQuery::class]);

arch('state ranking service delegates only to identity and ranking queries')
    ->expect(StateRankingService::class)
    ->toOnlyUse([App\Queries\StateIdentityQuery::class, App\Queries\StateRankingQuery::class]);
