-- Independent audit oracle. Bind exactly one of :municipality / :state as TEXT,
-- and the other as NULL. No application Query or Service generates this SQL.
WITH identity AS (
    SELECT m.cd_mun, m.nm_mun, u.cd_uf, u.nm_uf
    FROM municipio m JOIN uf u ON u.cd_uf = m.cd_uf
    WHERE m.cd_mun = :municipality
    UNION ALL
    SELECT NULL, NULL, u.cd_uf, u.nm_uf FROM uf u WHERE u.cd_uf = :state
), territory AS (
    SELECT s.*, m.nm_mun
    FROM setor s
    JOIN municipio m ON m.cd_mun = s.cd_mun
    JOIN uf u ON u.cd_uf = m.cd_uf
    WHERE (:municipality IS NOT NULL AND m.cd_mun = :municipality)
       OR (:state IS NOT NULL AND u.cd_uf = :state)
), sector_totals AS (
    -- No demographic join: every original sector contributes once.
    SELECT COUNT(*) AS total, SUM(populacao) AS population, SUM(area_km2) AS area,
           COUNT(*) FILTER (WHERE situacao = 'Urbana') AS urban,
           COUNT(*) FILTER (WHERE situacao = 'Rural') AS rural,
           COUNT(*) FILTER (WHERE NOT (
               LENGTH(cd_mun) = 7 AND cd_mun NOT GLOB '*[^0-9]*' AND TRIM(nm_mun) <> ''
           )) AS unidentified,
           COUNT(*) FILTER (WHERE cd_mun = '.') AS dot_sectors,
           SUM(populacao) FILTER (WHERE cd_mun = '.') AS dot_population,
           SUM(area_km2) FILTER (WHERE cd_mun = '.') AS dot_area
    FROM territory
), demographic_totals AS (
    -- Aggregate demographic columns independently, restricting membership by sector key.
    SELECT SUM(d.homens) AS men, SUM(d.mulheres) AS women,
           COUNT(d.homens) AS men_known, COUNT(d.mulheres) AS women_known,
           COUNT(*) AS demographic_rows
    FROM demografia d
    WHERE d.cd_setor IN (SELECT cd_setor FROM territory)
)
SELECT i.cd_mun, i.nm_mun, i.cd_uf, i.nm_uf,
       t.total AS "agregados.total_setores",
       t.population AS "agregados.populacao",
       t.area AS "agregados.area_km2",
       CAST(t.population AS REAL) / NULLIF(t.area, 0) AS "agregados.densidade_hab_km2",
       t.total AS "agregados.distribuicao.total",
       t.urban AS "agregados.distribuicao.urban",
       t.rural AS "agregados.distribuicao.rural",
       t.total - t.urban - t.rural AS "agregados.distribuicao.unclassified",
       100.0 * t.urban / NULLIF(t.total, 0) AS "agregados.distribuicao.urban_pct",
       100.0 * t.rural / NULLIF(t.total, 0) AS "agregados.distribuicao.rural_pct",
       100.0 * (t.total - t.urban - t.rural) / NULLIF(t.total, 0) AS "agregados.distribuicao.unclassified_pct",
       d.men AS "agregados.homens.valor",
       100.0 * d.men / NULLIF(d.men + d.women, 0) AS "agregados.homens.percentual",
       d.men_known AS "agregados.homens.setores_com_valor",
       t.total AS "agregados.homens.total_setores",
       CASE WHEN t.total = 0 OR d.men_known = 0 THEN 'indisponivel'
            WHEN d.men_known = t.total THEN 'completo' ELSE 'parcial' END AS "agregados.homens.estado",
       d.women AS "agregados.mulheres.valor",
       100.0 * d.women / NULLIF(d.men + d.women, 0) AS "agregados.mulheres.percentual",
       d.women_known AS "agregados.mulheres.setores_com_valor",
       t.total AS "agregados.mulheres.total_setores",
       CASE WHEN t.total = 0 OR d.women_known = 0 THEN 'indisponivel'
            WHEN d.women_known = t.total THEN 'completo' ELSE 'parcial' END AS "agregados.mulheres.estado",
       t.unidentified AS setores_sem_municipio_identificavel,
       d.men + d.women AS "audit.total_sexo",
       t.total - d.demographic_rows AS "audit.setores_sem_demografia",
       t.dot_sectors AS "audit.dot_setores",
       t.dot_population AS "audit.dot_populacao",
       t.dot_area AS "audit.dot_area_km2"
FROM identity i CROSS JOIN sector_totals t CROSS JOIN demographic_totals d;
