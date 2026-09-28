# Agregação censitária: contrato interno e auditoria

Implementação das tasks 2.1–2.4; auditoria reproduzível da task 2.5.
As decisões são as do [design OpenSpec](../openspec/changes/build-census-explorer/design.md).

## Fonte e responsabilidades

`censo.sqlite` é o snapshot imutável. Queries usam a conexão `census` somente
leitura; Services compõem os resultados e calculam derivados, sem DB/PDO/Query
Builder. Identidade é consultada em `municipio`/`uf`, independentemente de setores.
A agregação parte de `setor`, com `LEFT JOIN demografia` pela PK `cd_setor`:
no máximo uma linha demográfica por setor. Cada setor contribui uma vez, mesmo
sem demografia; não se usa `SUM(DISTINCT valor)`.

Na UF, todos os setores vinculados via município participam. O contador
`setores_sem_municipio_identificavel` conta setores cujo município tem código
sem sete dígitos ASCII ou nome vazio após `TRIM` do SQLite. Essa contagem não
filtra os agregados. O registro `.` não é um município selecionável válido,
mas seus dois setores continuam pertencendo à UF `43`; não se atribui significado
à anomalia.

## Fórmulas e NULLs

| Métrica | Cálculo |
|---|---|
| População | `SUM(setor.populacao)` |
| Área (km²) | `SUM(setor.area_km2)` |
| Densidade (hab/km²) | população agregada / área agregada |
| Total de setores | `COUNT(setor.cd_setor)` |
| `urban` | contagem de setores com `situacao = 'Urbana'` |
| `rural` | contagem de setores com `situacao = 'Rural'` |
| `unclassified` | contagem dos demais, incluindo NULL; rótulo futuro “Não informada” |
| Percentual territorial | contagem da categoria / total de setores × 100 |
| Homens / mulheres | `SUM(demografia.homens)` / `SUM(demografia.mulheres)`, separadamente |
| Denominador por sexo | soma de homens + soma de mulheres, após agregar cada coluna |
| Percentual por sexo | soma do sexo / denominador por sexo × 100 |

Densidade usa totais, nunca `AVG` de densidades setoriais ou municipais.
A distribuição territorial mede **setores**, não população por situação, e
`urban + rural + unclassified = distribuicao.total = total_setores`.
O denominador por sexo não usa `SUM(homens + mulheres)`, `SUM(moradores)` ou
população setorial. Ele é interno ao cálculo, sem campo `total_sexo` no DTO.
Não há arredondamento interno; números e códigos não recebem formatação local.

- `SUM` ignora NULLs quando existem valores conhecidos; sem valores conhecidos,
  retorna `null`. População e área são somadas independentemente, inclusive
  quando as lacunas ocorrem em setores diferentes. Não há cobertura adicional
  de população/área no contrato atual.
- Densidade é `null` se população ou área agregada for nula, ou se área for zero.
- Ausência demográfica não remove setores nem preenche valores com zero,
  moradores, população ou o outro sexo. Um zero conhecido permanece zero.
- Se uma soma por sexo for nula, o denominador também é nulo. Denominador nulo
  ou zero produz ambos os percentuais nulos.
- Sem setores: população, área e densidade nulas; contagens territoriais zero;
  percentuais territoriais nulos; cada sexo com valor/percentual nulos,
  cobertura `0/0` e estado `indisponivel`.
- Com setores, categoria territorial ausente tem contagem e percentual zero.
  Situação nula/desconhecida nunca é convertida para Rural.

## Cobertura demográfica

Para cada sexo, `setores_com_valor = COUNT(demografia.campo)` inclui zeros
conhecidos; `total_setores` inclui todos os setores do território, inclusive os
sem linha demográfica ou com população zero. A cobertura de um sexo não depende
do outro e não estima população faltante.

| Condição | `estado` | `valor` |
|---|---|---|
| Total zero ou nenhum setor com valor conhecido | `indisponivel` | `null` |
| Conhecidos = total > 0 | `completo` | soma conhecida |
| 0 < conhecidos < total | `parcial` | soma conhecida |

Os percentuais descrevem valores demográficos conhecidos; não eliminam o aviso
de parcialidade. A soma de homens/mulheres pode divergir da população setorial.

## DTO atual dos Services

Os retornos são arrays PHP tipados nos PHPDocs, com códigos em strings e números
sem formatação. Ambos retornam `null` quando a identidade não existe.

```text
MunicipalCensusService::find(string cd_mun)
  { cd_mun: string, nm_mun: string, cd_uf: string, nm_uf: string,
    agregados: Agregados } | null

StateCensusService::find(string cd_uf)
  { cd_uf: string, nm_uf: string, agregados: Agregados,
    setores_sem_municipio_identificavel: int } | null

Agregados
  total_setores: int
  populacao: int | null
  area_km2: float | null
  densidade_hab_km2: float | null
  distribuicao:
    total, urban, rural, unclassified: int
    urban_pct, rural_pct, unclassified_pct: float | null
  homens: Demografia
  mulheres: Demografia

Demografia
  valor: int | null
  percentual: float | null
  setores_com_valor: int
  total_setores: int
  estado: "completo" | "parcial" | "indisponivel"
```

