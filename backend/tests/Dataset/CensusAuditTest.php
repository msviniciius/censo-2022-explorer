<?php

use App\Services\MunicipalCensusService;
use App\Services\StateCensusService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $path = getenv('CENSUS_AUDIT_DATABASE');
    $this->assertTrue(is_string($path) && is_file($path) && is_readable($path),
        'Dataset real obrigatório. Execute: docker compose --profile audit run --build --rm dataset-audit');
    $this->auditPath = $path;
    $this->auditHash = hash_file('sha256', $path);
    $this->assertSame('f429226fa3372fde8b4cd22a32cb2a3fc24533b5fb340249b83867d3b485086a',
        $this->auditHash, 'O arquivo não corresponde ao snapshot censitário auditado.');

    // Independent connection: no Laravel Query/Service participates in the SQL oracle.
    $this->oracle = new PDO('sqlite:'.$path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
    ]);
    $this->oracle->exec('PRAGMA query_only = ON'); // Connection-local; no persistent PRAGMA.
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
    if (isset($this->auditPath, $this->auditHash)) {
        $this->assertSame($this->auditHash, hash_file('sha256', $this->auditPath),
            'Integridade: SHA-256 do censo.sqlite mudou durante a auditoria.');
    }
});

it('matches the real dataset against independent SQL', function (string $scope, string $code): void {
    $label = "$scope $code";
    $start = hrtime(true);
    $statement = $this->oracle->prepare(file_get_contents(__DIR__.'/sql/territory.sql'));
    $statement->bindValue(':municipality', $scope === 'municipio' ? $code : null,
        $scope === 'municipio' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $statement->bindValue(':state', $scope === 'uf' ? $code : null,
        $scope === 'uf' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $statement->execute();
    $sql = $statement->fetch();
    $sqlMs = (hrtime(true) - $start) / 1e6;
    $this->assertIsArray($sql, "$label: SQL não retornou identidade.");
    $this->assertFalse($statement->fetch(), "$label: SQL retornou identidades duplicadas.");

    $start = hrtime(true);
    $actual = $scope === 'municipio'
        ? app(MunicipalCensusService::class)->find($code)
        : app(StateCensusService::class)->find($code);
    $appMs = (hrtime(true) - $start) / 1e6;
    $this->assertIsArray($actual, "$label: Service não retornou identidade.");

    $expected = array_filter($sql, fn (string $key): bool => ! str_starts_with($key, 'audit.'), ARRAY_FILTER_USE_KEY);
    if ($scope === 'municipio') {
        unset($expected['setores_sem_municipio_identificavel']);
    } else {
        unset($expected['cd_mun'], $expected['nm_mun']);
    }
    $flatActual = Arr::dot($actual); // Only flattens DTO keys; performs no metric calculation.
    $expectedKeys = array_keys($expected);
    $actualKeys = array_keys($flatActual);
    sort($expectedKeys);
    sort($actualKeys);
    $this->assertSame($expectedKeys, $actualKeys, "$label: campos do contrato interno divergem.");
    foreach ($expected as $field => $value) {
        $message = "$label / $field: SQL=".var_export($value, true).'; aplicação='.var_export($flatActual[$field], true);
        if (is_float($value)) {
            $this->assertIsFloat($flatActual[$field], $message);
            $this->assertEqualsWithDelta($value, $flatActual[$field], 1e-6, $message);
        } else {
            $this->assertSame($value, $flatActual[$field], $message);
        }
    }

    $metrics = $actual['agregados'];
    $distribution = $metrics['distribuicao'];
    $this->assertSame($metrics['total_setores'],
        $distribution['urban'] + $distribution['rural'] + $distribution['unclassified'], "$label: partição dos setores.");
    $this->assertSame($sql['audit.total_sexo'], $metrics['homens']['valor'] + $metrics['mulheres']['valor'],
        "$label: denominador obtido das duas somas independentes.");

    // Previously observed references are an additional check, not the independent oracle.
    if ($scope === 'municipio') {
        $this->assertSame(85, $metrics['total_setores']);
        $this->assertSame(21494, $metrics['populacao']);
        $this->assertEqualsWithDelta(7067.1267819, $metrics['area_km2'], 1e-6);
        $this->assertSame(10744, $metrics['homens']['valor']);
        $this->assertSame(10601, $metrics['mulheres']['valor']);
        $this->assertSame(67, $metrics['homens']['setores_com_valor']);
        $this->assertSame(67, $metrics['mulheres']['setores_com_valor']);
    } else {
        $this->assertSame(25569, $metrics['total_setores']);
        $this->assertSame(10882965, $metrics['populacao']);
        $this->assertEqualsWithDelta(281707.1504883004, $metrics['area_km2'], 1e-6);
        $anomaly = $this->oracle->query("SELECT cd_mun, nm_mun, cd_uf FROM municipio WHERE cd_mun = '.'")->fetch();
        $this->assertSame(['cd_mun' => '.', 'nm_mun' => '', 'cd_uf' => '43'], $anomaly);
        $this->assertSame(2, $sql['audit.dot_setores'], 'A seleção estadual independente deve incluir os dois setores de ponto.');
        $this->assertSame(0, $sql['audit.dot_populacao']);
        $this->assertEqualsWithDelta(13085.864101, $sql['audit.dot_area_km2'], 1e-6);
        $this->assertSame(2, $actual['setores_sem_municipio_identificavel']);
    }

    fwrite(STDOUT, "\nAUDIT_JSON ".json_encode([
        'territorio' => $label, 'aplicacao' => $actual, 'sql_independente' => $sql,
        'duracao_ms' => ['sql' => $sqlMs, 'aplicacao' => $appMs],
        'sha256' => $this->auditHash,
    ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR).PHP_EOL);
})->with([
    'município 1100015' => ['municipio', '1100015'],
    'UF 43, incluindo ponto' => ['uf', '43'],
])->group('real-dataset');
