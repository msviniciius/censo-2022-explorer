<?php

namespace App\Services;

use App\Queries\SectorAggregationQuery;
use App\Queries\StateIdentityQuery;

final class StateCensusService
{
    public function __construct(
        private StateIdentityQuery $identityQuery,
        private SectorAggregationQuery $aggregationQuery,
        private SectorMetricsService $metrics,
    ) {}

    /**
     * Composes identity and sector metrics; demographic metrics are not included yet.
     *
     * @return array{cd_uf: string, nm_uf: string, agregados: array{total_setores: int, populacao: int|null, area_km2: float|null, densidade_hab_km2: float|null, distribuicao: array{total: int, urban: int, rural: int, unclassified: int, urban_pct: float|null, rural_pct: float|null, unclassified_pct: float|null}}}|null
     */
    public function find(string $code): ?array
    {
        $identity = $this->identityQuery->find($code);

        if ($identity === null) {
            return null;
        }

        return [
            ...$identity,
            'agregados' => $this->metrics->compose($this->aggregationQuery->forState($identity['cd_uf'])),
        ];
    }
}
