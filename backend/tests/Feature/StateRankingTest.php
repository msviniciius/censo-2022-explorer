<?php

use App\Http\Controllers\StateRankingController;
use App\Services\StateCensusService;
use App\Services\StateRankingService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->databasePath = tempnam(sys_get_temp_dir(), 'census-ranking-');
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec(<<<'SQL'
        PRAGMA foreign_keys = ON;
        CREATE TABLE uf (cd_uf TEXT PRIMARY KEY, nm_uf TEXT NOT NULL) WITHOUT ROWID;
        CREATE TABLE municipio (cd_mun TEXT PRIMARY KEY, nm_mun TEXT NOT NULL, cd_uf TEXT NOT NULL REFERENCES uf(cd_uf)) WITHOUT ROWID;
        CREATE TABLE setor (cd_setor TEXT PRIMARY KEY, cd_mun TEXT NOT NULL REFERENCES municipio(cd_mun), situacao TEXT, area_km2 REAL, populacao INTEGER) WITHOUT ROWID;
        CREATE TABLE demografia (cd_setor TEXT PRIMARY KEY REFERENCES setor(cd_setor), moradores INTEGER, homens INTEGER, mulheres INTEGER) WITHOUT ROWID;
        INSERT INTO uf VALUES ('31', 'Minas'), ('35', 'São Paulo'), ('43', 'Rio Grande do Sul'), ('44', 'UF vazia');
        INSERT INTO municipio VALUES
            ('0100001', 'Zeta', '31'), ('0100002', 'Alfa', '31'),
            ('0100003', 'Precisão menor', '31'), ('0100004', 'Precisão maior', '31'),
            ('0100005', 'Setores agregados', '31'), ('0100006', 'Sem setores', '31'),
            ('0100007', 'Área zero', '31'), ('0100008', 'Densidade zero', '31'),
            ('0100009', 'População nula', '31'), ('0100010', 'Área nula', '31'),
            ('0100011', 'Comparador 40', '31'), ('100001', 'Código curto', '31'),
            ('01A0001', 'Código inválido', '31'), ('01000011', 'Código longo', '31'),
            ('0100012', '   ', '31'), ('3500001', 'Outro estado', '35'),
            ('4300001', 'Município válido', '43'), ('.', '', '43'),
            ('44bad', 'Código inválido apenas', '44');
        INSERT INTO setor VALUES
            ('s1', '0100001', 'Urbana', 2, 100), ('s2', '0100002', 'Rural', 4, 200),
            ('s3', '0100003', 'Urbana', 1000, 50001), ('s4', '0100004', 'Rural', 1000, 50004),
            ('s5', '0100005', 'Urbana', 1, 100), ('s6', '0100005', 'Rural', 9, 200),
            ('s7', '0100007', NULL, 0, 5), ('s8', '0100008', 'Rural', 2, 0),
            ('s9', '0100009', 'Urbana', 3, NULL), ('s10', '0100010', 'Rural', NULL, 10),
            ('s11', '0100011', 'Urbana', 1, 40), ('other', '3500001', 'Urbana', 1, 999999),
            ('valid-rs', '4300001', 'Urbana', 1, 10),
            ('dot1', '.', NULL, 3, 0), ('dot2', '.', 'Outra', 7, 0);
        SQL);
    unset($fixture);
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

it('aggregates sectors before density and ranks full precision by density then municipal code', function (): void {
    $rows = app(StateRankingService::class)->forState('31');
    $byCode = collect($rows)->keyBy('cd_mun');

    expect(array_column($rows, 'cd_mun'))->toBe([
        '0100004', '0100003', '0100001', '0100002', '0100011', '0100005',
        '0100008', '0100006', '0100007', '0100009', '0100010',
    ]);
    expect($byCode['0100001']['cd_mun'])->toBe('0100001')
        ->and($byCode['0100001']['densidade_hab_km2'])->toBe(50.0)
        ->and($byCode['0100002']['densidade_hab_km2'])->toBe(50.0)
        ->and($byCode['0100004']['densidade_hab_km2'])->toBe(50.004)
        ->and($byCode['0100003']['densidade_hab_km2'])->toBe(50.001)
        ->and($byCode['0100005']['populacao'])->toBe(300)
        ->and($byCode['0100005']['area_km2'])->toBe(10.0)
        ->and($byCode['0100005']['densidade_hab_km2'])->toBe(30.0)
        ->and($byCode['0100011']['densidade_hab_km2'])->toBe(40.0);
    // Averaging the two sector densities (100 and 200/9) would put 0100005 above 40;
    // aggregating its sums first correctly yields 30 and places it below 0100011.
});