Este é o contrato **interno**, sem envelope `data` ou status HTTP. Na task 3.2,
o endpoint municipal envolve o DTO em `data`, sem renomear campos nem recalcular
métricas; [contrato HTTP e exemplos](municipal-api.md). O Service municipal retorna
`null` também para identidade com nome vazio após trim, antes de agregar; a rota
exige sete dígitos ASCII. A Query de identidade continua independente de setores.
A Query de autocomplete aplica sua própria elegibilidade. O agregado estadual
continua incluindo `.`; a task 4.1 expõe esse DTO em `GET /api/ufs/{cd_uf}` e
lista as UFs em `GET /api/ufs`, conforme o [contrato HTTP estadual](state-api.md).
Ranking e interface estadual seguem pendentes.

## Particularidades confirmadas do snapshot

A [exploração](../openspec/changes/build-census-explorer/dataset-exploration.md)
registra as consultas originais e não atribui causas às lacunas:

- 27 UFs, 5.571 registros municipais, 468.099 setores e 458.772 linhas demográficas;
  9.327 setores sem linha demográfica.
- NULLs: situação 1.103; moradores 8.684; homens 8.691; mulheres 8.733.
  Não há NULLs em população/área setorial nesse snapshot; fixtures cobrem esses casos.
- `cd_mun='.'`, nome vazio, UF `43`, dois setores com população zero e área somada
  de 13.085,864101 km². Nenhum registro foi removido ou corrigido.
- População nacional 203.080.756; área aproximadamente 8.510.417,247266822 km².
- Nacionalmente, somas separadas por sexo totalizam 202.561.525; a soma por linha
  retorna 202.561.116. São expressões diferentes na presença de NULLs.
- A tabela `uf` fornece nome/código, sem sigla; há 232 nomes municipais
  compartilhados entre UFs. Não há índices secundários nem otimizações novas.

## Executar a auditoria do dataset real

Na raiz, com os mesmos pré-requisitos Docker/Compose do README:

```sh
# Suíte normal: fixtures temporárias, sem montar censo.sqlite.
docker compose --profile test run --build --rm backend-test

sha256sum censo.sqlite
docker compose --profile audit run --build --rm dataset-audit
sha256sum censo.sqlite
```

O perfil `audit` reutiliza o estágio de testes PHP e monta somente o banco como
read-only, com `create_host_path: false`. `phpunit.dataset.xml` executa apenas
`tests/Dataset`; a configuração normal permanece limitada a `tests/Feature`.
A auditoria falha com mensagem explícita se o arquivo estiver ausente ou seu hash
não corresponder ao snapshot. Não faz skip silencioso.

O lado A chama os Services reais. O lado B executa
[`territory.sql`](../backend/tests/Dataset/sql/territory.sql) por PDO independente,
sem reutilizar Queries ou Services. Setores e demografia são agregados em CTEs
separadas; a UF é delimitada por `setor → municipio → uf`, sem excluir `.`.
O SQL também calcula densidade, percentuais e estados de cobertura.
Os parâmetros são `:municipality='1100015', :state=NULL`, ou
`:municipality=NULL, :state='43'`.

O teste compara todos os campos e tipos do DTO, com igualdade exata para
identidades/contagens/NULLs e tolerância absoluta `1e-6` para números reais.
Verifica também as referências do design, a partição territorial, o denominador
por sexo e os dois setores de `.`. Divergências identificam território, campo,
valor SQL e valor da aplicação. Linhas `AUDIT_JSON` registram os dois resultados
e tempos de execução em milissegundos, sem arredondar os valores comparados.

Ambas as conexões abrem com `SQLITE_OPEN_READONLY` e `query_only` local à conexão.
Não há tentativa de escrita, migration, seed, índice, view ou PRAGMA persistente.
O SHA-256 é conferido antes/depois de cada caso, inclusive em falha de comparação:

```text
f429226fa3372fde8b4cd22a32cb2a3fc24533b5fb340249b83867d3b485086a
```

A auditoria cobre dois territórios reais e complementa as fixtures de borda;
não representa auditoria de todos os territórios, benchmark ou aceite HTTP.


## Resultado observado em 2026-09-27

Suíte normal: **62 testes / 437 assertions**. Auditoria real: **2 testes / 102
assertions**, ambos aprovados. Identidades confirmadas: `1100015`, Alta Floresta
D'Oeste, `11`, Rondônia; e `43`, Rio Grande do Sul.

Valores da aplicação abaixo, comparados campo a campo com o SQL independente.
O ponto é separador decimal nesta tabela; os números não receberam formatação
pt-BR nem arredondamento para apresentação da interface.

