<?php

use App\Queries\MunicipalityIdentityQuery;
use App\Queries\SectorAggregationQuery;
use App\Queries\StateIdentityQuery;
use App\Services\MunicipalCensusService;
use App\Services\StateCensusService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->databasePath = tempnam(sys_get_temp_dir(), 'census-composition-');
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec(<<<'SQL'
        PRAGMA foreign_keys = ON;
        CREATE TABLE uf (cd_uf TEXT PRIMARY KEY, nm_uf TEXT NOT NULL) WITHOUT ROWID;
        CREATE TABLE municipio (cd_mun TEXT PRIMARY KEY, nm_mun TEXT NOT NULL, cd_uf TEXT NOT NULL REFERENCES uf(cd_uf)) WITHOUT ROWID;
        CREATE TABLE setor (cd_setor TEXT PRIMARY KEY, cd_mun TEXT NOT NULL REFERENCES municipio(cd_mun), situacao TEXT, area_km2 REAL, populacao INTEGER) WITHOUT ROWID;
        CREATE TABLE demografia (cd_setor TEXT PRIMARY KEY REFERENCES setor(cd_setor), moradores INTEGER, homens INTEGER, mulheres INTEGER) WITHOUT ROWID;

        INSERT INTO uf VALUES ('01', 'UF A'), ('02', 'UF B'), ('03', 'UF sem setores'), ('04', 'UF sem municípios');
        INSERT INTO municipio VALUES
            ('0100001', 'Município A', '01'), ('0100002', 'Município B', '01'),
            ('0100003', 'Município vazio', '01'), ('0200001', 'Outra UF', '02'),
            ('0300001', 'Município vazio em UF vazia', '03'), ('.', '', '01');
        INSERT INTO setor VALUES
            ('010000100000001', '0100001', 'Urbana', 2.5, 100),
            ('010000100000002', '0100001', 'Rural', 2.5, 100),
            ('010000100000003', '0100001', NULL, 1.0, 50),
            ('010000200000001', '0100002', 'Urbana', 4.0, 30),
            ('020000100000001', '0200001', 'Urbana', 50.0, 900),
            ('010000000000001', '.', NULL, 3.0, 0),
            ('010000000000002', '.', NULL, 7.0, 0);
        INSERT INTO demografia VALUES
            ('010000100000001', 100, 40, 60),
            ('010000100000002', 100, 45, 55),
            ('010000200000001', 30, 10, 20),
            ('020000100000001', 900, 400, 500);
        INSERT INTO uf VALUES ('05', 'UF de casos de métricas');
        INSERT INTO municipio VALUES
            ('0500001', 'Densidade pelos totais', '05'), ('0500002', 'Área zero', '05'),
            ('0500003', 'População desconhecida', '05'), ('0500004', 'Área desconhecida', '05'),
            ('0500005', 'Valores parcialmente conhecidos', '05'), ('0500006', 'Situações não classificadas', '05'),
            ('0500007', 'População zero', '05'), ('0500008', 'Populações desiguais', '05'),
            ('0500009', 'Todos os valores desconhecidos', '05');
        INSERT INTO setor VALUES
            ('050000100000001', '0500001', 'Urbana', 1.0, 100),
            ('050000100000002', '0500001', 'Rural', 9.0, 200),
            ('050000200000001', '0500002', 'Urbana', 0.0, 100),
            ('050000300000001', '0500003', 'Urbana', 2.0, NULL),
            ('050000400000001', '0500004', 'Rural', NULL, 100),
            ('050000500000001', '0500005', 'Urbana', NULL, 10),
            ('050000500000002', '0500005', 'Rural', 3.0, NULL),
            ('050000500000003', '0500005', NULL, 2.0, 20),
            ('050000600000001', '0500006', NULL, 1.0, 10),
            ('050000600000002', '0500006', 'Suburbana', 1.0, 10),
            ('050000700000001', '0500007', 'Urbana', 1.0, 0),
            ('050000700000002', '0500007', 'Urbana', 1.0, 0),
            ('050000700000003', '0500007', 'Rural', 1.0, 0),
            ('050000700000004', '0500007', 'Rural', 1.0, 0),
            ('050000800000001', '0500008', 'Urbana', 1.0, 900),
            ('050000800000002', '0500008', 'Rural', 9.0, 100),
            ('050000900000001', '0500009', 'Urbana', NULL, NULL),
            ('050000900000002', '0500009', 'Rural', NULL, NULL);
        SQL);
    $fixture = null;

    $this->fixtureHash = hash_file('sha256', $this->databasePath);
    config(['database.connections.census.database' => $this->databasePath]);
    DB::purge('census');
});

afterEach(function (): void {
    DB::purge('census');
    $finalHash = hash_file('sha256', $this->databasePath);
    unlink($this->databasePath);

    expect($finalHash)->toBe($this->fixtureHash);
});

