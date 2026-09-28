<?php

use App\Services\StateRankingService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $path = getenv('CENSUS_AUDIT_DATABASE');
    $this->assertTrue(is_string($path) && is_file($path) && is_readable($path),
        'Dataset real obrigatório. Execute: docker compose --profile audit run --build --rm dataset-audit');
    $this->auditPath = $path;
    $this->auditHash = hash_file('sha256', $path);
    $this->assertSame('f429226fa3372fde8b4cd22a32cb2a3fc24533b5fb340249b83867d3b485086a', $this->auditHash);
    $this->oracle = new PDO('sqlite:'.$path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
    ]);
    $this->oracle->exec('PRAGMA query_only = ON');
    $this->assertSame(1, $this->oracle->query('PRAGMA query_only')->fetchColumn());

    config(['database.connections.census.database' => $path]);
    DB::purge('census');
    $this->assertSame(PDO::SQLITE_OPEN_READONLY,
        config('database.connections.census.options')[PDO::SQLITE_ATTR_OPEN_FLAGS]);
    $this->assertSame(1, DB::connection('census')->selectOne('PRAGMA query_only')->query_only);
});

afterEach(function (): void {
    DB::purge('census');
    $this->oracle = null;
    $this->assertSame($this->auditHash, hash_file('sha256', $this->auditPath));
});

it('matches independent full rankings for six real UFs and verifies the RS anomaly regression', function (string $code, string $name): void {
    $statement = $this->oracle->prepare(file_get_contents(__DIR__.'/sql/ranking.sql'));
    $statement->bindValue(':uf', $code, PDO::PARAM_STR);
    $statement->execute();
    $expected = $statement->fetchAll();
    $actual = app(StateRankingService::class)->forState($code);
    $this->assertIsArray($actual, "$code $name: UF deveria existir.");
    $this->assertCount(count($expected), $actual, "$code $name: quantidade diverge.");

    $expectedCodes = array_column($expected, 'cd_mun');
    $actualCodes = array_column($actual, 'cd_mun');
    $this->assertCount(count(array_unique($expectedCodes)), $expectedCodes, "$code: duplicações na referência SQL.");
    $this->assertCount(count(array_unique($actualCodes)), $actualCodes, "$code: duplicações na aplicação.");
    $this->assertSame($expectedCodes, $actualCodes, "$code: omissão, inclusão ou ordem diverge.");

    foreach ($expected as $index => $row) {
        $result = $actual[$index];
        $position = $index + 1;
        $this->assertSame($position, $result['posicao'], "$code / {$row['cd_mun']}: posição global.");
        $this->assertSame($row['cd_mun'], $result['cd_mun']);
        $this->assertSame($row['nm_mun'], $result['nm_mun']);
        $this->assertSame($row['population_sum'] === null ? null : (int) $row['population_sum'], $result['populacao']);
        if ($row['area_sum'] === null) {
            $this->assertNull($result['area_km2']);
        } else {
            $this->assertEqualsWithDelta((float) $row['area_sum'], $result['area_km2'], 1e-6, "$code / {$row['cd_mun']}: área.");
        }
        if ($row['density'] === null) {
            $this->assertNull($result['densidade_hab_km2']);
        } else {
            $this->assertEqualsWithDelta((float) $row['density'], $result['densidade_hab_km2'], 1e-6, "$code / {$row['cd_mun']}: densidade.");
        }
    }

    $summary = [
        'cd_uf' => $code,
        'nm_uf' => $name,
        'municipios_elegiveis' => count($actual),
        'primeiros_tres' => array_slice($actualCodes, 0, 3),
        'ultimos_tres' => array_slice($actualCodes, -3),
        'posicao_final' => count($actual),
    ];
    fwrite(STDOUT, 'RANKING_AUDIT_JSON '.json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL);

    if ($code === '43') {
        $anomaly = $this->oracle->query("SELECT cd_mun, nm_mun, cd_uf FROM municipio WHERE cd_mun = '.'")->fetch();
        $this->assertSame(['cd_mun' => '.', 'nm_mun' => '', 'cd_uf' => '43'], $anomaly);
        $dot = $this->oracle->query("SELECT COUNT(*) AS sectors, SUM(populacao) AS population, SUM(area_km2) AS area FROM setor WHERE cd_mun = '.'")->fetch();
        $this->assertSame(2, (int) $dot['sectors']);
        $this->assertSame(0, (int) $dot['population']);
        $this->assertEqualsWithDelta(13085.864101, (float) $dot['area'], 1e-6);

        $response = $this->getJson('/api/ufs/43')->assertOk();
        $state = $response->json('data');
        $this->assertSame(2, $state['setores_sem_municipio_identificavel']);
        $this->assertSame(25569, $state['agregados']['total_setores']);
        $this->assertSame(10882965, $state['agregados']['populacao']);
        $this->assertEqualsWithDelta(281707.1504883004, $state['agregados']['area_km2'], 1e-6);
        $this->assertSame(2, $dot['sectors']);
    }
})->with([
    'Minas Gerais 31' => ['31', 'Minas Gerais'],
    'São Paulo 35' => ['35', 'São Paulo'],
    'Rio Grande do Sul 43' => ['43', 'Rio Grande do Sul'],
    'Amapá 16' => ['16', 'Amapá'],
    'Roraima 14' => ['14', 'Roraima'],
    'Distrito Federal 53' => ['53', 'Distrito Federal'],
])->group('real-dataset');
