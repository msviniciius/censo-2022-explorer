<?php

use App\Services\StateCensusService;
use App\Services\StateRankingService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->databasePath = tempnam(sys_get_temp_dir(), 'census-ranking-');
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec(<<<'SQL'
        PRAGMA foreign_keys = ON;
        CREATE TABLE uf (cd_uf TEXT PRIMARY KEY, nm_uf TEXT NOT NULL) WITHOUT ROWID;
        CREATE TABLE municipio (cd_mun TEXT PRIMARY KEY, nm_mun TEXT NOT NULL, cd_uf TEXT NOT NULL REFERENCES uf(cd_uf)) WITHOUT ROWID;
        CREATE TABLE setor (cd_setor TEXT PRIMARY KEY, cd_mun TEXT NOT NULL REFERENCES municipio(cd_mun), situacao TEXT, area_km2 REAL, populacao INTEGER) WITHOUT ROWID;
        CREATE TABLE demografia (cd_setor TEXT PRIMARY KEY REFERENCES setor(cd_setor), moradores INTEGER, homens INTEGER, mulheres INTEGER) WITHOUT ROWID;
        INSERT INTO uf VALUES ('31', 'Minas'), ('35', 'São Paulo'), ('43', 'Rio Grande do Sul'), ('44', 'UF vazia'), ('55', 'UF de paginação');
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
    $municipality = $fixture->prepare("INSERT INTO municipio (cd_mun, nm_mun, cd_uf) VALUES (?, ?, '55')");
    $sector = $fixture->prepare("INSERT INTO setor (cd_setor, cd_mun, situacao, area_km2, populacao) VALUES (?, ?, 'Urbana', 1, ?)");
    for ($number = 1; $number <= 55; $number++) {
        $code = '55'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
        if ($number <= 24) {
            $name = 'Maior '.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $population = 1000 - $number;
        } elseif ($number <= 28) {
            $name = [25 => 'Zulu', 26 => 'Alfa', 27 => 'Yankee', 28 => 'Beta'][$number];
            $population = 50;
        } elseif ($number <= 50) {
            $name = 'Menor '.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $population = 51 - $number;
        } else {
            $name = 'Sem setores '.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $population = null;
        }
        $municipality->execute([$code, $name]);
        if ($population !== null) {
            $sector->execute(['s-'.$code, $code, $population]);
        }
    }
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
    $rows = app(StateRankingService::class)->forState('31', 1, 25)['items'];
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
    $rows = app(StateRankingService::class)->forState('31', 1, 25)['items'];
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
    $rows = app(StateRankingService::class)->forState('31', 1, 25)['items'];
    $codes = array_column($rows, 'cd_mun');

    expect($codes)->not->toContain('100001', '01A0001', '01000011', '0100012', '3500001', '.', '4300001')
        ->and($codes)->toHaveCount(11);
});

it('distinguishes an existing UF without eligible municipalities from a missing UF', function (): void {
    expect(app(StateRankingService::class)->forState('44', 1, 25)['items'])->toBe([])
        ->and(app(StateRankingService::class)->forState('99', 1, 25))->toBeNull();
});

it('preserves the anomalous dot sectors in state totals while excluding the dot municipality from ranking', function (): void {
    $state = app(StateCensusService::class)->find('43');
    $ranking = app(StateRankingService::class)->forState('43', 1, 25)['items'];

    expect(array_column($ranking, 'cd_mun'))->toBe(['4300001'])
        ->and($state['setores_sem_municipio_identificavel'])->toBe(2)
        ->and($state['agregados']['total_setores'])->toBe(3)
        ->and($state['agregados']['populacao'])->toBe(10)
        ->and($state['agregados']['area_km2'])->toBe(11.0);
});