it('sums each sector once including equal values and a populated sector without demography', function (): void {
    $totals = app(SectorAggregationQuery::class)->forMunicipality('0100001');

    expect($totals['total_setores'])->toBe(3)
        ->and($totals['populacao'])->toBe(250)
        ->and($totals['area_km2'])->toEqualWithDelta(6.0, 1e-6);
});

it('aggregates the whole UF including unnamed municipality sectors without mixing UFs', function (): void {
    $query = app(SectorAggregationQuery::class);
    $totals = $query->forState('01');

    expect($totals['total_setores'])->toBe(6)
        ->and($totals['populacao'])->toBe(280)
        ->and($totals['area_km2'])->toEqualWithDelta(20.0, 1e-6)
        ->and($query->forState('02'))->toBe([
            'total_setores' => 1, 'populacao' => 900, 'area_km2' => 50.0,
            'urban' => 1, 'rural' => 0, 'unclassified' => 0,
        ]);
});

it('reads municipal identity with textual codes independently of sectors', function (): void {
    expect(app(MunicipalityIdentityQuery::class)->find('0100003'))->toBe([
        'cd_mun' => '0100003', 'nm_mun' => 'Município vazio', 'cd_uf' => '01', 'nm_uf' => 'UF A',
    ]);
});

it('reads UF identity without sectors or even municipalities', function (string $code, string $name): void {
    expect(app(StateIdentityQuery::class)->find($code))->toBe(['cd_uf' => $code, 'nm_uf' => $name]);
})->with([
    'municipality without sectors' => ['03', 'UF sem setores'],
    'no municipalities' => ['04', 'UF sem municípios'],
]);

it('composes identity and sector metrics through the municipal service', function (): void {
    expect(app(MunicipalCensusService::class)->find('0100001'))->toEqualWithDelta([
        'cd_mun' => '0100001', 'nm_mun' => 'Município A', 'cd_uf' => '01', 'nm_uf' => 'UF A',
        'agregados' => [
            'total_setores' => 3, 'populacao' => 250, 'area_km2' => 6.0,
            'densidade_hab_km2' => 250 / 6.0,
            'distribuicao' => [
                'total' => 3, 'urban' => 1, 'rural' => 1, 'unclassified' => 1,
                'urban_pct' => 100 / 3, 'rural_pct' => 100 / 3, 'unclassified_pct' => 100 / 3,
            ],
        ],
    ], 1e-6);
});

it('composes identity and sector metrics through the state service', function (): void {
    expect(app(StateCensusService::class)->find('01'))->toEqualWithDelta([
        'cd_uf' => '01', 'nm_uf' => 'UF A',
        'agregados' => [
            'total_setores' => 6, 'populacao' => 280, 'area_km2' => 20.0,
            'densidade_hab_km2' => 14.0,
            'distribuicao' => [
                'total' => 6, 'urban' => 2, 'rural' => 1, 'unclassified' => 3,
                'urban_pct' => 100 / 3, 'rural_pct' => 100 / 6, 'unclassified_pct' => 50.0,
            ],
        ],
    ], 1e-6);
});

it('keeps existing empty territories distinct from missing territories', function (string $service, string $code, string $key): void {
    $result = app($service)->find($code);

    expect($result[$key])->toBe($code)
        ->and($result['agregados'])->toBe([
            'total_setores' => 0, 'populacao' => null, 'area_km2' => null, 'densidade_hab_km2' => null,
            'distribuicao' => [
                'total' => 0, 'urban' => 0, 'rural' => 0, 'unclassified' => 0,
                'urban_pct' => null, 'rural_pct' => null, 'unclassified_pct' => null,
            ],
        ]);
})->with([
    'municipality' => [MunicipalCensusService::class, '0100003', 'cd_mun'],
    'UF without sectors' => [StateCensusService::class, '03', 'cd_uf'],
    'UF without municipalities' => [StateCensusService::class, '04', 'cd_uf'],
]);

it('returns no identity for missing territories or SQL-like codes', function (string $code): void {
    expect(app(MunicipalityIdentityQuery::class)->find($code))->toBeNull()
        ->and(app(StateIdentityQuery::class)->find($code))->toBeNull()
        ->and(app(MunicipalCensusService::class)->find($code))->toBeNull()
        ->and(app(StateCensusService::class)->find($code))->toBeNull();

    foreach (['forMunicipality', 'forState'] as $method) {
        expect(app(SectorAggregationQuery::class)->$method($code))
            ->toBe([
                'total_setores' => 0, 'populacao' => null, 'area_km2' => null,
                'urban' => 0, 'rural' => 0, 'unclassified' => 0,
            ]);
    }
})->with(['missing' => ['99'], 'SQL-like input' => ["' OR 1=1 --"]]);


