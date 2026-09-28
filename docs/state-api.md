# API e fluxo estadual — tasks 4.1–4.5

## Lista de UFs

`GET /api/ufs`

Retorna `200 {"data":[{"cd_uf":"…","nm_uf":"…"}]}` com todas as UFs da
fonte, incluindo o Distrito Federal e UFs sem municípios ou setores. A ordenação
é `census_normalize_name(nm_uf), cd_uf`: nome sem distinção de acentos/caixa,
código como desempate. Os nomes originais e os códigos em strings são preservados.
Não há siglas externas, filtros, paginação ou agregados nessa resposta.
Uma tabela `uf` vazia retorna `200 {"data":[]}`.

Fluxo: `StateController → StateListService → StateListQuery → uf`.
A Query executa uma única listagem, sem joins ou consultas por UF.

## Detalhes de uma UF

`GET /api/ufs/{cd_uf}`

O código deve conter exatamente dois dígitos ASCII. UF malformada ou inexistente
retorna 404. Uma UF existente sem setores retorna 200, contagens zero e métricas/
percentuais nulos, com cobertura demográfica indisponível.

Fluxo: `StateController → StateCensusService::find() → StateIdentityQuery /
SectorAggregationQuery`. O Controller envolve o DTO existente em `data`, sem
recalcular fórmulas. Contrato completo, regras e referências auditadas em
[aggregation.md](aggregation.md).

Exemplo do snapshot fornecido: `GET /api/ufs/43`:

```json
{
  "data": {
    "cd_uf": "43",
    "nm_uf": "Rio Grande do Sul",
    "agregados": {
      "total_setores": 25569,
      "populacao": 10882965,
      "area_km2": 281707.1504883004,
      "densidade_hab_km2": 38.6321929746401,
      "distribuicao": {
        "total": 25569, "urban": 19436, "rural": 6112, "unclassified": 21,
        "urban_pct": 76.01392311001604,
        "rural_pct": 23.903946184833195,
        "unclassified_pct": 0.08213070515076851
      },
      "homens": {
        "valor": 5237692, "percentual": 48.22035882365234,
        "setores_com_valor": 24874, "total_setores": 25569, "estado": "parcial"
      },
      "mulheres": {
        "valor": 5624301, "percentual": 51.77964117634766,
        "setores_com_valor": 24869, "total_setores": 25569, "estado": "parcial"
      }
    },
    "setores_sem_municipio_identificavel": 2
  }
}
```

Os dois setores do registro `cd_mun='.', nm_mun='', cd_uf='43'` participam dos
agregados e do contador, mesmo sem um município selecionável. O contador é um
inteiro do contrato; a API não acrescenta mensagem textual. Não há filtro de
municípios selecionáveis nos totais estaduais.

População e área são somas setoriais; densidade usa esses totais. A distribuição
territorial conta setores, com NULL/categoria desconhecida em `unclassified`.
Percentuais por sexo usam as somas separadas de homens e mulheres, preservando
NULLs e avisos de cobertura. Números não recebem formatação nem arredondamento.

## Status e erros

Os endpoints retornam JSON mesmo sem `Accept: application/json`.
O tratamento global existente produz os erros, sem SQL, caminhos ou stack traces.

| Status | Resposta / condição |
|---|---|
| 200 | Envelope `data`, com lista ou detalhes |
| 404 | `{"message":"Recurso não encontrado."}` para UF inválida/inexistente ou rota desconhecida |
| 405 | `{"message":"Não foi possível atender à solicitação."}` para método não suportado |
| 503 | `{"message":"Base censitária indisponível."}` para falha de acesso à base |
| 500 | `{"message":"Não foi possível atender à solicitação."}` para falha inesperada |

## Ranking municipal de uma UF

`GET /api/ufs/{cd_uf}/municipios?page=<n>&per_page=<n>` retorna os municípios
selecionáveis da UF ordenados pela densidade agregada decrescente, sem arredondar;
empates são resolvidos por `cd_mun` crescente e densidades nulas ficam no final.
`posicao` é global na UF e permanece contínua entre páginas. A agregação e o ranking
são executados no SQLite antes do recorte paginado.

`page` é inteiro a partir de 1 e assume `1` quando ausente. `per_page` aceita
exclusivamente `25`, `50` ou `100` e assume `25` quando ausente. Valores vazios,
não inteiros, menores que 1 ou tamanhos não permitidos retornam 422 no contrato
global de validação. Chaves repetidas seguem o parser HTTP padrão.

Exemplo completo da fixture de testes para `GET /api/ufs/43/municipios`:

```json
{
  "data": [
    {
      "posicao": 1,
      "cd_mun": "4300001",
      "nm_mun": "Município válido",
      "populacao": 10,
      "area_km2": 1,
      "densidade_hab_km2": 10
    }
  ],
  "meta": {"current_page": 1, "per_page": 25, "total": 1, "last_page": 1}
}
```

