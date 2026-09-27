<?php

use App\Queries\MunicipalitySearchQuery;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->databasePath = tempnam(sys_get_temp_dir(), 'census-search-');
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec(<<<'SQL'
        PRAGMA foreign_keys = ON;
        CREATE TABLE uf (cd_uf TEXT PRIMARY KEY, nm_uf TEXT NOT NULL) WITHOUT ROWID;
        CREATE TABLE municipio (cd_mun TEXT PRIMARY KEY, nm_mun TEXT NOT NULL, cd_uf TEXT NOT NULL REFERENCES uf(cd_uf)) WITHOUT ROWID;
        CREATE TABLE setor (cd_setor TEXT PRIMARY KEY, cd_mun TEXT NOT NULL REFERENCES municipio(cd_mun), situacao TEXT, area_km2 REAL, populacao INTEGER) WITHOUT ROWID;
        CREATE TABLE demografia (cd_setor TEXT PRIMARY KEY REFERENCES setor(cd_setor), moradores INTEGER, homens INTEGER, mulheres INTEGER) WITHOUT ROWID;
        INSERT INTO uf VALUES ('01', 'UF A'), ('02', 'UF B'), ('03', 'UF C'), ('43', 'Rio Grande do Sul');
        SQL);
    // No sectors or demographic rows: search depends only on territorial identity.
    $municipalities = [
        ['0100003', 'São José', '01'],
        ['0200001', 'SAO JOSE', '02'],
        ['0100002', 'SÃO JOSÉ', '01'],
        ['0100001', "Sa\u{0303}o Jose\u{0301}", '01'],
        ['0110001', 'João Pessoa', '01'],
        ['0110002', 'Água Branca', '01'],
        ['0110003', 'Bom Jesus', '01'],
        ['0210001', 'Bom Jesus', '02'],
        ['0110004', 'Évora', '01'],
        ['0120001', 'Porto % Azul', '01'],
        ['0120002', 'Porto _ Azul', '01'],
        ['0120003', 'Porto \\ Azul', '01'],
        ['0120004', 'Porto ! Azul', '01'],
        ['0120005', "Alta Floresta D'Oeste", '01'],
        ['0120006', "Lugar %_\\!' combinado", '01'],
        ['0120007', "Lugar %' OR 1=1 --", '01'],
        ['0120008', 'Porto XX Azul', '01'],
        ['0120009', 'Porto X Azul', '01'],
        ['0120010', 'Porto Azul', '01'],
        ['.', '', '43'],
        ['0000000', '', '43'],
        ['0000001', '   ', '43'],
        ['12345', 'São Inválido', '43'],
        ['12345678', 'São Inválido', '43'],
        ['ABC0001', 'São Inválido', '43'],
        ['１２３４５６７', 'São Inválido', '43'],
    ];
    // Reverse code order ensures the limit must follow normalized-name ordering.
    foreach (['Á', 'B', 'Ç', 'D', 'É', 'F', 'G', 'H', 'Í', 'J', 'K', 'L'] as $index => $suffix) {
        $municipalities[] = [sprintf('030%04d', 12 - $index), 'Lista '.$suffix, '03'];
    }
    $insert = $fixture->prepare('INSERT INTO municipio VALUES (?, ?, ?)');
    foreach ($municipalities as $municipality) {
        $insert->execute($municipality);
    }
    unset($insert, $fixture);

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

it('matches names exactly or partially with Unicode normalization', function (string $term, array $codes): void {
    $results = app(MunicipalitySearchQuery::class)->search($term);
    expect(array_column($results, 'cd_mun'))->toBe($codes);
})->with([
    'exact' => ['João Pessoa', ['0110001']],
    'partial' => ['Pessoa', ['0110001']],
    'case' => ['JOÃO PESSOA', ['0110001']],
    'accent' => ['Joao Pessoa', ['0110001']],
    'case and accent' => ['JOAO PESSOA', ['0110001']],
    'prefix' => ['joao', ['0110001']],
    'internal fragment' => ['ao pess', ['0110001']],
    'trim' => [" \tJoão Pessoa\n", ['0110001']],
    'two Unicode characters' => ['Ág', ['0110002']],
    'two ASCII characters' => ['ag', ['0110002']],
    'uppercase non-ASCII letter' => ['évora', ['0110004']],
    'NFC and NFD names, tied by code' => ['sao jose', ['0100001', '0100002', '0100003', '0200001']],
    'NFD search term' => ["SA\u{0303}O JOSE\u{0301}", ['0100001', '0100002', '0100003', '0200001']],
    'no match' => ['Atlantida inexistente', []],
    'does not search by municipal code' => ['0110001', []],
    'does not search by state name' => ['Rio Grande do Sul', []],
]);

