<?php

use App\Services\StateCensusService;
use App\Services\StateListService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->databasePath = tempnam(sys_get_temp_dir(), 'census-state-http-');
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec(<<<'SQL'
        PRAGMA foreign_keys = ON;
        CREATE TABLE uf (cd_uf TEXT PRIMARY KEY, nm_uf TEXT NOT NULL) WITHOUT ROWID;
        CREATE TABLE municipio (cd_mun TEXT PRIMARY KEY, nm_mun TEXT NOT NULL, cd_uf TEXT NOT NULL REFERENCES uf(cd_uf)) WITHOUT ROWID;
        CREATE TABLE setor (cd_setor TEXT PRIMARY KEY, cd_mun TEXT NOT NULL REFERENCES municipio(cd_mun), situacao TEXT, area_km2 REAL, populacao INTEGER) WITHOUT ROWID;
        CREATE TABLE demografia (cd_setor TEXT PRIMARY KEY REFERENCES setor(cd_setor), moradores INTEGER, homens INTEGER, mulheres INTEGER) WITHOUT ROWID;
        INSERT INTO uf VALUES
            ('02', 'aguia'), ('43', 'Rio Grande do Sul'), ('01', 'Águia'),
            ('53', 'Distrito Federal'), ('35', 'São Paulo');
        INSERT INTO municipio VALUES
            ('0200001', 'Sem setores', '02'), ('4300001', 'Município RS', '43'),
            ('.', '', '43'), ('5300108', 'Brasília', '53'), ('3500001', 'Outro estado', '35');
        INSERT INTO setor VALUES
            ('430000100000001', '4300001', 'Urbana', 2.0, 100),
            ('430000100000002', '4300001', 'Rural', 3.0, 200),
            ('430000000000001', '.', NULL, 3.0, 0),
            ('430000000000002', '.', 'Outra', 7.0, 0),
            ('530010800000001', '5300108', 'Urbana', 1.0, 0),
            ('350000100000001', '3500001', 'Rural', 1.0, 999);
        INSERT INTO demografia VALUES
            ('430000100000001', 100, 40, 60),
            ('530010800000001', 0, 0, NULL);
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

it('lists UFs by normalized name then textual code using only one uf query', function (): void {
    $connection = DB::connection('census');
    $connection->enableQueryLog();
    $response = $this->get('/api/ufs')->assertOk()->assertHeader('Content-Type', 'application/json');
    expect($response->json())->toBe(['data' => [
        ['cd_uf' => '01', 'nm_uf' => 'Águia'],
        ['cd_uf' => '02', 'nm_uf' => 'aguia'],
        ['cd_uf' => '53', 'nm_uf' => 'Distrito Federal'],
        ['cd_uf' => '43', 'nm_uf' => 'Rio Grande do Sul'],
        ['cd_uf' => '35', 'nm_uf' => 'São Paulo'],
    ]]);
    // UFs without municipalities or sectors remain present; no per-UF aggregates are loaded.
    $queries = $connection->getQueryLog();
    expect($queries)->toHaveCount(1);
    expect(strtolower($queries[0]['query']))->toContain('from "uf"')
        ->not->toContain('join', 'municipio', 'setor', 'demografia');
});

it('returns an empty list when the uf table is empty without requiring other tables', function (): void {
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec('DROP TABLE demografia; DROP TABLE setor; DROP TABLE municipio; DELETE FROM uf;');
    unset($fixture);
    $this->fixtureHash = hash_file('sha256', $this->databasePath);
    $this->get('/api/ufs')->assertOk()->assertExactJson(['data' => []]);
});

it('exposes the state aggregate contract including both anomalous sectors and partial coverage', function (): void {
    $response = $this->get('/api/ufs/43')->assertOk()->assertHeader('Content-Type', 'application/json');
    expect($response->json())->toBe(['data' => [
        'cd_uf' => '43', 'nm_uf' => 'Rio Grande do Sul',
        'agregados' => [
            'total_setores' => 4, 'populacao' => 300, 'area_km2' => 15, 'densidade_hab_km2' => 20,
            'distribuicao' => [
                'total' => 4, 'urban' => 1, 'rural' => 1, 'unclassified' => 2,
                'urban_pct' => 25, 'rural_pct' => 25, 'unclassified_pct' => 50,
            ],
            'homens' => ['valor' => 40, 'percentual' => 40, 'setores_com_valor' => 1, 'total_setores' => 4, 'estado' => 'parcial'],
            'mulheres' => ['valor' => 60, 'percentual' => 60, 'setores_com_valor' => 1, 'total_setores' => 4, 'estado' => 'parcial'],
        ],
        'setores_sem_municipio_identificavel' => 2,
    ]]);
});

it('includes Distrito Federal details and preserves known zero versus unavailable data', function (): void {
    $response = $this->getJson('/api/ufs/53')->assertOk();
    expect($response->json())->toBe(['data' => [
        'cd_uf' => '53', 'nm_uf' => 'Distrito Federal',
        'agregados' => [
            'total_setores' => 1, 'populacao' => 0, 'area_km2' => 1, 'densidade_hab_km2' => 0,
            'distribuicao' => [
                'total' => 1, 'urban' => 1, 'rural' => 0, 'unclassified' => 0,
                'urban_pct' => 100, 'rural_pct' => 0, 'unclassified_pct' => 0,
            ],
            'homens' => ['valor' => 0, 'percentual' => null, 'setores_com_valor' => 1, 'total_setores' => 1, 'estado' => 'completo'],
            'mulheres' => ['valor' => null, 'percentual' => null, 'setores_com_valor' => 0, 'total_setores' => 1, 'estado' => 'indisponivel'],
        ],
        'setores_sem_municipio_identificavel' => 0,
    ]]);
});

it('keeps existing UFs without sectors distinct from missing UFs', function (string $code, string $name): void {
    $response = $this->get('/api/ufs/'.$code)->assertOk();
    expect($response->json())->toBe(['data' => [
        'cd_uf' => $code, 'nm_uf' => $name,
        'agregados' => [
            'total_setores' => 0, 'populacao' => null, 'area_km2' => null, 'densidade_hab_km2' => null,
            'distribuicao' => [
                'total' => 0, 'urban' => 0, 'rural' => 0, 'unclassified' => 0,
                'urban_pct' => null, 'rural_pct' => null, 'unclassified_pct' => null,
            ],
            'homens' => ['valor' => null, 'percentual' => null, 'setores_com_valor' => 0, 'total_setores' => 0, 'estado' => 'indisponivel'],
            'mulheres' => ['valor' => null, 'percentual' => null, 'setores_com_valor' => 0, 'total_setores' => 0, 'estado' => 'indisponivel'],
        ],
        'setores_sem_municipio_identificavel' => 0,
    ]]);
})->with(['no municipalities' => ['01', 'Águia'], 'no sectors' => ['02', 'aguia']]);

it('returns JSON 404 for an unknown UF', function (): void {
    $this->get('/api/ufs/99')->assertNotFound()->assertExactJson(['message' => 'Recurso não encontrado.']);
});

it('rejects malformed UF codes before opening the database', function (string $code): void {
    config(['database.connections.census.database' => $this->databasePath.'.missing']);
    $this->get('/api/ufs/'.rawurlencode($code))->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.']);
    expect(DB::getConnections())->toBe([]);
})->with(['4', '043', '4a', '５３', '.', "' OR 1=1 --"]);

it('returns JSON 404 for an unknown state API route', function (): void {
    $this->get('/api/ufs/43/unknown')->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.']);
});

it('keeps unsupported HTTP methods outside the state read-only API', function (string $path): void {
    $this->postJson($path)->assertStatus(405)
        ->assertExactJson(['message' => 'Não foi possível atender à solicitação.']);
    $this->deleteJson($path)->assertStatus(405);
})->with(['/api/ufs', '/api/ufs/43']);

it('uses the global generic 503 response for an unavailable census database', function (string $path, string $failure): void {
    config(['app.debug' => true]);
    if ($failure === 'missing') {
        config(['database.connections.census.database' => $this->databasePath.'.missing']);
    } else {
        file_put_contents($this->databasePath, 'not a SQLite database');
        $this->fixtureHash = hash_file('sha256', $this->databasePath);
    }
    $this->get($path)->assertStatus(503)->assertExactJson(['message' => 'Base censitária indisponível.']);
    expect(file_exists($this->databasePath.'.missing'))->toBeFalse();
})->with([
    ['/api/ufs', 'missing'], ['/api/ufs/43', 'missing'],
    ['/api/ufs', 'corrupted'], ['/api/ufs/43', 'corrupted'],
]);

it('uses the global generic 500 response without exposing internals', function (string $path, string $service): void {
    config(['app.debug' => true]);
    $this->app->bind($service, function () {
        throw new RuntimeException('Internal secret: SELECT * FROM uf at /private/censo.sqlite');
    });
    $this->get($path)->assertStatus(500)
        ->assertExactJson(['message' => 'Não foi possível atender à solicitação.']);
})->with([
    ['/api/ufs', StateListService::class], ['/api/ufs/43', StateCensusService::class],
]);
