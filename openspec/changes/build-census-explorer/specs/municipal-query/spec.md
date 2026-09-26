# Spec Delta

## Purpose

Permitir localizar um município pelo nome e consultar seus agregados censitários, distinguindo localidades homônimas e apresentando estados claros de interação.

## ADDED Requirements

### Requirement: Autocomplete municipal

O sistema SHALL disponibilizar `GET /api/municipios?q=<texto>` para busca por trecho do nome, sem distinção de maiúsculas ou acentos. Após remover espaços das extremidades, consultas com menos de dois caracteres MUST retornar lista vazia; consultas acima de 100 caracteres ou de tipo inválido MUST retornar HTTP 422. A resposta MUST conter no máximo dez municípios válidos, ordenados por nome normalizado e código municipal crescente no desempate, com `cd_mun`, `nm_mun`, `cd_uf` e `nm_uf`. Caracteres de busca MUST ser tratados literalmente, sem funcionar como curingas ou comandos.

#### Scenario: Busca ignora acentos e caixa
- **WHEN** o usuário pesquisa `sao` e existe um município chamado `São Paulo`
- **THEN** ele é elegível para as sugestões, respeitando a ordenação e o limite de dez resultados

#### Scenario: Nomes repetidos são distinguíveis
- **WHEN** municípios de UFs diferentes têm o mesmo nome
- **THEN** cada sugestão apresenta município e nome da UF e a seleção usa seu código municipal

#### Scenario: Busca curta ou sem correspondências
- **WHEN** o texto tem menos de dois caracteres após trim ou não encontra correspondências
- **THEN** a API retorna HTTP 200 com `data: []`, sem selecionar um município automaticamente

#### Scenario: Texto literal e validação
- **WHEN** a consulta contém `%`, `_`, aspas ou mais de 100 caracteres
- **THEN** caracteres válidos são buscados literalmente e o texto acima do limite recebe HTTP 422

### Requirement: Detalhes do município selecionado

O sistema SHALL disponibilizar `GET /api/municipios/{cd_mun}` e exibir identificação territorial e todos os agregados definidos em `census-aggregation` na tela municipal. Somente a seleção explícita de uma sugestão MUST carregar os detalhes. Códigos malformados, inexistentes ou não selecionáveis MUST retornar HTTP 404 com mensagem legível, sem dados de outro município.

#### Scenario: Seleção carrega os agregados
- **WHEN** o usuário seleciona um município nas sugestões
- **THEN** a tela apresenta nome, UF, população, área, densidade, homens, mulheres, cobertura demográfica e distribuição urbana/rural/não informada daquele código

#### Scenario: Município não encontrado
- **WHEN** a API recebe um código ausente da base ou o registro `.`
- **THEN** retorna HTTP 404 e a interface permite realizar uma nova busca

### Requirement: Estados e acessibilidade da consulta municipal

A tela municipal SHALL oferecer rótulo do campo, navegação das sugestões com setas, seleção com Enter, fechamento com Escape e acesso por teclado aos controles. A busca SHALL aguardar 300 ms após a última edição e só consultar a partir de dois caracteres. A interface MUST distinguir estado inicial, carregamento, ausência de resultados, sucesso e falha com opção de tentar novamente. Editar a busca MUST limpar a seleção anterior; respostas de requisições obsoletas MUST ser ignoradas.

#### Scenario: Respostas chegam fora de ordem
- **WHEN** a consulta mais recente termina antes de uma consulta anterior
- **THEN** somente o resultado correspondente à consulta atual pode aparecer

#### Scenario: Edição invalida detalhes anteriores
- **WHEN** o usuário altera o texto após selecionar um município
- **THEN** os detalhes anteriores deixam de ser exibidos e a tela volta ao fluxo de busca

#### Scenario: Uso por teclado e falha recuperável
- **WHEN** o usuário opera o autocomplete por teclado e a consulta de detalhes falha
- **THEN** a seleção funciona com setas e Enter e uma mensagem legível oferece nova tentativa sem exibir dados obsoletos
