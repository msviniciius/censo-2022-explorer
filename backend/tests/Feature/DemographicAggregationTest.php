<?php

use App\Queries\SectorAggregationQuery;
use App\Services\MunicipalCensusService;
use App\Services\StateCensusService;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->databasePath = tempnam(sys_get_temp_dir(), 'census-demography-');
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec(<<<'SQL'
        PRAGMA foreign_keys = ON;
        CREATE TABLE uf (cd_uf TEXT PRIMARY KEY, nm_uf TEXT NOT NULL) WITHOUT ROWID;
        CREATE TABLE municipio (cd_mun TEXT PRIMARY KEY, nm_mun TEXT NOT NULL, cd_uf TEXT NOT NULL REFERENCES uf(cd_uf)) WITHOUT ROWID;
        CREATE TABLE setor (cd_setor TEXT PRIMARY KEY, cd_mun TEXT NOT NULL REFERENCES municipio(cd_mun), situacao TEXT, area_km2 REAL, populacao INTEGER) WITHOUT ROWID;
        CREATE TABLE demografia (cd_setor TEXT PRIMARY KEY REFERENCES setor(cd_setor), moradores INTEGER, homens INTEGER, mulheres INTEGER) WITHOUT ROWID;
        INSERT INTO uf VALUES ('43', 'Rio Grande do Sul'), ('35', 'São Paulo'), ('11', 'UF vazia');
        INSERT INTO municipio VALUES ('4300000', 'Sem setores', '43');
        SQL);

    // Each row: situation, area, population, and optional [residents, men, women].
    $territories = [
        '4300001' => [['Urbana', 1.0, 300, [999, 40, 60]]],
        '4300002' => [['Urbana', 1.0, 300, [999, null, 60]]],
        '4300003' => [['Urbana', 1.0, 300, [999, 40, null]]],
        '4300004' => [['Urbana', 1.0, 300, [999, null, null]]],
        '4300005' => [['Urbana', 1.0, 300, null]],
        '4300006' => [
            ['Urbana', 1.0, 100, [999, 40, 60]],
            ['Rural', 2.0, 200, [999, null, 30]],
            [null, 3.0, 300, [999, 10, null]],
            ['Suburbana', 4.0, 400, [999, null, 0]],
            ['Urbana', 5.0, 500, null],
        ],
        '4300007' => [['Urbana', 1.0, 300, [999, 0, 0]]],
        '4300008' => [['Urbana', 1.0, 300, null], ['Rural', 1.0, 300, null]],
        '4300009' => [['Urbana', 1.0, 300, [999, 0, null]]],
        '4300010' => [['Urbana', 1.0, 300, [999, 0, null]], ['Rural', 1.0, 300, [999, 0, 20]]],
        '4300011' => [['Urbana', 1.0, 100, [999, 40, 60]], ['Rural', 2.0, 0, null]],
        '.' => [[null, 3.0, 0, [999, 2, 3]], [null, 7.0, 0, null]],
        '3500001' => [['Urbana', 1.0, 10000, [999, 400, 500]]],
    ];
    $municipality = $fixture->prepare('INSERT INTO municipio VALUES (?, ?, ?)');
    $sector = $fixture->prepare('INSERT INTO setor VALUES (?, ?, ?, ?, ?)');
    $demography = $fixture->prepare('INSERT INTO demografia VALUES (?, ?, ?, ?)');
    foreach ($territories as $municipalCode => $sectors) {
        $municipalCode = (string) $municipalCode;
        $stateCode = $municipalCode === '3500001' ? '35' : '43';
        $municipality->execute([$municipalCode, $municipalCode === '.' ? '' : 'Município '.$municipalCode, $stateCode]);
        foreach ($sectors as $index => [$situation, $area, $population, $demographicValues]) {
            $sectorCode = ($municipalCode === '.' ? '4300000' : $municipalCode).sprintf('%08d', $index + 1);
            $sector->execute([$sectorCode, $municipalCode, $situation, $area, $population]);
            if ($demographicValues !== null) {
                $demography->execute([$sectorCode, ...$demographicValues]);
            }
        }
    }
    unset($municipality, $sector, $demography, $fixture);

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