it('keeps null densities last, places zero before them, and preserves no-sector nulls', function (): void {
    $rows = app(StateRankingService::class)->forState('31');
    $byCode = collect($rows)->keyBy('cd_mun');

    expect(array_slice(array_column($rows, 'cd_mun'), 6))->toBe([
        '0100008', '0100006', '0100007', '0100009', '0100010',
    ]);
    foreach (['0100006', '0100007', '0100009', '0100010'] as $code) {
        expect($byCode[$code]['densidade_hab_km2'])->toBeNull();
    }
    expect($byCode['0100006']['populacao'])->toBeNull()
        ->and($byCode['0100006']['area_km2'])->toBeNull()
        ->and($byCode['0100008']['densidade_hab_km2'])->toBe(0.0);
});

it('excludes ineligible municipalities and sectors from the selected state ranking', function (): void {
    $rows = app(StateRankingService::class)->forState('31');
    $codes = array_column($rows, 'cd_mun');

    expect($codes)->not->toContain('100001', '01A0001', '01000011', '0100012', '3500001', '.', '4300001')
        ->and($codes)->toHaveCount(11);
});

it('distinguishes an existing UF without eligible municipalities from a missing UF', function (): void {
    expect(app(StateRankingService::class)->forState('44'))->toBe([])
        ->and(app(StateRankingService::class)->forState('99'))->toBeNull();
});

it('preserves the anomalous dot sectors in state totals while excluding the dot municipality from ranking', function (): void {
    $state = app(StateCensusService::class)->find('43');
    $ranking = app(StateRankingService::class)->forState('43');

    expect(array_column($ranking, 'cd_mun'))->toBe(['4300001'])
        ->and($state['setores_sem_municipio_identificavel'])->toBe(2)
        ->and($state['agregados']['total_setores'])->toBe(3)
        ->and($state['agregados']['populacao'])->toBe(10)
        ->and($state['agregados']['area_km2'])->toBe(11.0);
});

it('uses a constant two-query service path and one ranking query for any number of eligible municipalities', function (): void {
    $connection = DB::connection('census');
    $connection->enableQueryLog();

    expect(app(StateRankingService::class)->forState('31'))->toHaveCount(11);
    $manyQueries = $connection->getQueryLog();
    $connection->flushQueryLog();
    expect(app(StateRankingService::class)->forState('44'))->toBe([]);
    $emptyQueries = $connection->getQueryLog();

    expect($manyQueries)->toHaveCount(2)
        ->and($emptyQueries)->toHaveCount(2)
        ->and(strtolower($manyQueries[1]['query']))->toContain('row_number()', 'group by s.cd_mun')
        ->not->toContain('avg(', 'limit', 'offset');
});

it('avoids the ranking query for an unknown UF', function (): void {
    $connection = DB::connection('census');
    $connection->enableQueryLog();

    expect(app(StateRankingService::class)->forState('99'))->toBeNull()
        ->and($connection->getQueryLog())->toHaveCount(1);
});

it('keeps the isolated controller ready for 4.3 and rejects malformed or unknown codes with 404', function (): void {
    $controller = app(StateRankingController::class);
    $service = app(StateRankingService::class);

    expect($controller('31', $service))->toHaveCount(11);
    foreach (['4', '043', '４３'] as $code) {
        expect(fn () => $controller($code, $service))->toThrow(HttpException::class);
    }
    expect(fn () => $controller('99', $service))->toThrow(HttpException::class);
});

it('does not publish the ranking endpoint before the paginated contract in 4.3', function (): void {
    $this->get('/api/ufs/31/municipios')->assertNotFound();
});