it('returns only the contracted fields and keeps national homonyms and string codes', function (): void {
    expect(app(MunicipalitySearchQuery::class)->search('bom jesus'))->toBe([
        ['cd_mun' => '0110003', 'nm_mun' => 'Bom Jesus', 'cd_uf' => '01', 'nm_uf' => 'UF A'],
        ['cd_mun' => '0210001', 'nm_mun' => 'Bom Jesus', 'cd_uf' => '02', 'nm_uf' => 'UF B'],
    ]);
});

it('orders by normalized name before code and applies the ten-row limit in SQL', function (): void {
    $connection = DB::connection('census');
    $connection->enableQueryLog();
    $results = app(MunicipalitySearchQuery::class)->search('lista');

    expect(array_column($results, 'cd_mun'))->toBe([
        '0300012', '0300011', '0300010', '0300009', '0300008',
        '0300007', '0300006', '0300005', '0300004', '0300003',
    ]);
    $queries = $connection->getQueryLog();
    expect($queries)->toHaveCount(1)
        ->and(strtolower($queries[0]['query']))->toContain('limit 10');
});

it('returns empty for short terms without opening the census connection', function (string $term): void {
    config(['database.connections.census.database' => $this->databasePath.'.missing']);
    // Opening this connection would throw because the file does not exist.
    expect(app(MunicipalitySearchQuery::class)->search($term))->toBe([])
        ->and(DB::getConnections())->toBe([]);
})->with(['', ' ', " \t\n ", 'a', 'é', ' Á ', '%', '_', '\\', "'"]);

it('does not turn a term made only of combining accents into a match-all pattern', function (): void {
    expect(app(MunicipalitySearchQuery::class)->search("\u{0301}\u{0303}"))->toBe([])
        ->and(DB::getConnections())->toBe([]);
});

it('treats LIKE metacharacters, the escape character, backslashes and quotes literally', function (
    string $term, string $code,
): void {
    $connection = DB::connection('census');
    $connection->enableQueryLog();
    expect(array_column(app(MunicipalitySearchQuery::class)->search($term), 'cd_mun'))->toBe([$code]);
    $queries = $connection->getQueryLog();
    expect($queries)->toHaveCount(1)
        ->and($queries[0]['bindings'])->toHaveCount(1)
        ->and($queries[0]['query'])->toContain("LIKE ? ESCAPE '!'")
        ->not->toContain($term);
})->with([
    'percent is not any sequence' => ['Porto % Azul', '0120001'],
    'underscore is not any character' => ['Porto _ Azul', '0120002'],
    'backslash' => ['Porto \\ Azul', '0120003'],
    'chosen escape character' => ['Porto ! Azul', '0120004'],
    'apostrophe' => ["D'Oeste", '0120005'],
    'all special characters combined' => ["%_\\!'", '0120006'],
    'SQL-looking name fragment' => ["%' OR 1=1 --", '0120007'],
]);

it('does not let SQL-looking input broaden results', function (): void {
    expect(app(MunicipalitySearchQuery::class)->search("%' OR 1=1 -- nonexistent"))->toBe([]);
});

it('excludes malformed codes and blank names while keeping the anomalous record in the fixture', function (): void {
    $query = app(MunicipalitySearchQuery::class);
    expect(array_column($query->search('sao'), 'cd_mun'))->toBe(['0100001', '0100002', '0100003', '0200001'])
        ->and($query->search('   '))->toBe([])
        ->and($query->search('inválido'))->toBe([]);
    $anomaly = DB::connection('census')->table('municipio')->where('cd_mun', '.')->first();
    expect([$anomaly->cd_mun, $anomaly->nm_mun, $anomaly->cd_uf])->toBe(['.', '', '43']);
});

it('rejects the anomalous code even if a synthetic fixture gives it a matching name', function (): void {
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec("UPDATE municipio SET nm_mun = 'São Inválido' WHERE cd_mun = '.'");
    unset($fixture);
    $this->fixtureHash = hash_file('sha256', $this->databasePath);

    expect(app(MunicipalitySearchQuery::class)->search('inválido'))->toBe([]);
});

it('registers normalization on each new read-only census connection without changing returned names', function (): void {
    $query = app(MunicipalitySearchQuery::class);
    $first = $query->search('sao jose');
    expect(array_column($first, 'nm_mun'))->toBe(["Sa\u{0303}o Jose\u{0301}", 'SÃO JOSÉ', 'São José', 'SAO JOSE'])
        ->and(DB::connection('census')->selectOne('PRAGMA query_only')->query_only)->toBe(1);
    DB::purge('census');
    expect($query->search('sao jose'))->toBe($first)
        ->and(DB::connection('census')->selectOne('PRAGMA query_only')->query_only)->toBe(1);
});
