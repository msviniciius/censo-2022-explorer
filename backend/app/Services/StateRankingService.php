<?php

namespace App\Services;

use App\Queries\StateIdentityQuery;
use App\Queries\StateRankingQuery;

final class StateRankingService
{
    public function __construct(
        private StateIdentityQuery $identity,
        private StateRankingQuery $ranking,
    ) {}

    /** @return list<array{posicao: int, cd_mun: string, nm_mun: string, populacao: int|null, area_km2: float|null, densidade_hab_km2: float|null}>|null */
    public function forState(string $code): ?array
    {
        if ($this->identity->find($code) === null) {
            return null;
        }

        return $this->ranking->forState($code);
    }
}
