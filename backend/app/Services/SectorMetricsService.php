<?php

namespace App\Services;

final class SectorMetricsService
{
    /**
     * @param array{total_setores: int, populacao: int|null, area_km2: float|null, urban: int, rural: int, unclassified: int} $totals
     * @return array{total_setores: int, populacao: int|null, area_km2: float|null, densidade_hab_km2: float|null, distribuicao: array{total: int, urban: int, rural: int, unclassified: int, urban_pct: float|null, rural_pct: float|null, unclassified_pct: float|null}}
     */
    public function compose(array $totals): array
    {
        $population = $totals['populacao'];
        $area = $totals['area_km2'];
        $count = $totals['total_setores'];

        return [
            'total_setores' => $count,
            'populacao' => $population,
            'area_km2' => $area,
            'densidade_hab_km2' => $population === null || $area === null || $area == 0
                ? null : $population / $area,
            'distribuicao' => [
                'total' => $count,
                'urban' => $totals['urban'],
                'rural' => $totals['rural'],
                'unclassified' => $totals['unclassified'],
                'urban_pct' => $count === 0 ? null : $totals['urban'] / $count * 100.0,
                'rural_pct' => $count === 0 ? null : $totals['rural'] / $count * 100.0,
                'unclassified_pct' => $count === 0 ? null : $totals['unclassified'] / $count * 100.0,
            ],
        ];
    }
}
