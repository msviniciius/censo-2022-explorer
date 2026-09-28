<?php

namespace App\Queries;

use Illuminate\Database\DatabaseManager;

final class StateRankingQuery
{
    public function __construct(private DatabaseManager $database) {}

    /** @return array{total: int, items: list<array{posicao: int, cd_mun: string, nm_mun: string, populacao: int|null, area_km2: float|null, densidade_hab_km2: float|null}>} */
    public function forState(string $code, int $page, int $perPage): array
    {
        $offset = $page - 1 > intdiv(PHP_INT_MAX, $perPage)
            ? PHP_INT_MAX
            : ($page - 1) * $perPage;

        $rows = $this->database->connection('census')->select(<<<'SQL'
            WITH eligible AS (
                SELECT m.cd_mun, m.nm_mun
                FROM municipio m
                WHERE m.cd_uf = ?
                  AND LENGTH(m.cd_mun) = 7
                  AND m.cd_mun NOT GLOB '*[^0-9]*'
                  AND TRIM(m.nm_mun) <> ''
            ), sector_totals AS (
                SELECT s.cd_mun,
                       SUM(s.populacao) AS populacao,
                       SUM(s.area_km2) AS area_km2
                FROM setor s
                JOIN eligible e ON e.cd_mun = s.cd_mun
                GROUP BY s.cd_mun
            ), metrics AS (
                SELECT e.cd_mun, e.nm_mun, t.populacao, t.area_km2,
                       CAST(t.populacao AS REAL) / NULLIF(t.area_km2, 0) AS densidade_hab_km2
                FROM eligible e
                LEFT JOIN sector_totals t ON t.cd_mun = e.cd_mun
            ), ranked AS (
                SELECT ROW_NUMBER() OVER (
                    ORDER BY CASE WHEN densidade_hab_km2 IS NULL THEN 1 ELSE 0 END,
                             densidade_hab_km2 DESC,
                             cd_mun ASC
                ) AS posicao,
                cd_mun, nm_mun, populacao, area_km2, densidade_hab_km2
                FROM metrics
            ), total AS (
                SELECT COUNT(*) AS total FROM eligible
            ), page AS (
                SELECT posicao, cd_mun, nm_mun, populacao, area_km2, densidade_hab_km2
                FROM ranked
                ORDER BY posicao
                LIMIT ? OFFSET ?
            )
            SELECT total.total, page.posicao, page.cd_mun, page.nm_mun,
                   page.populacao, page.area_km2, page.densidade_hab_km2
            FROM total
            LEFT JOIN page ON 1 = 1
            ORDER BY page.posicao
            SQL, [$code, $perPage, $offset]);

        $items = [];
        foreach ($rows as $row) {
            if ($row->cd_mun === null) {
                continue;
            }

            $items[] = [
                'posicao' => (int) $row->posicao,
                'cd_mun' => (string) $row->cd_mun,
                'nm_mun' => (string) $row->nm_mun,
                'populacao' => $row->populacao === null ? null : (int) $row->populacao,
                'area_km2' => $row->area_km2 === null ? null : (float) $row->area_km2,
                'densidade_hab_km2' => $row->densidade_hab_km2 === null ? null : (float) $row->densidade_hab_km2,
            ];
        }

        return ['total' => (int) $rows[0]->total, 'items' => $items];
    }
}
