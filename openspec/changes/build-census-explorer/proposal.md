# Proposal

## Why

O arquivo `censo.sqlite` contém os dados necessários para explorar o Censo 2022, mas ainda não há aplicação para consultá-los. A primeira versão deve oferecer consultas municipais e estaduais com resultados rastreáveis à base fornecida e execução reproduzível em máquina limpa.

## What Changes

- Especificar duas telas: consulta municipal com autocomplete e consulta estadual com agregados e ranking paginado de municípios por densidade decrescente.
- Apresentar população, área, densidade, homens, mulheres e distribuição da população urbana/rural em ambas as consultas, indicando dados indisponíveis ou parciais.
- Definir regras compartilhadas de agregação, cobertura demográfica, identificação territorial e tratamento das inconsistências observadas na base.
- Adotar backend Laravel/PHP 8.4 com SQLite e fluxo Controller → Service → Query → SQLite; frontend Vue 3, TypeScript, Vite e Tailwind.
- Entregar futuramente inicialização completa por `docker compose up --build`, sem instalações ou comandos preparatórios no host além do Docker/Compose.
- Nesta etapa, produzir exclusivamente artefatos SDD; toda implementação permanece pendente.

## Capabilities

### New Capabilities

- `census-aggregation`: fonte de verdade, métricas censitárias, fórmulas, cobertura e tratamento de dados incompletos.
- `municipal-query`: busca de municípios por autocomplete e exibição dos agregados do município selecionado.
- `state-query`: seleção de UF, agregados estaduais e ranking municipal paginado por densidade.
- `container-runtime`: construção e inicialização integral pelo Docker Compose, com acesso somente de leitura à base fornecida.

### Modified Capabilities

Nenhuma. O repositório ainda não possui specs funcionais nem código da aplicação.

## Impact

A implementação futura criará backend, frontend, API HTTP somente de consulta, empacotamento Docker e testes. O banco existente será preservado, sem migrações, seeds ou correções de seus registros. As decisões e critérios de aceite constam de `design.md` e das delta specs; `tasks.md` organiza a execução incremental.

Fora do escopo: autenticação, escrita/CRUD de dados, importação ou atualização do censo, fontes externas, mapas, gráficos, exportações, comparações entre territórios, filtros adicionais, administração, filas, Redis e infraestrutura de produção em nuvem.
