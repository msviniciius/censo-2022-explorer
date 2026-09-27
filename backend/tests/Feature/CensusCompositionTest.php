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

it('composes identity and raw totals through the municipal service', function (): void {
    expect(app(MunicipalCensusService::class)->find('0100001'))->toBe([
        'cd_mun' => '0100001', 'nm_mun' => 'Município A', 'cd_uf' => '01', 'nm_uf' => 'UF A',
        'agregados' => ['total_setores' => 3, 'populacao' => 250, 'area_km2' => 6.0],
    ]);
});

it('composes identity and raw totals through the state service', function (): void {
    expect(app(StateCensusService::class)->find('01'))->toBe([
        'cd_uf' => '01', 'nm_uf' => 'UF A',
        'agregados' => ['total_setores' => 6, 'populacao' => 280, 'area_km2' => 20.0],
    ]);
});

it('keeps existing empty territories distinct from missing territories', function (string $service, string $code, string $key): void {
    $result = app($service)->find($code);

    expect($result[$key])->toBe($code)
        ->and($result['agregados'])->toBe(['total_setores' => 0, 'populacao' => null, 'area_km2' => null]);
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
            ->toBe(['total_setores' => 0, 'populacao' => null, 'area_km2' => null]);
    }
})->with(['missing' => ['99'], 'SQL-like input' => ["' OR 1=1 --"]]);