Códigos permanecem strings; posição, população e metadados são inteiros; área e
densidade são números. População, área e densidade podem ser `null`.
`total` conta municípios elegíveis e `last_page = max(1, ceil(total/per_page))`.
Não há campos `links`, `from` ou `to`. Uma página além da última
retorna 200 com `data: []`, a página solicitada em `current_page`, e o total/
`last_page` reais. Uma UF existente sem municípios elegíveis também retorna 200
com lista vazia, `total: 0` e `last_page: 1`. UF malformada ou inexistente retorna
404; indisponibilidade censitária retorna 503; falhas inesperadas retornam 500.
Erros são JSON genéricos, sem SQL, caminhos ou stack traces. O ranking não altera
os agregados de `GET /api/ufs/{cd_uf}`.

## Fluxo da interface e recuperação

A rota `/estados` carrega a lista de UFs sem selecionar uma opção por padrão. A
seleção inicia, independentemente, os detalhes da UF e a página 1 do ranking com
25 itens. Paginar consulta somente o ranking; trocar para 25, 50 ou 100 itens
reinicia na página 1 e preserva os agregados.

Lista, detalhes e ranking mantêm estados e retries independentes. Falhas de rede
ou HTTP 5xx oferecem nova tentativa apenas para o recurso afetado. Erros HTTP 4xx
não repetem automaticamente uma requisição inválida. Uma falha do ranking
preserva agregados já carregados. Ao paginar com a mesma UF e tamanho, a última
página válida permanece visível durante o carregamento e caso a nova página
falhe; o retry usa exatamente a UF, página e tamanho da falha.

Cada recurso usa sua própria sequência de requisições. Respostas de detalhes só
são aceitas para a UF ainda selecionada. Respostas do ranking também precisam
corresponder ao tamanho de página atual. Trocar ou limpar a UF, trocar página ou
tamanho e desmontar a tela invalidam logicamente respostas anteriores, inclusive
falhas tardias.

Os totais estaduais e o ranking têm abrangências diferentes. Os agregados usam
todos os setores vinculados à UF, inclusive setores de municípios não
selecionáveis. O ranking inclui apenas municípios com código de sete dígitos e
nome não vazio. Na UF `43`, os dois setores do registro `.` entram nos agregados
e em `setores_sem_municipio_identificavel`, enquanto o registro não integra o
ranking. Portanto, os totais estaduais não devem ser reconstruídos pela soma das
linhas do ranking.

Exemplo de erro: `GET /api/ufs/31/municipios?page=0` retorna HTTP 422:

```json
{
  "message": "Os parâmetros informados são inválidos.",
  "errors": {"page": ["The page field must be at least 1."]}
}
```

No snapshot auditado, MG possui 853 municípios elegíveis: 35 páginas de 25
(a última com 3 itens), 18 de 50 ou 9 de 100. A página 36 de tamanho 25 retorna
`{"data":[],"meta":{"current_page":36,"per_page":25,"total":853,"last_page":35}}`.
Para uma UF existente sem elegíveis, `page=2&per_page=50` retorna
`{"data":[],"meta":{"current_page":2,"per_page":50,"total":0,"last_page":1}}`.

Fluxo: `StateRankingController → StateRankingService → StateIdentityQuery /
StateRankingQuery`. Uma UF existente exige duas consultas, inclusive para página
vazia; UF inexistente encerra após a identidade. O total acompanha a página na
mesma consulta SQL. Não há filtros textuais nem ordenação configurável.

## Verificação

```sh
docker compose --profile test build backend-test
docker compose run --rm backend-test php vendor/bin/pest tests/Feature/StateHttpTest.php
docker compose run --rm backend-test php vendor/bin/pest tests/Feature/StateRankingTest.php
docker compose --profile audit run --build --rm dataset-audit php vendor/bin/pest --configuration phpunit.dataset.xml tests/Dataset/StateRankingAuditTest.php
docker compose run --rm backend-test php vendor/bin/pest tests/Feature/ServiceArchitectureTest.php
docker compose run --rm backend-test

docker compose up --build -d --wait
curl -i http://localhost:8080/api/ufs
curl -i http://localhost:8080/api/ufs/53
curl -i http://localhost:8080/api/ufs/43
curl -i http://localhost:8080/api/ufs/99
curl -i http://localhost:8080/api/ufs/4
curl -i http://localhost:8080/api/municipios/1100015
curl -i http://localhost:8080/api/health
sha256sum censo.sqlite
```

Os testes HTTP usam fixtures SQLite temporárias, sem montar o dataset real.
Os smoke tests leem o snapshot original montado read-only: a lista deve conter
27 UFs com códigos textuais e o Distrito Federal (`53`); os detalhes de `43`
devem corresponder à referência auditada acima, com tolerância numérica `1e-6`.