it('calculates independent sums and density from totals without rounding or imputing nulls', function (
    string $code, ?int $population, ?float $area, ?float $density,
): void {
    $metrics = app(MunicipalCensusService::class)->find($code)['agregados'];

    expect($metrics['populacao'])->toBe($population);
    if ($area === null) {
        expect($metrics['area_km2'])->toBeNull();
    } else {
        expect($metrics['area_km2'])->toEqualWithDelta($area, 1e-6);
    }
    if ($density === null) {
        expect($metrics['densidade_hab_km2'])->toBeNull();
    } else {
        expect($metrics['densidade_hab_km2'])->toEqualWithDelta($density, 1e-6);
    }
})->with([
    'equal values in distinct sectors and missing demography' => ['0100001', 250, 6.0, 41.666666666666664],
    'other municipality in the same UF' => ['0100002', 30, 4.0, 7.5],
    'other UF' => ['0200001', 900, 50.0, 18.0],
    '300 inhabitants over 10 km2 rather than mean sector densities' => ['0500001', 300, 10.0, 30.0],
    'zero total area' => ['0500002', 100, 0.0, null],
    'all population values null' => ['0500003', null, 2.0, null],
    'all area values null' => ['0500004', 100, null, null],
    'partial nulls summed independently' => ['0500005', 30, 5.0, 6.0],
    'known zero population' => ['0500007', 0, 4.0, 0.0],
    'all population and area values null' => ['0500009', null, null, null],
]);

it('partitions every sector once and calculates percentages by sector count', function (
    string $code, array $counts, array $percentages,
): void {
    $metrics = app(MunicipalCensusService::class)->find($code)['agregados'];
    $distribution = $metrics['distribuicao'];

    expect($metrics['total_setores'])->toBe(array_sum($counts))
        ->and($distribution['total'])->toBe($metrics['total_setores'])
        ->and($distribution['urban'] + $distribution['rural'] + $distribution['unclassified'])
        ->toBe($distribution['total']);

    foreach (['urban', 'rural', 'unclassified'] as $index => $category) {
        expect($distribution[$category])->toBe($counts[$index])
            ->and($distribution[$category.'_pct'])->toEqualWithDelta($percentages[$index], 1e-6);
    }
})->with([
    'urban rural and null, with unequal populations' => ['0100001', [1, 1, 1], [33.333333333333336, 33.333333333333336, 33.333333333333336]],
    'absent categories in another municipality' => ['0100002', [1, 0, 0], [100.0, 0.0, 0.0]],
    'null and unknown situation are never rural' => ['0500006', [0, 0, 2], [0.0, 0.0, 100.0]],
    'population null does not remove the sector' => ['0500003', [1, 0, 0], [100.0, 0.0, 0.0]],
    'area null does not remove the sector' => ['0500004', [0, 1, 0], [0.0, 100.0, 0.0]],
    'zero population does not change distribution' => ['0500007', [2, 2, 0], [50.0, 50.0, 0.0]],
    '900 urban inhabitants and 100 rural inhabitants' => ['0500008', [1, 1, 0], [50.0, 50.0, 0.0]],
    'all measures null still count the sectors' => ['0500009', [1, 1, 0], [50.0, 50.0, 0.0]],
]);

it('uses statewide totals for density and distribution rather than averaging municipalities', function (): void {
    $metrics = app(StateCensusService::class)->find('01')['agregados'];
    $distribution = $metrics['distribuicao'];

    // Municipality densities: 250/6, 30/4 and 0/10. The statewide ratio is 280/20.
    expect($metrics['densidade_hab_km2'])->toEqualWithDelta(14.0, 1e-6)
        ->and($distribution['total'])->toBe(6)
        ->and($distribution['urban'])->toBe(2)
        ->and($distribution['rural'])->toBe(1)
        ->and($distribution['unclassified'])->toBe(3)
        ->and($distribution['urban'] + $distribution['rural'] + $distribution['unclassified'])
        ->toBe($metrics['total_setores'])
        ->and($distribution['urban_pct'])->toEqualWithDelta(33.333333333333336, 1e-6)
        ->and($distribution['rural_pct'])->toEqualWithDelta(16.666666666666668, 1e-6)
        ->and($distribution['unclassified_pct'])->toEqualWithDelta(50.0, 1e-6);

    $otherState = app(StateCensusService::class)->find('02')['agregados'];
    expect($otherState['total_setores'])->toBe(1)
        ->and($otherState['populacao'])->toBe(900)
        ->and($otherState['area_km2'])->toEqualWithDelta(50.0, 1e-6)
        ->and($otherState['densidade_hab_km2'])->toEqualWithDelta(18.0, 1e-6)
        ->and($otherState['distribuicao'])->toBe([
            'total' => 1, 'urban' => 1, 'rural' => 0, 'unclassified' => 0,
            'urban_pct' => 100.0, 'rural_pct' => 0.0, 'unclassified_pct' => 0.0,
        ]);
});