it('composes independent values and coverage without imputing missing sex data', function (
    string $code, int $total, array $men, array $women,
): void {
    $metrics = app(MunicipalCensusService::class)->find($code)['agregados'];
    expect($metrics['total_setores'])->toBe($total);
    foreach (['homens' => $men, 'mulheres' => $women] as $field => [$value, $percentage, $known, $status]) {
        expect($metrics[$field]['valor'])->toBe($value)
            ->and($metrics[$field]['setores_com_valor'])->toBe($known)
            ->and($metrics[$field]['total_setores'])->toBe($total)
            ->and($metrics[$field]['estado'])->toBe($status);
        if ($percentage === null) {
            expect($metrics[$field]['percentual'])->toBeNull();
        } else {
            expect($metrics[$field]['percentual'])->toEqualWithDelta($percentage, 1e-6);
        }
    }
})->with([
    'both sexes known, population and residents differ' => ['4300001', 1, [40, 40.0, 1, 'completo'], [60, 60.0, 1, 'completo']],
    'men null' => ['4300002', 1, [null, null, 0, 'indisponivel'], [60, null, 1, 'completo']],
    'women null' => ['4300003', 1, [40, null, 1, 'completo'], [null, null, 0, 'indisponivel']],
    'both null' => ['4300004', 1, [null, null, 0, 'indisponivel'], [null, null, 0, 'indisponivel']],
    'missing demographic row' => ['4300005', 1, [null, null, 0, 'indisponivel'], [null, null, 0, 'indisponivel']],
    'different partial coverage by sex' => ['4300006', 5, [50, 35.714285714285715, 2, 'parcial'], [90, 64.28571428571429, 3, 'parcial']],
    'zero denominator with known zeros' => ['4300007', 1, [0, null, 1, 'completo'], [0, null, 1, 'completo']],
    'no demographic rows in the territory' => ['4300008', 2, [null, null, 0, 'indisponivel'], [null, null, 0, 'indisponivel']],
    'zero is complete while missing is unavailable' => ['4300009', 1, [0, null, 1, 'completo'], [null, null, 0, 'indisponivel']],
    'complete men and partial women' => ['4300010', 2, [0, 0.0, 2, 'completo'], [20, 100.0, 1, 'parcial']],
    'missing row with zero population still reduces coverage' => ['4300011', 2, [40, 40.0, 1, 'parcial'], [60, 60.0, 1, 'parcial']],
    'existing municipality without sectors' => ['4300000', 0, [null, null, 0, 'indisponivel'], [null, null, 0, 'indisponivel']],
    'another UF remains isolated' => ['3500001', 1, [400, 44.44444444444444, 1, 'completo'], [500, 55.55555555555556, 1, 'completo']],
]);

it('uses the sum of separate sex totals rather than the sum of rowwise additions', function (): void {
    $comparison = DB::connection('census')->selectOne(<<<'SQL'
        SELECT SUM(d.homens) + SUM(d.mulheres) AS separate_sums,
               SUM(d.homens + d.mulheres) AS rowwise_sum,
               SUM(d.moradores) AS residents
        FROM setor s LEFT JOIN demografia d ON d.cd_setor = s.cd_setor
        WHERE s.cd_mun = ?
        SQL, ['4300006']);
    expect($comparison->separate_sums)->toBe(140)
        ->and($comparison->rowwise_sum)->toBe(100)
        ->and($comparison->residents)->toBe(3996);

    $raw = app(SectorAggregationQuery::class)->forMunicipality('4300006');
    expect($raw['homens_sum'])->toBe(50)
        ->and($raw['mulheres_sum'])->toBe(90)
        ->and($raw['homens_com_valor'])->toBe(2)
        ->and($raw['mulheres_com_valor'])->toBe(3);

    $metrics = app(MunicipalCensusService::class)->find('4300006')['agregados'];
    expect($metrics['homens']['percentual'])->toEqualWithDelta(50 / 140 * 100, 1e-6)
        ->and($metrics['mulheres']['percentual'])->toEqualWithDelta(90 / 140 * 100, 1e-6)
        ->and($metrics['populacao'])->toBe(1500);
});

it('preserves every sector metric with missing and partial demography', function (): void {
    $metrics = app(MunicipalCensusService::class)->find('4300006')['agregados'];
    expect($metrics['total_setores'])->toBe(5)
        ->and($metrics['populacao'])->toBe(1500)
        ->and($metrics['area_km2'])->toEqualWithDelta(15.0, 1e-6)
        ->and($metrics['densidade_hab_km2'])->toEqualWithDelta(100.0, 1e-6)
        ->and($metrics['distribuicao'])->toBe([
            'total' => 5, 'urban' => 2, 'rural' => 1, 'unclassified' => 2,
            'urban_pct' => 40.0, 'rural_pct' => 20.0, 'unclassified_pct' => 40.0,
        ]);

    $withoutDemography = app(MunicipalCensusService::class)->find('4300008')['agregados'];
    expect($withoutDemography['total_setores'])->toBe(2)
        ->and($withoutDemography['populacao'])->toBe(600)
        ->and($withoutDemography['area_km2'])->toEqualWithDelta(2.0, 1e-6)
        ->and($withoutDemography['distribuicao']['urban'])->toBe(1)
        ->and($withoutDemography['distribuicao']['rural'])->toBe(1);
});

