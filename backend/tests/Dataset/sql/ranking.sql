-- Independent ranking oracle. Aggregates sectors by joining them to municipal
-- identity first; production instead starts with the eligible municipality CTE.
WITH eligible_municipalities AS (
    SELECT m.cd_mun, m.nm_mun
    FROM municipio m
    JOIN uf u ON u.cd_uf = m.cd_uf
    WHERE u.cd_uf = :uf
      AND LENGTH(m.cd_mun) = 7
      AND m.cd_mun NOT GLOB '*[^0-9]*'
      AND TRIM(m.nm_mun) <> ''
), state_sector_totals AS (
    SELECT s.cd_mun, SUM(s.populacao) AS population_sum, SUM(s.area_km2) AS area_sum
    FROM setor s
    JOIN municipio m ON m.cd_mun = s.cd_mun
    JOIN uf u ON u.cd_uf = m.cd_uf
    WHERE u.cd_uf = :uf
      AND LENGTH(m.cd_mun) = 7
      AND m.cd_mun NOT GLOB '*[^0-9]*'
      AND TRIM(m.nm_mun) <> ''
    GROUP BY s.cd_mun
), results AS (
    SELECT e.cd_mun, e.nm_mun, t.population_sum, t.area_sum,
           CASE WHEN t.population_sum IS NULL OR t.area_sum IS NULL OR t.area_sum = 0
                THEN NULL ELSE 1.0 * t.population_sum / t.area_sum END AS density
    FROM eligible_municipalities e
    LEFT JOIN state_sector_totals t ON t.cd_mun = e.cd_mun
)
SELECT cd_mun, nm_mun, population_sum, area_sum, density
FROM results
ORDER BY CASE WHEN density IS NULL THEN 1 ELSE 0 END, density DESC, cd_mun ASC;