| Campo | Município 1100015 | UF 43 |
|---|---:|---:|
| total_setores | 85 | 25569 |
| populacao | 21494 | 10882965 |
| area_km2 | 7067.1267818999995 | 281707.1504883004 |
| densidade_hab_km2 | 3.041405745691367 | 38.6321929746401 |
| urban | 26 | 19436 |
| rural | 59 | 6112 |
| unclassified | 0 | 21 |
| urban_pct | 30.58823529411765 | 76.01392311001604 |
| rural_pct | 69.41176470588235 | 23.903946184833195 |
| unclassified_pct | 0.0 | 0.08213070515076851 |
| homens.valor | 10744 | 5237692 |
| mulheres.valor | 10601 | 5624301 |
| homens.percentual | 50.33497306160694 | 48.22035882365234 |
| mulheres.percentual | 49.66502693839306 | 51.77964117634766 |
| homens.setores_com_valor | 67 | 24874 |
| mulheres.setores_com_valor | 67 | 24869 |
| total_setores de cada sexo | 85 | 25569 |
| homens.estado | parcial | parcial |
| mulheres.estado | parcial | parcial |
| Denominador por sexo (interno) | 21345 | 10861993 |
| Setores sem linha demográfica (diagnóstico SQL) | 5 | 269 |
| setores_sem_municipio_identificavel | não integra o DTO municipal | 2 |

As contagens territoriais somam 85 e 25.569, respectivamente. Os dois setores de
`.` estão incluídos na UF, somando população zero e área de 13.085,864101 km².
A cobertura é parcial em ambos os sexos nos dois territórios; setores sem linha
não são a única lacuna, pois há também valores nulos em linhas existentes.

Identidades, contagens, somas inteiras e estados coincidiram exatamente. Área,
densidade e percentuais territoriais também coincidiram nesta execução. Houve
diferença de aproximadamente `7.1e-15` nos percentuais por sexo devido à ordem das
operações de ponto flutuante (SQL multiplica por 100 antes de dividir; aplicação
divide antes). Está abaixo da tolerância `1e-6`; nenhuma regra ou Query foi alterada.
A área municipal `7067.1267818999995` também concorda com a referência
`7067.1267819` do design dentro da tolerância.

Tempos de uma única execução local em containers PHP 8.4, sem aquecimento
controlado ou caracterização de hardware:

| Território | SQL independente (ms) | Services (ms) |
|---|---:|---:|
| municipio 1100015 | 312.031994 | 69.90288 |
| uf 43 | 354.396239 | 238.778296 |

Esses tempos incluem execução/fetch e, no lado da aplicação, resolução dos
Services. São evidência desta execução, não SLA, benchmark ou justificativa para
índices/cache. O hash antes/depois foi idêntico ao esperado. Não foram necessárias
correções na camada de agregação. A task 2.5 permanece desmarcada para revisão.


## Preparação interna do ranking estadual — task 4.2

`StateRankingQuery` parte da lista de municípios elegíveis da UF solicitada,
agrega setores em lote por `cd_mun` e só então calcula densidade e posições no
SQLite. A fórmula é `CAST(populacao AS REAL) / NULLIF(area_km2, 0)` sobre os
totais municipais. Não há média de densidades, arredondamento, preenchimento de
NULLs com zero, agregação/ordenação em PHP ou consulta por município.

A elegibilidade exige `m.cd_uf = :cd_uf`, código municipal de sete dígitos
ASCII (`LENGTH(m.cd_mun) = 7` e `m.cd_mun NOT GLOB '*[^0-9]*'`) e nome não vazio
após `TRIM`. A ordenação global é densidade conhecida primeiro, densidade
`DESC`, depois `cd_mun ASC`; nomes não desempatarão. `ROW_NUMBER()` produz a
posição completa antes de qualquer recorte futuro.

O `LEFT JOIN` dos totais setoriais mantém municípios elegíveis sem setores; suas
somas e densidade permanecem `null` e ocupam o fim pela ordenação. O registro
`cd_mun='.'` é inelegível ao ranking, enquanto `StateCensusService` segue
incluindo seus dois setores nos agregados e no contador da UF 43. O Service de
ranking consulta identidade uma vez e executa uma única Query set-based, sem
N+1.

Nesta task, a rota pública de ranking permanece ausente. O Controller é exercitado
isoladamente e a integração HTTP, incluindo a resposta paginada `data + meta`,
será publicada somente na task 4.3. Não há contrato HTTP intermediário sem
paginação.

A comparação independente do dataset real está em
[`StateRankingAuditTest.php`](../backend/tests/Dataset/StateRankingAuditTest.php),
com SQL próprio em [`ranking.sql`](../backend/tests/Dataset/sql/ranking.sql). A
execução confirmou equivalência em todas as linhas, métricas, ordem e posições,
sem duplicações ou omissões. As contagens observadas foram MG 31 = 853, SP 35 =
645, RS 43 = 497, AP 16 = 16, RR 14 = 15 e DF 53 = 1.