it('includes both anomalous municipality sectors once in their UF and demographic coverage', function (): void {
    $state = app(StateCensusService::class)->find('43');
    $metrics = $state['agregados'];
    // Known female subtotals: 60 + 60 + 90 + 0 + 20 + 60 + 3 = 293.
    expect($state['setores_sem_municipio_identificavel'])->toBe(2)
        ->and($metrics['total_setores'])->toBe(20)
        ->and($metrics['populacao'])->toBe(4900)
        ->and($metrics['area_km2'])->toEqualWithDelta(39.0, 1e-6)
        ->and($metrics['homens']['valor'])->toBe(172)
        ->and($metrics['mulheres']['valor'])->toBe(293)
        ->and($metrics['homens']['setores_com_valor'])->toBe(10)
        ->and($metrics['mulheres']['setores_com_valor'])->toBe(9)
        ->and($metrics['homens']['total_setores'])->toBe(20)
        ->and($metrics['mulheres']['total_setores'])->toBe(20)
        ->and($metrics['homens']['estado'])->toBe('parcial')
        ->and($metrics['mulheres']['estado'])->toBe('parcial')
        ->and($metrics['homens']['percentual'])->toEqualWithDelta(172 / 465 * 100, 1e-6)
        ->and($metrics['mulheres']['percentual'])->toEqualWithDelta(293 / 465 * 100, 1e-6);

    $otherState = app(StateCensusService::class)->find('35');
    expect($otherState['setores_sem_municipio_identificavel'])->toBe(0)
        ->and($otherState['agregados']['total_setores'])->toBe(1)
        ->and($otherState['agregados']['homens']['valor'])->toBe(400)
        ->and($otherState['agregados']['mulheres']['valor'])->toBe(500)
        ->and($otherState['agregados']['homens']['estado'])->toBe('completo')
        ->and($otherState['agregados']['mulheres']['estado'])->toBe('completo');
});

it('returns unavailable demographic metrics and zero unidentified sectors for an empty UF', function (): void {
    $state = app(StateCensusService::class)->find('11');
    expect($state['setores_sem_municipio_identificavel'])->toBe(0);
    foreach (['homens', 'mulheres'] as $field) {
        expect($state['agregados'][$field])->toBe([
            'valor' => null, 'percentual' => null, 'setores_com_valor' => 0,
            'total_setores' => 0, 'estado' => 'indisponivel',
        ]);
    }
});

it('counts unidentified sectors by code and name without filtering state aggregates', function (
    string $code, string $name, int $unidentified,
): void {
    // Arrange only in the temporary fixture, before opening the read-only application connection.
    $fixture = new PDO('sqlite:'.$this->databasePath);
    $fixture->exec('PRAGMA foreign_keys = ON');
    $fixture->prepare('INSERT INTO municipio VALUES (?, ?, ?)')->execute([$code, $name, '35']);
    $insert = $fixture->prepare('INSERT INTO setor VALUES (?, ?, ?, ?, ?)');
    foreach (['350999900000001', '350999900000002'] as $sectorCode) {
        $insert->execute([$sectorCode, $code, 'Urbana', 1.0, 100]);
    }
    unset($insert, $fixture);
    $this->fixtureHash = hash_file('sha256', $this->databasePath);

    $state = app(StateCensusService::class)->find('35');
    expect($state['setores_sem_municipio_identificavel'])->toBe($unidentified)
        ->and($state['agregados']['total_setores'])->toBe(3)
        ->and($state['agregados']['populacao'])->toBe(10200)
        ->and($state['agregados']['area_km2'])->toEqualWithDelta(3.0, 1e-6);
})->with([
    'short code' => ['123456', 'Nome existente', 2],
    'non-digit code' => ['123456A', 'Nome existente', 2],
    'blank trimmed name' => ['3500002', '   ', 2],
    'identifiable municipality' => ['3500002', ' Nome existente ', 0],
]);
