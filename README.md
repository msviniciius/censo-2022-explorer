# censo-2022-explorer

Census Explorer com bootstrap executável e camada interna de agregação municipal
e estadual. A API expõe prontidão, busca e detalhes municipais. A interface
municipal permite buscar, selecionar e consultar os dados de um município.

## Executar

Pré-requisitos: Git para obter o projeto, Docker Engine com Compose v2,
acesso à rede no primeiro build e porta 8080 disponível.

Na raiz da cópia completa do projeto:

```sh
docker compose up --build
```

Abra http://localhost:8080; a aplicação abre a consulta municipal.
GET /api/health retorna 200 quando consegue ler as tabelas/colunas censitárias
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

O healthcheck e os endpoints municipais seguem Controller → Service → Query →
SQLite. A agregação estadual permanece interna, sem endpoint HTTP. Não há
Repository ou Organizer.

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

## Auditoria da agregação no dataset real

Fórmulas, NULLs, cobertura, contrato interno dos Services, lacunas do dataset e
resultados estão em [docs/aggregation.md](docs/aggregation.md).

A auditoria compara município `1100015` e UF `43` com SQL independente. Ela é
separada da suíte normal e monta o banco somente para leitura:

```sh
sha256sum censo.sqlite
docker compose --profile audit run --build --rm dataset-audit
sha256sum censo.sqlite
```

O comando valida todos os campos do contrato interno e o hash esperado antes e
depois, incluindo os dois setores do registro `.` nos totais estaduais. Uma
divergência faz o teste falhar com território/campo e valores comparados.


## API municipal

A task 3.2 expõe `GET /api/municipios?q=<texto>` e
`GET /api/municipios/{cd_mun}`. Contratos, exemplos, validação e comandos de
smoke test estão em [docs/municipal-api.md](docs/municipal-api.md).

## Fluxo municipal e recuperação

Em `/municipios`, digite pelo menos dois caracteres do nome. A busca aguarda
300 ms após a última edição, ignora acentos/caixa e mostra o nome da UF para
distinguir homônimos. Selecione uma sugestão com clique ou setas e Enter;
Escape fecha/cancela a interação corrente. A seleção carrega os detalhes.
Editar o texto limpa a seleção e os dados anteriores.

A busca distingue espera pela digitação, carregamento, resultados, ausência de
resultados e erro. “Nenhum município encontrado” significa resposta bem-sucedida
sem correspondências. Falhas de rede/servidor oferecem **Tentar buscar novamente**,
repetindo o termo que falhou; entrada inválida orienta editar o texto.

Os detalhes têm carregamento e erro próprios. **Tentar carregar detalhes novamente**
repete a consulta do município selecionado, sem repetir o autocomplete. Os botões
são acessíveis por teclado e não iniciam solicitações duplicadas durante o
carregamento. Município não encontrado orienta uma nova busca. Editar ou selecionar
outro município invalida os resultados pendentes, inclusive os de retries.

A seleção fica na URL: `/municipios?cd=1100015` abre diretamente Alta Floresta
D'Oeste, em Rondônia. Recarregar ou compartilhar esse endereço mantém a consulta;
`/municipios` sem código abre o estado inicial. Para conferir no navegador após
`docker compose up --build -d --wait`, abra a rota inicial, busque e selecione
esse município, confira os detalhes e recarregue a URL com `?cd=1100015`.

## Interpretar métricas e cobertura

População, área e densidade são métricas territoriais. A distribuição urbana,
rural e **Não informada** conta setores, não residentes. Situação ausente ou
não reconhecida permanece em Não informada.

Homens e mulheres mostram a soma dos valores conhecidos, com cobertura separada:
**completo** indica todos os setores com valor; **parcial**, somente parte deles;
**indisponível**, ausência de valores conhecidos. “67 de 85 setores” descreve a
cobertura setorial, não a porcentagem de pessoas recenseadas. Um zero conhecido
permanece zero; valor ausente aparece como Indisponível.

A distribuição por sexo refere-se aos **valores conhecidos**: os percentuais usam
a soma de homens mais a soma de mulheres. Essa soma pode diferir da população
territorial e não elimina o aviso de parcialidade. Não há preenchimento de lacunas
com zero nem estimativa de dados faltantes.
