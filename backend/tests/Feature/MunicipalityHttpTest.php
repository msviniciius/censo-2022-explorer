<?php

use App\Services\MunicipalitySearchService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->databasePath = tempnam(sys_get_temp_dir(), 'census-http-');
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec(<<<'SQL'
        PRAGMA foreign_keys = ON;
        CREATE TABLE uf (cd_uf TEXT PRIMARY KEY, nm_uf TEXT NOT NULL) WITHOUT ROWID;
        CREATE TABLE municipio (cd_mun TEXT PRIMARY KEY, nm_mun TEXT NOT NULL, cd_uf TEXT NOT NULL REFERENCES uf(cd_uf)) WITHOUT ROWID;
        CREATE TABLE setor (cd_setor TEXT PRIMARY KEY, cd_mun TEXT NOT NULL REFERENCES municipio(cd_mun), situacao TEXT, area_km2 REAL, populacao INTEGER) WITHOUT ROWID;
        CREATE TABLE demografia (cd_setor TEXT PRIMARY KEY REFERENCES setor(cd_setor), moradores INTEGER, homens INTEGER, mulheres INTEGER) WITHOUT ROWID;
        INSERT INTO uf VALUES ('01', 'UF A'), ('02', 'UF B'), ('43', 'Rio Grande do Sul');
        INSERT INTO municipio VALUES
            ('0100001', 'São José', '01'), ('0200001', 'São José', '02'),
            ('0100002', 'Sem setores', '01'), ('0100003', 'Área zero', '01'),
            ('0100004', 'Cobertura independente', '01'), ('0100005', 'Dados ausentes', '01'),
            ('0100006', '', '01'), ('0100007', '   ', '01'), ('.', '', '43');
        INSERT INTO setor VALUES
            ('010000100000001', '0100001', 'Urbana', 1.0, 100),
            ('010000100000002', '0100001', 'Rural', 9.0, 200),
            ('010000100000003', '0100001', NULL, 2.0, 0),
            ('010000300000001', '0100003', 'Urbana', 0.0, 50),
            ('010000400000001', '0100004', 'Rural', 2.0, 100),
            ('010000500000001', '0100005', 'Outra', NULL, NULL),
            ('430000000000001', '.', NULL, 3.0, 0),
            ('430000000000002', '.', NULL, 7.0, 0);
        INSERT INTO demografia VALUES
            ('010000100000001', 100, 40, 60),
            ('010000100000002', 200, NULL, 20),
            ('010000300000001', 50, 0, 0),
            ('010000400000001', 100, 0, NULL);
        SQL);
    $insert = $fixture->prepare('INSERT INTO municipio VALUES (?, ?, ?)');
    $insert->execute(['0100008', "Lugar %_\\!' literal", '01']);
    $insert->execute(['0100009', str_repeat('Á', 100), '01']);
    foreach (range(1, 12) as $index) {
        $insert->execute([sprintf('030%04d', $index), sprintf('Lista %02d', $index), '01']);
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

it('serves national autocomplete with the exact envelope, Unicode matching and textual identity', function (): void {
    $response = $this->get('/api/municipios?q=%20SAO%20JOSE%20')
        ->assertOk()->assertHeader('Content-Type', 'application/json');
    expect($response->json())->toBe(['data' => [
        ['cd_mun' => '0100001', 'nm_mun' => 'São José', 'cd_uf' => '01', 'nm_uf' => 'UF A'],
        ['cd_mun' => '0200001', 'nm_mun' => 'São José', 'cd_uf' => '02', 'nm_uf' => 'UF B'],
    ]]);
});

it('returns empty search data without opening the database for absent or short q', function (string $query): void {
    config(['database.connections.census.database' => $this->databasePath.'.missing']);
    $this->get('/api/municipios'.$query)->assertOk()->assertExactJson(['data' => []]);
    expect(DB::getConnections())->toBe([]);
})->with(['', '?q=', '?q=%20%20', '?q=a', '?q=%C3%A9']);

it('returns empty data for a valid unmatched term', function (): void {
    $this->getJson('/api/municipios?q=inexistente')->assertOk()->assertExactJson(['data' => []]);
});

it('accepts two characters and preserves the query ordering and limit', function (): void {
    $response = $this->getJson('/api/municipios?q=li')->assertOk()->assertJsonCount(10, 'data');
    expect(array_column($response->json('data'), 'cd_mun'))->toBe([
        '0300001', '0300002', '0300003', '0300004', '0300005',
        '0300006', '0300007', '0300008', '0300009', '0300010',
    ]);
});

it('passes special characters literally through HTTP to the existing query', function (): void {
    $this->getJson('/api/municipios?'.http_build_query(['q' => "%_\\!'"]))
        ->assertOk()->assertExactJson(['data' => [[
            'cd_mun' => '0100008', 'nm_mun' => "Lugar %_\\!' literal", 'cd_uf' => '01', 'nm_uf' => 'UF A',
        ]]]);
    $this->getJson('/api/municipios?'.http_build_query(['q' => "%' OR 1=1 --"]))
        ->assertOk()->assertExactJson(['data' => []]);
});

it('rejects invalid query types and overlong terms with a field error even without an Accept header', function (
    string $query, string $message,
): void {
    $this->get('/api/municipios?'.$query)->assertStatus(422)->assertExactJson([
        'message' => 'Os parâmetros informados são inválidos.', 'errors' => ['q' => [$message]],
    ]);
    expect(DB::getConnections())->toBe([]);
})->with([
    'array' => ['q[]=sao', 'O parâmetro q deve ser um texto.'],
    'nested object-like query' => ['q[name]=sao', 'O parâmetro q deve ser um texto.'],
    '101 ASCII characters' => ['q='.str_repeat('a', 101), 'O parâmetro q deve ter no máximo 100 caracteres.'],
    '101 Unicode characters' => [http_build_query(['q' => str_repeat('Á', 101)]), 'O parâmetro q deve ter no máximo 100 caracteres.'],
]);

it('accepts 100 Unicode characters after trimming, not a 100-byte limit', function (): void {
    $this->getJson('/api/municipios?'.http_build_query(['q' => ' '.str_repeat('á', 100).' ']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.cd_mun', '0100009');
});

it('exposes complete municipal details with numeric aggregates and independent demographic coverage', function (): void {
    $response = $this->get('/api/municipios/0100001')->assertOk()->assertHeader('Content-Type', 'application/json');
    $response->assertExactJson(['data' => [
        'cd_mun' => '0100001', 'nm_mun' => 'São José', 'cd_uf' => '01', 'nm_uf' => 'UF A',
        'agregados' => [
            'total_setores' => 3, 'populacao' => 300, 'area_km2' => 12, 'densidade_hab_km2' => 25,
            'distribuicao' => [
                'total' => 3, 'urban' => 1, 'rural' => 1, 'unclassified' => 1,
                'urban_pct' => 33.33333333333333, 'rural_pct' => 33.33333333333333, 'unclassified_pct' => 33.33333333333333,
            ],
            'homens' => ['valor' => 40, 'percentual' => 33.33333333333333, 'setores_com_valor' => 1, 'total_setores' => 3, 'estado' => 'parcial'],
            'mulheres' => ['valor' => 80, 'percentual' => 66.66666666666666, 'setores_com_valor' => 2, 'total_setores' => 3, 'estado' => 'parcial'],
        ],
    ]]);
    expect($response->json('data.cd_mun'))->toBe('0100001')
        ->and($response->json('data.cd_uf'))->toBe('01');
    $metrics = $response->json('data.agregados');
    expect($metrics['populacao'])->toBe(300)->and($metrics['total_setores'])->toBe(3);
    foreach (['area_km2', 'densidade_hab_km2'] as $field) {
        expect(is_int($metrics[$field]) || is_float($metrics[$field]))->toBeTrue();
    }
    foreach (['total', 'urban', 'rural', 'unclassified'] as $field) {
        expect($metrics['distribuicao'][$field])->toBeInt();
    }
    foreach (['homens', 'mulheres'] as $sex) {
        expect($metrics[$sex]['valor'])->toBeInt()
            ->and($metrics[$sex]['setores_com_valor'])->toBeInt()
            ->and($metrics[$sex]['total_setores'])->toBeInt()
            ->and($metrics[$sex]['percentual'])->toBeFloat();
    }
});

it('keeps existing municipalities without sectors available with null metrics and zero counts', function (): void {
    $response = $this->getJson('/api/municipios/0100002')->assertOk();
    expect($response->json())->toBe(['data' => [
        'cd_mun' => '0100002', 'nm_mun' => 'Sem setores', 'cd_uf' => '01', 'nm_uf' => 'UF A',
        'agregados' => [
            'total_setores' => 0, 'populacao' => null, 'area_km2' => null, 'densidade_hab_km2' => null,
            'distribuicao' => [
                'total' => 0, 'urban' => 0, 'rural' => 0, 'unclassified' => 0,
                'urban_pct' => null, 'rural_pct' => null, 'unclassified_pct' => null,
            ],
            'homens' => ['valor' => null, 'percentual' => null, 'setores_com_valor' => 0, 'total_setores' => 0, 'estado' => 'indisponivel'],
            'mulheres' => ['valor' => null, 'percentual' => null, 'setores_com_valor' => 0, 'total_setores' => 0, 'estado' => 'indisponivel'],
        ],
    ]]);
});

it('preserves nulls, known zeros and complete versus unavailable coverage in JSON', function (): void {
    $this->getJson('/api/municipios/0100003')->assertOk()
        ->assertJsonPath('data.agregados.densidade_hab_km2', null)
        ->assertJsonPath('data.agregados.homens.valor', 0)
        ->assertJsonPath('data.agregados.homens.percentual', null)
        ->assertJsonPath('data.agregados.homens.estado', 'completo')
        ->assertJsonPath('data.agregados.mulheres.valor', 0)
        ->assertJsonPath('data.agregados.mulheres.percentual', null);
    $this->getJson('/api/municipios/0100004')->assertOk()
        ->assertJsonPath('data.agregados.homens.valor', 0)
        ->assertJsonPath('data.agregados.homens.estado', 'completo')
        ->assertJsonPath('data.agregados.mulheres.valor', null)
        ->assertJsonPath('data.agregados.mulheres.estado', 'indisponivel');
    $this->getJson('/api/municipios/0100005')->assertOk()
        ->assertJsonPath('data.agregados.populacao', null)->assertJsonPath('data.agregados.area_km2', null)
        ->assertJsonPath('data.agregados.densidade_hab_km2', null)
        ->assertJsonPath('data.agregados.distribuicao.unclassified', 1)
        ->assertJsonPath('data.agregados.homens.valor', null)->assertJsonPath('data.agregados.mulheres.valor', null);
});

it('returns JSON 404 for missing, malformed or non-selectable municipalities', function (string $code): void {
    $this->get('/api/municipios/'.rawurlencode($code))->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.']);
})->with(['9999999', '123', '12345678', '010000A', '１２３４５６７', '.', '0100006', '0100007', "' OR 1=1 --"]);

it('returns JSON 404 for an unknown API route', function (): void {
    $this->get('/api/municipios/0100001/unknown')->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.']);
});

it('keeps unsupported HTTP methods outside the read-only API', function (): void {
    $this->postJson('/api/municipios')->assertStatus(405)
        ->assertExactJson(['message' => 'Não foi possível atender à solicitação.']);
    $this->deleteJson('/api/municipios/0100001')->assertStatus(405);
});

it('returns generic 503 for an unavailable census database', function (string $path, string $failure): void {
    config(['app.debug' => true]); // The envelope must remain safe even with debug enabled.
    if ($failure === 'missing') {
        config(['database.connections.census.database' => $this->databasePath.'.missing']);
    } else {
        file_put_contents($this->databasePath, 'not a SQLite database');
        $this->fixtureHash = hash_file('sha256', $this->databasePath);
    }
    $this->get($path)->assertStatus(503)->assertExactJson(['message' => 'Base censitária indisponível.']);
    expect(file_exists($this->databasePath.'.missing'))->toBeFalse();
})->with([
    ['/api/municipios?q=sao', 'missing'], ['/api/municipios/0100001', 'missing'],
    ['/api/municipios?q=sao', 'corrupted'], ['/api/municipios/0100001', 'corrupted'],
]);

it('returns generic 500 for unexpected failures without exposing exception details', function (): void {
    config(['app.debug' => true]);
    $this->app->bind(MunicipalitySearchService::class, function () {
        throw new RuntimeException('Internal secret: SELECT * FROM municipio at /private/censo.sqlite');
    });
    $this->get('/api/municipios?q=sao')->assertStatus(500)
        ->assertExactJson(['message' => 'Não foi possível atender à solicitação.']);
});
