<?php

namespace App\Http\Controllers;

use App\Services\StateRankingService;

final class StateRankingController extends Controller
{
    /** @return list<array{posicao: int, cd_mun: string, nm_mun: string, populacao: int|null, area_km2: float|null, densidade_hab_km2: float|null}> */
    public function __invoke(string $cd_uf, StateRankingService $ranking): array
    {
        abort_if(preg_match('/\A[0-9]{2}\z/', $cd_uf) !== 1, 404);

        $rows = $ranking->forState($cd_uf);
        abort_if($rows === null, 404);

        return $rows;
    }
}
