# Spec Delta

## Purpose

Garantir que uma cópia completa do projeto com a base fornecida seja construída e executada em máquina limpa usando apenas Docker e Docker Compose.

## ADDED Requirements

### Requirement: Inicialização por comando único

A entrega SHALL conter `censo.sqlite` e tudo que permita iniciar a aplicação usando exclusivamente `docker compose up --build` na raiz. Os únicos pré-requisitos do host MUST ser Docker Engine com Compose v2, acesso à rede para baixar imagens/dependências no primeiro build e porta 8080 disponível. Nenhum PHP, Composer, Node ou gerenciador de pacotes no host, cópia manual de `.env`, criação manual de chave, migração, seed ou ajuste manual de permissões SHALL ser necessário. Após inicializar, a aplicação MUST estar acessível em `http://localhost:8080` com interface e API na mesma origem.

#### Scenario: Primeira execução sem artefatos locais
- **WHEN** o comando é executado numa cópia completa da entrega, sem `.env`, `vendor`, `node_modules`, assets compilados ou volumes prévios
- **THEN** dependências, assets, configuração, chave e diretórios graváveis são preparados automaticamente e as duas telas consultam a base fornecida

### Requirement: Preservação e disponibilidade da base

O serviço SHALL acessar `censo.sqlite` somente para leitura e MUST falhar com diagnóstico legível se o arquivo estiver ausente, ilegível, corrompido ou incompatível com as quatro tabelas e colunas necessárias. O sistema MUST nunca substituir silenciosamente a base por um banco vazio. Logs, caches e arquivos de runtime MUST usar armazenamento separado da fonte censitária.

#### Scenario: Banco indisponível
- **WHEN** a aplicação é iniciada sem uma base válida
- **THEN** ela não sinaliza prontidão, informa a causa nos logs e não cria uma base censitária vazia

#### Scenario: Reinicialização preserva os dados
- **WHEN** os serviços são encerrados e iniciados novamente pelo mesmo comando
- **THEN** continuam consultando a mesma base sem alterar seu conteúdo ou exigir preparação manual

### Requirement: Prontidão e falhas observáveis

A aplicação SHALL expor `GET /api/health` com HTTP 200 somente quando o backend conseguir consultar a estrutura censitária necessária, e HTTP 503 com mensagem genérica quando não conseguir. O Compose SHALL configurar verificação de saúde e a entrega SHALL documentar endereço, pré-requisitos e comandos de execução e encerramento. Respostas de erro da API MUST ser JSON, sem stack traces, SQL ou caminhos internos.

#### Scenario: Verificação após a inicialização
- **WHEN** os serviços estão prontos e a base está disponível
- **THEN** o healthcheck passa, `/api/health` retorna 200 e uma consulta municipal e uma consulta estadual retornam dados reais

#### Scenario: Falha de leitura após a inicialização
- **WHEN** o backend perde a capacidade de consultar a base
- **THEN** o healthcheck falha e a API retorna erro legível sem revelar detalhes internos
