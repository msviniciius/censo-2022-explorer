# API municipal — task 3.2

As duas rotas retornam JSON, mesmo sem `Accept: application/json`.
Sucesso usa somente `{ "data": ... }`. Os arrays internos dos Services não têm
envelope/status; o Controller acrescenta `data` sem recalcular ou formatar os
valores. Fórmulas, tipos e lacunas: [agregação](aggregation.md).

## Busca

`GET /api/municipios?q=<texto>`

- `q` é opcional, com padrão vazio; remove espaços das extremidades.
- Menos de dois caracteres retorna `200 {"data":[]}` sem consultar o banco.
- De dois a 100 caracteres Unicode: busca parcial nacional, ignorando acentos e
  caixa. Sem correspondência retorna a mesma lista vazia.
- Array/tipo inválido ou mais de 100 caracteres após trim retorna 422.
- No máximo dez identificações, por nome normalizado e código crescente no empate.
  Homônimos permanecem separados. `%`, `_`, `!`, barras invertidas e aspas são
  literais; codifique os parâmetros na URL.
- Códigos são strings; UF tem nome/código da fonte, sem catálogo de siglas.
  Registros sem sete dígitos ASCII ou sem nome utilizável, inclusive `.`, não aparecem.

Exemplo: `GET /api/municipios?q=alta%20floresta%20d%27oeste`:

```json
{"data":[{"cd_mun":"1100015","nm_mun":"Alta Floresta D'Oeste","cd_uf":"11","nm_uf":"Rondônia"}]}
```

Fluxo: `MunicipalityController → MunicipalitySearchService → MunicipalitySearchQuery`.
Normalização, escaping, elegibilidade, ordenação e limite continuam na Query da 3.1.

## Detalhes

`GET /api/municipios/{cd_mun}`

O código deve ter sete dígitos ASCII; permanece string, preservando zeros à
esquerda. Município ausente, malformado ou não selecionável retorna 404, incluindo
`.` e nomes vazios. Município existente sem setores retorna 200 com contagens
zero, métricas/percentuais nulos e cobertura indisponível.

Exemplo: `GET /api/municipios/1100015`:

```json
{
  "data": {
    "cd_mun": "1100015", "nm_mun": "Alta Floresta D'Oeste",
    "cd_uf": "11", "nm_uf": "Rondônia",
    "agregados": {
      "total_setores": 85, "populacao": 21494,
      "area_km2": 7067.1267818999995, "densidade_hab_km2": 3.041405745691367,
      "distribuicao": {
        "total": 85, "urban": 26, "rural": 59, "unclassified": 0,
        "urban_pct": 30.58823529411765, "rural_pct": 69.41176470588235,
        "unclassified_pct": 0
      },
      "homens": {
        "valor": 10744, "percentual": 50.33497306160694,
        "setores_com_valor": 67, "total_setores": 85, "estado": "parcial"
      },
      "mulheres": {
        "valor": 10601, "percentual": 49.66502693839306,
        "setores_com_valor": 67, "total_setores": 85, "estado": "parcial"
      }
    }
  }
}
```

Fluxo: `MunicipalityController → MunicipalCensusService → Queries de identidade/agregação`.
A conexão continua `census`, somente leitura. A rejeição de `.` nesta API não
altera a inclusão de seus setores nos agregados estaduais.

## Erros e status

| Status | Condição / corpo |
|---|---|
| 200 | Busca válida (inclusive vazia) ou detalhes elegíveis; envelope `data` |
| 404 | Código inválido/ausente/não selecionável ou rota API desconhecida; `{"message":"Recurso não encontrado."}` |
| 422 | `q` inválido; `message` e `errors.q` com lista de mensagens |
| 503 | Base indisponível; `{"message":"Base censitária indisponível."}` |
| 500 | Falha inesperada; `{"message":"Não foi possível atender à solicitação."}` |

Exemplo para `?q[]=sao`:

```json
{"message":"Os parâmetros informados são inválidos.","errors":{"q":["O parâmetro q deve ser um texto."]}}
```

O erro de comprimento usa `O parâmetro q deve ter no máximo 100 caracteres.`.
Respostas não expõem SQL, caminho, stack trace ou mensagem interna da exceção.
POST/DELETE não são endpoints disponíveis e recebem 405 em JSON. O `/api/health`
continua com o contrato anterior; endpoints estaduais e telas não integram esta task.

## Verificação reproduzível

```sh
docker compose --profile test run --build --rm backend-test
# Opcional: somente os contratos HTTP municipais
docker compose --profile test run --rm backend-test php vendor/bin/pest tests/Feature/MunicipalityHttpTest.php

docker compose up --build -d --wait
curl -i --get --data-urlencode "q=alta floresta d'oeste" http://localhost:8080/api/municipios
curl -i http://localhost:8080/api/municipios/1100015
curl -i http://localhost:8080/api/municipios
curl -i http://localhost:8080/api/municipios/9999999
curl -i --get --data-urlencode 'q[]=sao' http://localhost:8080/api/municipios
sha256sum censo.sqlite
```

Os testes HTTP normais usam exclusivamente fixtures SQLite temporárias. Os
comandos `curl` consultam o snapshot montado read-only, sem alterar o arquivo.
