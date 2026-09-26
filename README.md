# censo-2022-explorer

Bootstrap técnico do Census Explorer. Nesta etapa há uma página inicial Vue e
um endpoint de prontidão Laravel; consultas municipais, estaduais e ranking
ainda não foram implementados.

## Executar

Pré-requisitos: Git para obter o projeto, Docker Engine com Compose v2,
acesso à rede no primeiro build e porta 8080 disponível.

Na raiz da cópia completa do projeto:

```sh
docker compose up --build
```

Abra http://localhost:8080. O frontend chama GET /api/health na mesma origem.
O endpoint retorna 200 quando consegue ler as tabelas/colunas censitárias
necessárias e 503 quando a base está indisponível. URLs desconhecidas em /api
retornam JSON 404; as demais URLs têm fallback da SPA.

O Compose instala dependências, compila o Vue e prepara storage e a chave
automaticamente. Não é necessário instalar PHP, Composer ou Node no host,
copiar .env, executar migrations/seeders ou preparar diretórios.

Para encerrar, mantendo o storage:

```sh
docker compose down
```

## Banco e arquitetura

O arquivo censo.sqlite faz parte do repositório e é montado em /data/censo.sqlite
somente para leitura. O design atual prevê leitura direta do arquivo montado,
sem cópia operacional. A conexão também abre em modo somente leitura e ativa
query_only. Não há migrations, seeds, índices adicionais nem banco substituto.

Storage, cache de arquivos e chave persistente usam volume separado. O serviço
app executa PHP 8.4-FPM; web serve os assets com Nginx e encaminha /api ao Laravel.
Somente web publica uma porta. As imagens base estão fixadas por digest e as
dependências por lockfiles.

O healthcheck segue Controller → Service → Query → SQLite. As mesmas pastas
receberão os futuros casos de uso; não há Repository ou Organizer.

## Verificações do bootstrap

Todos os comandos abaixo usam containers:

```sh
docker compose config --quiet
docker compose build
docker compose --profile test run --build --rm backend-test
docker compose --profile test run --build --rm frontend-test
docker compose up -d --wait
docker compose ps
docker compose logs app web
```

O build do frontend executa TypeScript e Vite. Os testes usam Pest e
Vitest + Vue Test Utils. Os testes backend constroem fixtures temporárias,
sem usar migrations ou alterar o arquivo censitário. Playwright fica para a
etapa futura de E2E.

Se a inicialização falhar, consulte docker compose logs app web. Arquivo ausente,
ilegível ou esquema incompatível impedem a prontidão; a aplicação não cria um
SQLite vazio. A chave fica no volume app-storage e é reutilizada nos reinícios.
