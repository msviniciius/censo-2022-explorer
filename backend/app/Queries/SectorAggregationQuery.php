<?php

namespace App\Queries;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;

final class SectorAggregationQuery
{
    public function __construct(private DatabaseManager $database) {}

    /** @return array{total_setores: int, populacao: int|null, area_km2: float|null, urban: int, rural: int, unclassified: int, homens_sum: int|null, mulheres_sum: int|null, homens_com_valor: int, mulheres_com_valor: int} */
    public function forMunicipality(string $code): array
    {
        return $this->aggregate(
            $this->database->connection('census')->table('setor as s')
                ->where('s.cd_mun', $code),
        );
    }

    /** @return array{total_setores: int, populacao: int|null, area_km2: float|null, urban: int, rural: int, unclassified: int, homens_sum: int|null, mulheres_sum: int|null, homens_com_valor: int, mulheres_com_valor: int, setores_sem_municipio_identificavel: int} */
    public function forState(string $code): array
    {
        return $this->aggregate(
            $this->database->connection('census')->table('setor as s')
                ->join('municipio as m', 'm.cd_mun', '=', 's.cd_mun')
                ->where('m.cd_uf', $code)
                ->selectRaw(<<<'SQL'
                    COUNT(CASE WHEN LENGTH(m.cd_mun) <> 7 OR m.cd_mun GLOB '*[^0-9]*'
                        OR TRIM(m.nm_mun) = '' THEN s.cd_setor END) AS setores_sem_municipio_identificavel
                    SQL),
        );
    }

    /** @return array{total_setores: int, populacao: int|null, area_km2: float|null, urban: int, rural: int, unclassified: int, homens_sum: int|null, mulheres_sum: int|null, homens_com_valor: int, mulheres_com_valor: int, setores_sem_municipio_identificavel?: int} */
    private function aggregate(Builder $sectors): array
    {
        // Demography has at most one row per sector (cd_setor primary key).
        // LEFT JOIN preserves sectors without demography; municipality is many-to-one.
        // Keep equal values from distinct sectors; SUM(DISTINCT value) would lose them.
        $totals = $sectors->leftJoin('demografia as d', 'd.cd_setor', '=', 's.cd_setor')
            ->selectRaw(<<<'SQL'
            COUNT(s.cd_setor) AS total_setores,
            SUM(s.populacao) AS populacao,
            SUM(s.area_km2) AS area_km2,
            COUNT(CASE WHEN s.situacao = 'Urbana' THEN s.cd_setor END) AS urban,
            COUNT(CASE WHEN s.situacao = 'Rural' THEN s.cd_setor END) AS rural,
            COUNT(CASE WHEN s.situacao IS NULL OR s.situacao NOT IN ('Urbana', 'Rural')
                THEN s.cd_setor END) AS unclassified,
            SUM(d.homens) AS homens_sum,
            SUM(d.mulheres) AS mulheres_sum,
            COUNT(d.homens) AS homens_com_valor,
            COUNT(d.mulheres) AS mulheres_com_valor
            SQL)->first();

        $result = [
            'total_setores' => (int) $totals->total_setores,
            'populacao' => $totals->populacao === null ? null : (int) $totals->populacao,
            'area_km2' => $totals->area_km2 === null ? null : (float) $totals->area_km2,
            'urban' => (int) $totals->urban,
            'rural' => (int) $totals->rural,
            'unclassified' => (int) $totals->unclassified,
            'homens_sum' => $totals->homens_sum === null ? null : (int) $totals->homens_sum,
            'mulheres_sum' => $totals->mulheres_sum === null ? null : (int) $totals->mulheres_sum,
            'homens_com_valor' => (int) $totals->homens_com_valor,
            'mulheres_com_valor' => (int) $totals->mulheres_com_valor,
        ];

        if (isset($totals->setores_sem_municipio_identificavel)) {
            $result['setores_sem_municipio_identificavel'] = (int) $totals->setores_sem_municipio_identificavel;
        }

        return $result;
    }
}