it('uses a constant two-query service path and one ranking query for any number of eligible municipalities', function (): void {
    $connection = DB::connection('census');
    $connection->enableQueryLog();

    expect(app(StateRankingService::class)->forState('31', 1, 25)['items'])->toHaveCount(11);
    $manyQueries = $connection->getQueryLog();
    $connection->flushQueryLog();
    expect(app(StateRankingService::class)->forState('44', 1, 25)['items'])->toBe([]);
    $emptyQueries = $connection->getQueryLog();
    $connection->flushQueryLog();
    $this->get('/api/ufs/55/municipios?page=4')->assertOk()->assertJsonCount(0, 'data');
    $beyondQueries = $connection->getQueryLog();

    expect($manyQueries)->toHaveCount(2)
        ->and($emptyQueries)->toHaveCount(2)
        ->and($beyondQueries)->toHaveCount(2)
        ->and(strtolower($manyQueries[1]['query']))->toContain('row_number()', 'group by s.cd_mun', 'limit ? offset ?')
        ->not->toContain('avg(');
    $rankingSql = strtolower($manyQueries[1]['query']);
    expect(strpos($rankingSql, 'row_number()'))->toBeLessThan(strpos($rankingSql, 'limit ? offset ?'));
});

it('avoids the ranking query for an unknown UF', function (): void {
    $connection = DB::connection('census');
    $connection->enableQueryLog();

    expect(app(StateRankingService::class)->forState('99', 1, 25))->toBeNull()
        ->and($connection->getQueryLog())->toHaveCount(1);
});

it('serves default and explicit pages with exact pagination metadata', function (): void {
    $default = $this->get('/api/ufs/31/municipios')->assertOk()->json();
    expect(array_keys($default))->toBe(['data', 'meta'])
        ->and($default['meta'])->toBe(['current_page' => 1, 'per_page' => 25, 'total' => 11, 'last_page' => 1])
        ->and(array_column($default['data'], 'posicao'))->toBe(range(1, 11));

    $firstExplicit = $this->get('/api/ufs/31/municipios?page=1')->assertOk()->json();
    expect($firstExplicit['meta'])->toBe(['current_page' => 1, 'per_page' => 25, 'total' => 11, 'last_page' => 1]);
    $second = $this->get('/api/ufs/31/municipios?page=2&per_page=25')->assertOk()->json();
    expect($second)->toBe(['data' => [], 'meta' => ['current_page' => 2, 'per_page' => 25, 'total' => 11, 'last_page' => 1]]);

    foreach ([25, 50, 100] as $perPage) {
        $this->get('/api/ufs/31/municipios?per_page='.$perPage)->assertOk()
            ->assertJsonPath('meta.per_page', $perPage);
    }
});

it('returns valid empty pagination metadata for an existing UF without eligible municipalities', function (): void {
    $this->get('/api/ufs/44/municipios?page=2&per_page=50')->assertOk()->assertExactJson([
        'data' => [],
        'meta' => ['current_page' => 2, 'per_page' => 50, 'total' => 0, 'last_page' => 1],
    ]);
});

it('paginates 55 municipalities without repetition or omission and preserves global positions', function (): void {
    $pages = [];
    foreach ([1, 2, 3] as $page) {
        $pages[] = $this->get('/api/ufs/55/municipios?page='.$page)->assertOk()->json();
    }
    expect(array_map(fn ($page) => count($page['data']), $pages))->toBe([25, 25, 5]);
    foreach ($pages as $index => $page) {
        expect($page['meta'])->toBe(['current_page' => $index + 1, 'per_page' => 25, 'total' => 55, 'last_page' => 3]);
        expect(array_column($page['data'], 'posicao'))->toBe(range($index * 25 + 1, $index * 25 + count($page['data'])));
    }
    $codes = array_merge(...array_map(fn ($page) => array_column($page['data'], 'cd_mun'), $pages));
    $expectedCodes = array_map(fn (int $number): string => '55'.str_pad((string) $number, 5, '0', STR_PAD_LEFT), range(1, 55));
    expect($codes)->toBe($expectedCodes)->and(array_unique($codes))->toHaveCount(55);
    $all = array_merge(...array_map(fn ($page) => $page['data'], $pages));
    expect(array_column(array_slice($all, 23, 5), 'cd_mun'))->toBe(['5500024', '5500025', '5500026', '5500027', '5500028'])
        ->and(array_column(array_slice($all, 48, 7), 'cd_mun'))->toBe(['5500049', '5500050', '5500051', '5500052', '5500053', '5500054', '5500055'])
        ->and(array_column(array_slice($all, 48, 7), 'posicao'))->toBe([49, 50, 51, 52, 53, 54, 55]);
    $this->get('/api/ufs/55/municipios?page=4')->assertOk()->assertExactJson([
        'data' => [],
        'meta' => ['current_page' => 4, 'per_page' => 25, 'total' => 55, 'last_page' => 3],
    ]);
    expect($all[24]['nm_mun'])->toBe('Zulu')->and($all[25]['nm_mun'])->toBe('Alfa')
        ->and($all[24]['densidade_hab_km2'])->toBe(50)
        ->and($all[25]['densidade_hab_km2'])->toBe(50)
        ->and($all[49]['densidade_hab_km2'])->toBe(1);
    foreach ($pages[2]['data'] as $row) {
        expect($row['densidade_hab_km2'])->toBeNull()
            ->and($row['populacao'])->toBeNull()->and($row['area_km2'])->toBeNull();
    }
    foreach ([50 => [50, 5, 2], 100 => [55, 0, 1]] as $size => [$firstCount, $secondCount, $last]) {
        foreach ([1 => $firstCount, 2 => $secondCount] as $page => $count) {
            $this->get('/api/ufs/55/municipios?page='.$page.'&per_page='.$size)->assertOk()
                ->assertJsonCount($count, 'data')->assertJsonPath('meta', [
                    'current_page' => $page, 'per_page' => $size, 'total' => 55, 'last_page' => $last,
                ]);
        }
    }
});

