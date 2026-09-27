<?php

namespace App\Services;

use App\Queries\SectorAggregationQuery;
use App\Queries\StateIdentityQuery;

final class StateCensusService
{
    public function __construct(
        private StateIdentityQuery $identityQuery,
        private SectorAggregationQuery $aggregationQuery,
    ) {}

    /**
     * Composes identity and raw sector totals; this is not the complete details contract.
     *
     * @return array{cd_uf: string, nm_uf: string, agregados: array{total_setores: int, populacao: int|null, area_km2: float|null}}|null
     */
    public function find(string $code): ?array
    {
        $identity = $this->identityQuery->find($code);

        if ($identity === null) {
            return null;
        }

        return [...$identity, 'agregados' => $this->aggregationQuery->forState($identity['cd_uf'])];
    }
}
