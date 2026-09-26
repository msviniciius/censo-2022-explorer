# Spec Delta

## Purpose

Permitir consultar agregados de uma unidade da federação e percorrer seus municípios em um ranking paginado e estável de densidade demográfica.

## ADDED Requirements

### Requirement: Seleção e agregados estaduais

O sistema SHALL disponibilizar `GET /api/ufs` com códigos e nomes da tabela `uf`, ordenados por nome normalizado e código no desempate, e `GET /api/ufs/{cd_uf}` com identificação e agregados definidos em `census-aggregation`. A tela estadual MUST permitir selecionar uma UF, incluindo o Distrito Federal conforme a base, sem carregar agregados de uma UF por padrão. Códigos com formato diferente de dois dígitos ou ausentes MUST retornar HTTP 404.

#### Scenario: Seleção de uma UF
- **WHEN** o usuário abre a tela estadual e seleciona uma UF da lista
- **THEN** são exibidos população, área, densidade, homens, mulheres, cobertura demográfica e distribuição urbana/rural/não informada da UF selecionada

#### Scenario: Setores sem município identificável
- **WHEN** a UF contém setores vinculados a um registro municipal não selecionável
- **THEN** a tela informa que esses setores entram nos totais estaduais e não aparecem no ranking municipal

#### Scenario: UF inexistente
- **WHEN** uma consulta de detalhes ou ranking usa uma UF inexistente ou malformada
- **THEN** a API retorna HTTP 404 com mensagem legível

### Requirement: Ranking municipal por densidade

O sistema SHALL disponibilizar `GET /api/ufs/{cd_uf}/municipios?page=<n>&per_page=<n>` com municípios selecionáveis somente daquela UF. Cada item MUST informar posição, código, nome, população, área e densidade municipal calculada conforme `census-aggregation`. A ordem MUST ser densidade decrescente sem arredondamento, código municipal crescente para desempate e densidade `null` por último. Posições MUST ser sequenciais, iniciadas em um e contínuas entre páginas, inclusive nos empates.

#### Scenario: Ordenação com empate e ausência de densidade
- **WHEN** dois municípios têm densidade 50, outro tem densidade 10 e outro tem densidade `null`
- **THEN** os dois de densidade 50 vêm primeiro em ordem de código, depois o de densidade 10 e por último o sem densidade

#### Scenario: Ranking restrito à UF
- **WHEN** o usuário consulta o ranking de uma UF
- **THEN** nenhum município de outra UF nem registro municipal inválido aparece ou integra o total paginado

### Requirement: Paginação verificável

A paginação SHALL usar `page=1` e `per_page=25` por padrão, aceitar somente inteiros `page >= 1` e `per_page` pertencente a `{25, 50, 100}` e retornar HTTP 422 para entradas inválidas. A resposta MUST incluir `current_page`, `per_page`, `total` e `last_page`, sendo `last_page = max(1, ceil(total / per_page))`. Página além da última MUST retornar HTTP 200 com lista vazia e metadados corretos. A tela SHALL iniciar com 25 itens por página, oferecer as opções 25, 50 e 100 e controles anterior/próxima, com indicação de página e total, desabilitados nos limites e durante carregamento. Mudar o tamanho MUST reiniciar na página 1 e solicitar a nova página ao servidor, preservando os agregados da UF. Filtro por UF, ordenação e paginação MUST permanecer server-side.

#### Scenario: Navegação sem repetir municípios
- **WHEN** uma UF possui 55 municípios e são solicitadas as páginas 1, 2 e 3 com tamanho 25
- **THEN** retornam 25, 25 e 5 itens, posições de 1 a 55, total 55 e última página 3, sem repetição nem omissão

#### Scenario: Página vazia e parâmetros inválidos
- **WHEN** são solicitadas a página 4 de um ranking de três páginas, a página zero ou tamanhos 20, 26 ou 101
- **THEN** a primeira consulta retorna lista vazia com HTTP 200 e as outras retornam HTTP 422

#### Scenario: Tamanho padrão e opções de página
- **WHEN** o ranking de Minas Gerais contém 853 municípios e `per_page` não é informado
- **THEN** a resposta usa 25 itens por página, informa 35 páginas e retorna três itens na última página

#### Scenario: Troca do tamanho da página
- **WHEN** o usuário troca o tamanho de 25 para 50 ou 100 em um ranking com 853 municípios
- **THEN** o ranking volta à página 1 e consulta o servidor com o tamanho escolhido, informando 18 ou nove páginas, respectivamente, sem recarregar os agregados

### Requirement: Estados independentes e navegação entre telas

A aplicação SHALL oferecer acesso às telas municipal e estadual, com controles rotulados e utilizáveis por teclado. A tela estadual MUST distinguir carregamento, vazio, sucesso e falha recuperável da lista de UFs, dos agregados e do ranking. A troca de UF MUST limpar os resultados anteriores, reiniciar o ranking na página 1 e ignorar respostas obsoletas. A falha do ranking MUST preservar agregados já carregados da mesma UF.

#### Scenario: Troca de UF durante uma requisição
- **WHEN** o usuário troca de UF enquanto a página 3 do ranking anterior está carregando
- **THEN** a nova UF inicia na página 1 e nenhuma resposta da UF anterior substitui seus dados

#### Scenario: Erro apenas no ranking
- **WHEN** os agregados carregam e a requisição do ranking falha
- **THEN** os agregados permanecem visíveis e o ranking oferece uma nova tentativa
