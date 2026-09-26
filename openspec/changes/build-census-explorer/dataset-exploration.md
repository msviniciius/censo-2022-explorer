# Exploração do dataset

Conferência direta de `censo.sqlite` em 2026-09-26, usando conexão SQLite `mode=ro` e `PRAGMA query_only=ON`. Referências externas limitadas aos números fornecidos pelo desafio. Nenhuma hipótese sobre a origem dos registros ou das lacunas.

SHA-256 antes e depois: `f429226fa3372fde8b4cd22a32cb2a3fc24533b5fb340249b83867d3b485086a`.

## Contagens e totais nacionais

```sql
SELECT (SELECT COUNT(*) FROM uf) AS uf,
       (SELECT COUNT(*) FROM municipio) AS municipio,
       (SELECT COUNT(*) FROM setor) AS setor,
       (SELECT COUNT(*) FROM demografia) AS demografia;
SELECT SUM(populacao) AS populacao, SUM(area_km2) AS area_km2,
       SUM(populacao)-203080756 AS diferenca_populacao,
       SUM(area_km2)-8510417 AS diferenca_area_km2
FROM setor;
```

| Tabela | Registros |
|---|---:|
| uf | 27 |
| municipio | 5.571 |
| setor | 468.099 |
| demografia | 458.772 |

População: **203.080.756**, igual à referência. Área: **8.510.417,247266822 km²**, aproximadamente **0,247266822 km²** acima dos 8.510.417 km² de referência; o arredondamento ao inteiro coincide. Preservar a precisão da base nos cálculos.

## O registro municipal adicional

```sql
SELECT m.cd_mun,m.nm_mun,m.cd_uf,u.nm_uf
FROM municipio m JOIN uf u ON u.cd_uf=m.cd_uf
WHERE LENGTH(m.cd_mun)<>7 OR m.cd_mun GLOB '*[^0-9]*'
   OR TRIM(m.nm_mun)='';
SELECT * FROM setor WHERE cd_mun='.';
```

A primeira consulta retorna somente `cd_mun='.'`, `nm_mun=''`, `cd_uf='43'` (Rio Grande do Sul). Os outros 5.570 registros não atendem a esse predicado diagnóstico. Isso identifica a diferença de contagem, mas não estabelece o significado do registro.

| Setor associado | Situação | População | Área km² |
|---|---|---:|---:|
| 430000100000000 | NULL | 0 | 2.884,3399222 |
| 430000200000000 | NULL | 0 | 10.201,5241788 |

Os dois setores somam **13.085,864101 km²**. Excluir o registro dos agregados perderia essa área. Não remover nem corrigir dados; manter sua participação nos totais nacional/estadual. A regra já aprovada de municípios selecionáveis permanece distinta da preservação integral do dataset. Nenhum filtro novo é proposto por esta exploração.

## NULLs, situação e cobertura demográfica

```sql
SELECT SUM(populacao IS NULL) AS populacao_null,
       SUM(area_km2 IS NULL) AS area_km2_null,
       SUM(situacao IS NULL) AS situacao_null FROM setor;
SELECT SUM(moradores IS NULL) AS moradores_null,
       SUM(homens IS NULL) AS homens_null,
       SUM(mulheres IS NULL) AS mulheres_null FROM demografia;
SELECT situacao, COUNT(*) AS setores, SUM(populacao) AS populacao
FROM setor GROUP BY situacao ORDER BY situacao;
SELECT COUNT(*) AS setores_sem_demografia
FROM setor s LEFT JOIN demografia d ON d.cd_setor=s.cd_setor
WHERE d.cd_setor IS NULL;
```

| Campo | NULLs |
|---|---:|
| setor.populacao | 0 |
| setor.area_km2 | 0 |
| setor.situacao | 1.103 |
| demografia.moradores | 8.684 |
| demografia.homens | 8.691 |
| demografia.mulheres | 8.733 |

| Situação | Setores | População |
|---|---:|---:|
| Urbana | 354.965 | 177.508.417 |
| Rural | 112.031 | 25.572.339 |
| NULL | 1.103 | 0 |

A distribuição urbana/rural usa a coluna **Setores**: `urban + rural + unclassified = 354.965 + 112.031 + 1.103 = 468.099`. Cada percentual é a contagem da categoria / total de setores × 100. A coluna População acima é um resultado bruto da exploração, preservado para referência; não é usada para calcular essa distribuição.

Há **9.327 setores sem linha correspondente em demografia**. Essa ausência de linha é diferente dos NULLs contados nas linhas existentes. `setor.situacao IS NULL` será `unclassified` (rótulo “Não informada”); nunca será convertido silenciosamente em Rural.

## Somas e percentuais por sexo