it('returns 422 for every specified invalid page or per_page value', function (string $query): void {
    $this->get('/api/ufs/55/municipios?'.$query)->assertStatus(422)
        ->assertJsonPath('message', 'Os parâmetros informados são inválidos.')
        ->assertJsonValidationErrors(str_starts_with($query, 'per_page') ? 'per_page' : 'page');
})->with([
    'page=0' => ['page=0'], 'page=-1' => ['page=-1'], 'page=abc' => ['page=abc'],
    'page=1.5' => ['page=1.5'], 'page empty' => ['page='], 'page array' => ['page[]=1'],
    'per_page=0' => ['per_page=0'], 'per_page=-1' => ['per_page=-1'],
    'per_page=24' => ['per_page=24'], 'per_page=26' => ['per_page=26'],
    'per_page=101' => ['per_page=101'], 'per_page=abc' => ['per_page=abc'],
    'per_page empty' => ['per_page='], 'per_page array' => ['per_page[]=25'],
]);

it('returns 404 for malformed and nonexistent state codes', function (string $code): void {
    $this->get('/api/ufs/'.rawurlencode($code).'/municipios')->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.']);
})->with(['one digit' => ['4'], 'three digits' => ['043'], 'unknown' => ['99'], 'non ASCII' => ['４３'], 'letters' => ['ab']]);

it('uses global JSON error handling for unavailable database, unexpected failure, method and unknown route', function (): void {
    config(['database.connections.census.database' => $this->databasePath.'.missing']);
    $this->get('/api/ufs/31/municipios')->assertStatus(503)
        ->assertExactJson(['message' => 'Base censitária indisponível.']);
    config(['database.connections.census.database' => $this->databasePath]);
    DB::purge('census');

    $this->app->bind(StateRankingService::class, function () {
        throw new RuntimeException('Internal ranking failure');
    });
    $this->get('/api/ufs/31/municipios')->assertStatus(500)
        ->assertExactJson(['message' => 'Não foi possível atender à solicitação.']);
    $this->postJson('/api/ufs/31/municipios')->assertStatus(405)
        ->assertExactJson(['message' => 'Não foi possível atender à solicitação.']);
    $this->get('/api/ufs/31/ranking-unknown')->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.']);
});

it('returns JSON without an Accept header', function (): void {
    $this->get('/api/ufs/55/municipios')->assertOk()
        ->assertHeader('Content-Type', 'application/json');
});

it('keeps an integer page with an overflowing offset beyond the last page', function (): void {
    $this->get('/api/ufs/55/municipios?page='.PHP_INT_MAX)->assertOk()->assertExactJson([
        'data' => [],
        'meta' => ['current_page' => PHP_INT_MAX, 'per_page' => 25, 'total' => 55, 'last_page' => 3],
    ]);
});
