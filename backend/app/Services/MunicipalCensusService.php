<?php

namespace App\Services;

use App\Queries\MunicipalityIdentityQuery;
use App\Queries\SectorAggregationQuery;

final class MunicipalCensusService
{
    public function __construct(
        private MunicipalityIdentityQuery $identityQuery,
        private SectorAggregationQuery $aggregationQuery,
    ) {}

    /**
     * Composes identity and raw sector totals; this is not the complete details contract.
     *
     * @return array{cd_mun: string, nm_mun: string, cd_uf: string, nm_uf: string, agregados: array{total_setores: int, populacao: int|null, area_km2: float|null}}|null
     */
    public function find(string $code): ?array
    {
        $identity = $this->identityQuery->find($code);

        if ($identity === null) {
            return null;
        }

        return [...$identity, 'agregados' => $this->aggregationQuery->forMunicipality($identity['cd_mun'])];
    }
}
