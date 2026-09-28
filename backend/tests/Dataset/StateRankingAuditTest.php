<?php

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

it('matches paginated HTTP rankings against the independent full SQL oracle', function (string $code, string $name): void {
    $statement = $this->oracle->prepare(file_get_contents(__DIR__.'/sql/ranking.sql'));
    $statement->bindValue(':uf', $code, PDO::PARAM_STR);
    $statement->execute();
    $expected = $statement->fetchAll();
    $total = count($expected);
    $this->assertSame(['31' => 853, '35' => 645, '43' => 497, '16' => 16, '14' => 15, '53' => 1][$code], $total, "$code $name: total auditado.");

    $assertPage = function (int $page, int $perPage, int $expectedLastPage) use ($code, $expected, $total): array {
        $response = $this->getJson("/api/ufs/$code/municipios?page=$page&per_page=$perPage")->assertOk();
        $payload = $response->json();
        $this->assertSame(['data', 'meta'], array_keys($payload));
        $this->assertSame([
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $expectedLastPage,
        ], $payload['meta'], "$code page $page / $perPage: metadados.");
        $expectedSlice = array_slice($expected, ($page - 1) * $perPage, $perPage);
        $this->assertCount(count($expectedSlice), $payload['data'], "$code page $page / $perPage: tamanho.");
        foreach ($expectedSlice as $index => $row) {
            $actual = $payload['data'][$index];
            $position = ($page - 1) * $perPage + $index + 1;
            $this->assertSame($position, $actual['posicao'], "$code / {$row['cd_mun']}: posição global.");
            $this->assertSame($row['cd_mun'], $actual['cd_mun']);
            $this->assertSame($row['nm_mun'], $actual['nm_mun']);
            $this->assertSame($row['population_sum'] === null ? null : (int) $row['population_sum'], $actual['populacao']);
            if ($row['area_sum'] === null) {
                $this->assertNull($actual['area_km2']);
            } else {
                $this->assertEqualsWithDelta((float) $row['area_sum'], $actual['area_km2'], 1e-6, "$code / {$row['cd_mun']}: área.");
            }
            if ($row['density'] === null) {
                $this->assertNull($actual['densidade_hab_km2']);
            } else {
                $this->assertEqualsWithDelta((float) $row['density'], $actual['densidade_hab_km2'], 1e-6, "$code / {$row['cd_mun']}: densidade.");
            }
        }
        return $payload;
    };

    $this->assertCount($total, array_unique(array_column($expected, 'cd_mun')));
    $perPage25Last = (int) max(1, ceil($total / 25));
    $allPages = [];
    for ($page = 1; $page <= $perPage25Last; $page++) {
        $payload = $assertPage($page, 25, $perPage25Last);
        array_push($allPages, ...$payload['data']);
    }
    $this->assertSame(array_column($expected, 'cd_mun'), array_column($allPages, 'cd_mun'), "$code: omissão, duplicação ou ordenação divergente entre páginas.");
    $this->assertSame(range(1, $total), array_column($allPages, 'posicao'), "$code: posições reiniciadas localmente.");

    foreach ([50 => (int) max(1, ceil($total / 50)), 100 => (int) max(1, ceil($total / 100))] as $perPage => $lastPage) {
        $assertPage(1, $perPage, $lastPage);
        $assertPage($lastPage, $perPage, $lastPage);
    }
    if ($code === '31') {
        $this->assertSame(35, $perPage25Last);
        $this->assertCount(3, $assertPage(35, 25, 35)['data']);
        $this->assertSame([], $assertPage(36, 25, 35)['data']);
        $this->assertSame(18, (int) ceil($total / 50));
        $this->assertSame(9, (int) ceil($total / 100));
    }
    if ($code === '53') {
        $this->assertSame(1, $perPage25Last);
        $this->assertCount(1, $allPages);
    }

    $summary = [
        'cd_uf' => $code,
        'nm_uf' => $name,
        'municipios_elegiveis' => $total,
        'per_page_25_last_page' => $perPage25Last,
        'primeiros_tres' => array_slice(array_column($allPages, 'cd_mun'), 0, 3),
        'ultimos_tres' => array_slice(array_column($allPages, 'cd_mun'), -3),
    ];
    fwrite(STDOUT, 'RANKING_PAGINATION_AUDIT_JSON '.json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL);

    if ($code === '43') {
        $anomaly = $this->oracle->query("SELECT cd_mun, nm_mun, cd_uf FROM municipio WHERE cd_mun = '.'")->fetch();
        $this->assertSame(['cd_mun' => '.', 'nm_mun' => '', 'cd_uf' => '43'], $anomaly);
        $this->assertNotContains('.', array_column($allPages, 'cd_mun'));
        $dot = $this->oracle->query("SELECT COUNT(*) AS sectors, SUM(populacao) AS population, SUM(area_km2) AS area FROM setor WHERE cd_mun = '.'")->fetch();
        $this->assertSame(2, (int) $dot['sectors']);
        $this->assertSame(0, (int) $dot['population']);
        $this->assertEqualsWithDelta(13085.864101, (float) $dot['area'], 1e-6);

        $state = $this->getJson('/api/ufs/43')->assertOk()->json('data');
        $this->assertSame(2, $state['setores_sem_municipio_identificavel']);
        $this->assertSame(25569, $state['agregados']['total_setores']);
        $this->assertSame(10882965, $state['agregados']['populacao']);
        $this->assertEqualsWithDelta(281707.1504883004, $state['agregados']['area_km2'], 1e-6);
    }
})->with([
    'Minas Gerais 31' => ['31', 'Minas Gerais'],
    'São Paulo 35' => ['35', 'São Paulo'],
    'Rio Grande do Sul 43' => ['43', 'Rio Grande do Sul'],
    'Amapá 16' => ['16', 'Amapá'],
    'Roraima 14' => ['14', 'Roraima'],
    'Distrito Federal 53' => ['53', 'Distrito Federal'],
])->group('real-dataset');
