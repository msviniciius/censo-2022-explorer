<?php

namespace App\Queries;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;

final class SectorAggregationQuery
{
    public function __construct(private DatabaseManager $database) {}

    /** @return array{total_setores: int, populacao: int|null, area_km2: float|null} */
    public function forMunicipality(string $code): array
    {
        return $this->aggregate(
            $this->database->connection('census')->table('setor as s')
                ->where('s.cd_mun', $code),
        );
    }

    /** @return array{total_setores: int, populacao: int|null, area_km2: float|null} */
    public function forState(string $code): array
    {
        return $this->aggregate(
            $this->database->connection('census')->table('setor as s')
                ->join('municipio as m', 'm.cd_mun', '=', 's.cd_mun')
                ->where('m.cd_uf', $code),
        );
    }

    /** @return array{total_setores: int, populacao: int|null, area_km2: float|null} */
    private function aggregate(Builder $sectors): array
    {
        // Sector totals do not depend on demography. The municipality join is many-to-one.
        // Keep equal values from distinct sectors; SUM(DISTINCT value) would lose them.
        $totals = $sectors->selectRaw('
            COUNT(s.cd_setor) AS total_setores,
            SUM(s.populacao) AS populacao,
            SUM(s.area_km2) AS area_km2
        ')->first();

        return [
            'total_setores' => (int) $totals->total_setores,
            'populacao' => $totals->populacao === null ? null : (int) $totals->populacao,
            'area_km2' => $totals->area_km2 === null ? null : (float) $totals->area_km2,
        ];
    }
}