```sql
SELECT SUM(moradores) AS moradores, SUM(homens) AS homens,
       SUM(mulheres) AS mulheres,
       SUM(homens)+SUM(mulheres) AS denominador_sexo,
       SUM(homens+mulheres) AS soma_por_linha
FROM demografia;
```

| Expressão | Resultado |
|---|---:|
| SUM(moradores) | 202.561.627 |
| SUM(homens) | 98.086.826 |
| SUM(mulheres) | 104.474.699 |
| SUM(homens) + SUM(mulheres) | 202.561.525 |
| SUM(homens + mulheres), somente para comparação | 202.561.116 |

A população setorial supera `SUM(moradores)` em **519.129** e a soma separada dos sexos em **519.231**; `SUM(moradores)` supera esta última em **102**. São diferenças observadas, sem causa atribuída.

Por território, percentual de homens = `100 × SUM(homens) / (SUM(homens) + SUM(mulheres))`; mulheres usa a mesma base. **Não usar a população setorial, SUM(moradores) nem SUM(homens + mulheres) como denominador.** Denominador nulo ou zero produz percentuais nulos; não imputar ausências como zero. Apresentar os percentuais como distribuição dos valores demográficos conhecidos, mantendo os avisos de cobertura parcial.

## Homônimos entre UFs

```sql
SELECT COUNT(*) AS nomes_em_mais_de_uma_uf FROM (
  SELECT nm_mun FROM municipio GROUP BY nm_mun
  HAVING COUNT(DISTINCT cd_uf)>1
);
SELECT m.nm_mun,m.cd_mun,u.cd_uf,u.nm_uf
FROM municipio m JOIN uf u ON u.cd_uf=m.cd_uf
WHERE m.nm_mun IN ('Bom Jesus','Santa Helena','São Domingos')
ORDER BY m.nm_mun,u.cd_uf,m.cd_mun;
```

São **232 nomes exatamente iguais presentes em mais de uma UF**. Exemplos do resultado:

| Nome | Código municipal / UF | Código municipal / UF |
|---|---|---|
| Bom Jesus | 2201903 / Piauí (22) | 2401701 / Rio Grande do Norte (24) |
| Santa Helena | 2109809 / Maranhão (21) | 2513307 / Paraíba (25) |
| São Domingos | 2513968 / Paraíba (25) | 2806800 / Sergipe (28) |

O autocomplete deve mostrar a UF junto ao nome. A seleção continua por `cd_mun`, nunca apenas por nome. A base fornece nome e código da UF; não é necessário acrescentar siglas externas.

## Registros municipais por UF e paginação

```sql
SELECT u.cd_uf,u.nm_uf,COUNT(m.cd_mun) AS registros_municipio
FROM uf u LEFT JOIN municipio m ON m.cd_uf=u.cd_uf
GROUP BY u.cd_uf,u.nm_uf ORDER BY registros_municipio DESC,u.cd_uf;
```

Consulta executada para todas as 27 UFs, sem excluir registros. Extremos e caso relevante:

| UF | Registros municipais |
|---|---:|
| Minas Gerais (31) | 853 |
| São Paulo (35) | 645 |
| Rio Grande do Sul (43) | 498, incluindo o registro `.` |
| Amapá (16) | 16 |
| Roraima (14) | 15 |
| Distrito Federal (53) | 1 |

O máximo é Minas Gerais; o mínimo entre todas as UFs é o Distrito Federal e, entre os estados, Roraima. Com `per_page=25` (padrão), Minas Gerais exige 35 páginas para seus 853 registros. As opções de tamanho são 25, 50 e 100. Os volumes sustentam a decisão existente de **filtrar por UF, ordenar por densidade e paginar no servidor**, antes de enviar resultados à tela.

## Decisões consolidadas

- `censo.sqlite` permanece a fonte de verdade, sem exclusões arbitrárias.
- População = `SUM(setor.populacao)`; área = `SUM(setor.area_km2)`; densidade = população / área, preservando a regra existente para área zero.
- Distribuição urbana/rural por quantidade de setores: `total = COUNT(setor.cd_setor)`; contar `urban` para Urbana, `rural` para Rural e `unclassified` para NULL ou categoria não reconhecida. A soma das três contagens é igual ao total; cada percentual é contagem / total × 100. Sem setores, contagens zero e percentuais nulos. População permanece independente.
- Situação NULL = `unclassified` (Não informada); nenhuma imputação para Rural.
- Percentuais por sexo usam `SUM(homens) + SUM(mulheres)` do território, com cobertura explícita.
- Autocomplete identifica a UF e seleciona por `cd_mun`.
- Ranking estadual aplica filtro por UF e paginação server-side.

As decisões de contrato estão refletidas em `design.md` e na spec `census-aggregation`; as verificações correspondentes permanecem pendentes em `tasks.md`. Nenhuma implementação foi iniciada.
