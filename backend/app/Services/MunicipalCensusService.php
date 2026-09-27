<?php

namespace App\Services;

use App\Queries\MunicipalityIdentityQuery;
use App\Queries\SectorAggregationQuery;

final class MunicipalCensusService
{
    public function __construct(
        private MunicipalityIdentityQuery $identityQuery,
        private SectorAggregationQuery $aggregationQuery,
        private SectorMetricsService $metrics,
        private DemographicMetricsService $demographics,
    ) {}

    /**
     * Composes identity, sector metrics and independent demographic coverage.
     *
     * @return array{cd_mun: string, nm_mun: string, cd_uf: string, nm_uf: string, agregados: array{total_setores: int, populacao: int|null, area_km2: float|null, densidade_hab_km2: float|null, distribuicao: array{total: int, urban: int, rural: int, unclassified: int, urban_pct: float|null, rural_pct: float|null, unclassified_pct: float|null}, homens: array{valor: int|null, percentual: float|null, setores_com_valor: int, total_setores: int, estado: string}, mulheres: array{valor: int|null, percentual: float|null, setores_com_valor: int, total_setores: int, estado: string}}}|null
     */
    public function find(string $code): ?array
    {
        $identity = $this->identityQuery->find($code);

        if ($identity === null || trim($identity['nm_mun']) === '') {
            return null;
        }

        $totals = $this->aggregationQuery->forMunicipality($identity['cd_mun']);

        return [
            ...$identity,
            'agregados' => [...$this->metrics->compose($totals), ...$this->demographics->compose($totals)],
        ];
    }
}
