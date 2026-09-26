# Design

## Context

Ver [proposal.md](proposal.md) para motivação e escopo. O repositório contém apenas a infraestrutura OpenSpec e `censo.sqlite`, sem aplicação, dependências ou specs anteriores. Este documento registra decisões propostas para implementação futura; nenhum runtime foi implementado ou testado nesta etapa.

Inspeção somente de leitura da base fornecida em 2026-09-26:

| Tabela | Chave e relações | Registros | Campos usados |
|---|---|---:|---|
| `uf` | PK `cd_uf` | 27 | `nm_uf` |
| `municipio` | PK `cd_mun`, FK `cd_uf → uf` | 5.571 | `nm_mun` |
| `setor` | PK `cd_setor`, FK `cd_mun → municipio` | 468.099 | `situacao`, `area_km2`, `populacao` |
| `demografia` | PK/FK `cd_setor → setor` | 458.772 | `moradores`, `homens`, `mulheres` |

Todas as tabelas são `WITHOUT ROWID`; não há índices secundários. A checagem de chaves estrangeiras não encontrou violações. O arquivo tem 35.196.928 bytes e SHA-256 `f429226fa3372fde8b4cd22a32cb2a3fc24533b5fb340249b83867d3b485086a`.

Achados que afetam o comportamento:

- O registro municipal `.` tem nome vazio e pertence à UF `43`. Seus dois setores somam população zero e área de 13.085,864101 km². Existem 5.570 municípios selecionáveis; o registro inválido não pode ser eliminado dos totais estaduais sem perder área da fonte de verdade.
- Não há população ou área nula, negativa ou área zero em `setor` no arquivo atual. A população total é 203.080.756; esse número é referência de auditoria da base, não uma terceira tela nacional.
- Há 9.327 setores sem linha demográfica; juntos têm população zero. Mesmo nas linhas existentes há 8.691 valores nulos de homens e 8.733 de mulheres. Os campos precisam de coberturas independentes.
- Há 354.965 setores `Urbana`, 112.031 `Rural` e 1.103 com situação nula. Esses últimos permanecem como `Não informada`.
- `demografia.moradores` coincide com a população setorial onde há valor, mas contém lacunas; não será usado para substituir `setor.populacao`.
- A tabela `uf` não contém sigla. A interface usará o nome da UF fornecido, sem introduzir um catálogo externo de siglas.

## Goals / Non-Goals

**Goals:** preservar a base, concentrar as regras no backend, produzir contratos HTTP tipáveis e permitir execução integral por Compose. Os cenários nas quatro specs são os critérios funcionais de aceite.

**Non-Goals:** adicionar camadas de repositório, ORM para carregamento de conjuntos censitários, banco auxiliar, cache distribuído, processamento assíncrono ou materialização persistente dos agregados. Não corrigir a base nem aplicar migrations Laravel a ela. Os demais limites funcionais estão na proposta.

## Decisions

### D1 — Organização e versões

Adotar `backend/` para Laravel com PHP 8.4, `frontend/` para Vue 3 + TypeScript + Vite + Tailwind e `docker/` para configurações dos serviços. Laravel 13 é a versão principal proposta: sua [documentação oficial de versões](https://laravel.com/framework/docs/releases) exige PHP mínimo 8.3, compatível com o PHP 8.4 solicitado. SQLite é suportado pelo [driver documentado do Laravel](https://github.com/laravel/docs/blob/13.x/database.md).

Fixar versões concretas e compatíveis de PHP 8.4, Composer, Node, Nginx e dependências no primeiro incremento, registrando `composer.lock` e o lockfile npm. A instalação de entrega usará os lockfiles. Vue 3 e PHP 8.4 são restrições, não opções a reavaliar. TypeScript terá checagem estrita. Não será necessário Node no runtime.

Alternativa: frontend incorporado às views Laravel. A separação em duas pastas deixa explícitos os contratos HTTP e os builds, mantendo uma única origem pública.

### D2 — Fluxo obrigatório Controller → Service → Query → SQLite

| Camada | Responsabilidade | Limite |
|---|---|---|
| Controller | Validar entrada HTTP, chamar Service, transformar resultados em JSON/status | Sem SQL, soma ou cálculo de densidade |
| Service | Orquestrar o caso de uso, compor DTOs, densidade de detalhes, percentuais e cobertura | Sem acesso direto à conexão ou dependência de HTTP |
| Query | Encapsular consultas parametrizadas, joins, agregações, ordenação e paginação no SQLite | Sem formatação de interface ou resposta HTTP |
| SQLite | Ler exclusivamente as quatro tabelas fornecidas | Sem escrita ou alteração de esquema |

Aplicar o fluxo aos endpoints de busca, detalhes, lista de UFs, ranking e prontidão. Usar classes Query concretas e injeção de dependência do Laravel, sem camada Repository adicional. O ranking calcula a razão em SQL antes da paginação; Services e Queries seguem a mesma fórmula, verificada por fixtures para evitar divergência. O frontend apenas apresenta valores e gerencia interação.

Alternativas descartadas: SQL em controllers mistura transporte e dados; carregar modelos Eloquent de todos os setores consome memória e favorece consultas por município; adicionar Repository duplicaria a função das Queries.

### D3 — Identidade, agregação e dados incompletos

A base é um snapshot imutável. Manter códigos como texto, inclusive em DTOs e rotas. Validar município selecionável por sete dígitos e nome não vazio após trim; UF deve existir e ter código de dois dígitos.

As consultas de detalhes partirão de `setor`, fazendo `LEFT JOIN demografia` por `cd_setor` quando necessário. A relação é de no máximo uma linha por setor, protegida pela chave primária. Somar a população e área sem depender da disponibilidade de demografia. Uma consulta estadual usa `setor → municipio → uf` e não aplica o filtro de municípios selecionáveis; a busca e o ranking aplicam esse filtro.

Retornar cobertura por campo demográfico usando `COUNT(valor)` e total de setores. Se total = 0 ou conhecidos = 0, estado `indisponivel` e valor `null`; se conhecidos = total > 0, estado `completo`; nos demais casos, `parcial` e soma dos valores conhecidos. Não presumir que população = homens + mulheres quando a cobertura é incompleta. O aviso de parcialidade existe mesmo quando setores sem dados têm população zero: trata-se de cobertura por setor, não estimativa de população faltante.

Distribuição: somas condicionais de população para `Urbana`, `Rural` e demais situações; percentual = soma da categoria / população total × 100 quando o denominador é positivo. Território com setores e população zero mantém contagens zero e percentuais nulos. Território sem setores mantém as métricas nulas. Área zero implica densidade nula. Não arredondar valores no backend; testes de ponto flutuante usam tolerância de `1e-6` para área/densidade.

Alternativa descartada: excluir registros anômalos de todos os cálculos ou converter ausência em zero. Ambas alterariam a interpretação da fonte. O registro sem nome será apenas indisponível para seleção, com explicação na consulta estadual.

### D4 — Contrato HTTP

Todos os endpoints são GET, retornam JSON e ficam sob `/api`. Sucesso de consulta usa `{ data: ... }`; ranking acrescenta `meta`. Erros usam `{ message: string }`; validação acrescenta `errors` por campo. Entrada inválida de query string retorna 422; identificador territorial inválido/inexistente retorna 404; indisponibilidade da base retorna 503; falha inesperada retorna 500. Erros de domínio não expõem SQL, caminho do arquivo ou stack trace. Rotas `/api/*` desconhecidas retornam JSON 404, sem cair no HTML da SPA.

| Endpoint | Entrada | `data` |
|---|---|---|
| `/municipios` | `q` opcional; padrão vazio; trim; 2–100 caracteres para buscar | até 10 identificações municipais |
| `/municipios/{cd_mun}` | código em string | identificação municipal e `agregados` |
| `/ufs` | nenhuma | lista de `{ cd_uf, nm_uf }` |
| `/ufs/{cd_uf}` | código em string | identificação estadual, `agregados`, `setores_sem_municipio_identificavel` |
| `/ufs/{cd_uf}/municipios` | `page=1`, `per_page=20`, limites nas specs | linhas do ranking e metadados |
| `/health` | nenhuma | `{ status: "ok" }` quando pronto |

Identificação municipal: `{ cd_mun: string, nm_mun: string, cd_uf: string, nm_uf: string }`.

Objeto `agregados`:

| Campo | Tipo / significado |
|---|---|
| `populacao` | inteiro ou `null` |
| `area_km2` | número ou `null` |
| `densidade_hab_km2` | número ou `null` |
| `total_setores` | inteiro >= 0 |
| `homens`, `mulheres` | `{ valor: integer ou null, setores_com_valor: integer, total_setores: integer, estado: "completo", "parcial" ou "indisponivel" }` |
| `distribuicao.urbana`, `.rural`, `.nao_informada` | `{ populacao: integer ou null, percentual: number ou null }` |

Linha de ranking: `{ posicao, cd_mun, nm_mun, populacao, area_km2, densidade_hab_km2 }`. Metadados: `{ current_page, per_page, total, last_page }`. A UF já é identificada pela rota e pela seleção da tela. Os tipos numéricos não incluem strings formatadas.

Alternativa: um único endpoint gigante com detalhes e ranking. Endpoints separados permitem paginar sem refazer a apresentação dos agregados e recuperar falhas por região da tela.

### D5 — Busca e ranking no banco

Autocomplete faz correspondência por trecho de nome normalizado, com minúsculas e remoção de marcas de acento por normalização Unicode. Registrar uma função SQLite na conexão PDO para essa normalização, usando a extensão PHP `intl`, incluída na imagem. Usar parâmetros e escape explícito de `%`, `_` e do caractere de escape, para busca literal. O mesmo nome normalizado ordena resultados; código desempata. Não adicionar busca por código, filtros ou catálogo de siglas. Uma varredura de 5.570 nomes é proporcional ao conjunto.

O ranking agrega em SQL os setores dos municípios da UF, calcula densidade como divisão real protegida contra zero e ordena antes de `LIMIT/OFFSET`. O desempate por código e o posicionamento dos nulos garantem paginação determinística. Contar municípios selecionáveis da mesma UF para os metadados, inclusive um eventual município sem setores. Usar `LEFT JOIN` a partir de municípios válidos para preservar esse caso. A posição é `(page - 1) × per_page + índice local + 1`.

Não há índices secundários na base e eles não serão acrescentados. Evitar uma consulta por município e subconsultas correlacionadas que varram setores repetidamente; adotar agregação em conjunto. Medir os planos e latências com a base real durante a implementação, registrando hardware e condições. Não há SLA numérico estabelecido nesta primeira versão.

Alternativas: busca `LIKE` simples não resolve a equivalência de acentos; trazer os setores para PHP ou paginar antes de ordenar produz custo e/ou classificação incorretos; persistir agregados ou índices alteraria o modelo de entrega e não é necessário para estabelecer esta versão.

### D6 — Duas telas Vue

Usar SPA com rotas `/municipios` e `/estados`, navegação explícita entre elas e redirecionamento de `/` para `/municipios`. O servidor fará fallback para o HTML apenas nas rotas de interface; recarregar uma rota deve funcionar. Vue Router cuida das duas rotas; estado local/composables bastam, sem store global.

Componentes compartilhados apresentam agregados, cobertura, distribuição e estados de erro. A distribuição será textual/tabular, sem gráficos. Formatação usa `Intl.NumberFormat('pt-BR')`, conforme as specs. Cartões/linhas devem se reorganizar em telas estreitas; a tabela de ranking pode rolar horizontalmente sem ocultar os controles.

No autocomplete, debounce de 300 ms, combobox acessível, seleção explícita e cancelamento/identificação de requisições. Em ambos os fluxos, uma resposta só atualiza a tela se pertencer à seleção e aos parâmetros atuais. Ao trocar de UF, limpar agregados/ranking, voltar à página 1 e carregar ambos independentemente. Ao mudar apenas de página, preservar os agregados. Retry usa a seleção atual. Não persistir seleção em armazenamento local nem acrescentar favoritos/histórico.

Alternativa: páginas renderizadas pelo servidor ou Inertia não atendem à separação HTTP escolhida tão diretamente; gerenciamento global de estado é desnecessário para os dois fluxos.

### D7 — Build e runtime com Compose

Dois serviços: `app` com PHP 8.4-FPM/Laravel e `web` com Nginx, única porta publicada `8080:80`. Nginx serve o build estático Vue e encaminha `/api/*` ao front controller Laravel via FastCGI. PHP-FPM permanece na rede interna. Usar builds em múltiplos estágios para Composer e Node, com as dependências e assets incorporados às imagens; não depender de bind mounts de código ou servidores de desenvolvimento.

O arquivo original `censo.sqlite` será parte da entrega completa na raiz, legível pelo usuário de runtime. Montá-lo no `app` com bind mount de arquivo somente leitura e `create_host_path: false`, para falhar se ausente em vez de criar um diretório. Configurar conexão censitária explicitamente, modo de abertura somente leitura e `query_only`; testar que uma escrita é rejeitada. O arquivo não participa de migrations, seeds, WAL configurado pela aplicação, sessões ou cache. Nenhum banco substituto será criado.

O entrypoint fornecerá configuração padrão automaticamente, sem `.env` do host, e criará/persistirá uma chave em armazenamento de runtime separado. Usar volume próprio para storage/chave, cache em arquivo e logs em stdout/stderr. Preparar permissões automaticamente antes de iniciar o processo de runtime. Desabilitar sessão em banco, filas em banco e scripts de instalação que criem ou migrem o SQLite padrão. `APP_DEBUG=false` na entrega Compose.

Na inicialização, validar arquivo, abertura e tabelas/colunas necessárias. A prontidão percorre Controller → Service → Query e executa leituras leves, sem varredura completa por healthcheck. Configurar healthcheck no serviço HTTP e dependência do backend, com período de tolerância de inicialização. Uma falha deve ser explicada nos logs e manter o serviço sem prontidão. Não expor FPM diretamente.

Alternativas: `artisan serve` e servidor Vite como runtime adicionariam processos de desenvolvimento à entrega; instalar dependências no host ou exigir `.env` contrariaria o comando único. Incluir um banco vazio como fallback mascararia falhas de entrega.

### D8 — Verificação e aceite por incremento

Testes de backend usarão fixtures SQLite temporárias com o mesmo esquema e leituras da base real. Fixtures serão construídas somente no ambiente de teste, sem migração sobre o arquivo censitário. Cobrir joins com demografia ausente, valores nulos e zeros conhecidos, território vazio, área zero, categorias não informadas, registro municipal inválido, empate e limites da paginação. Testes HTTP verificam os contratos, status e tipos. Revisão das dependências das classes verifica o fluxo das camadas.

Testes de interface com Vitest/Vue Test Utils focam debounce, navegação por teclado, respostas fora de ordem, troca de seleção, estados de erro e paginação. Um teste de fluxo com navegador automatizado cobre as duas telas contra os containers; não criar snapshots que apenas reproduzam a marcação.

Referências reais já verificadas para comparação independente:

| Território | Setores | População | Área km² | Demografia |
|---|---:|---:|---:|---|
| Município `1100015` | 85 | 21.494 | 7067.1267819 | homens 10.744, mulheres 10.601; cobertura 67/85 em ambos |
| UF `43`, incluindo o registro `.` | 25.569 | 10.882.965 | 281707.1504883004 | validar somas e cobertura por SQL independente |

Não usar o código das Queries como oráculo dos testes. Para ranking, validar ordenação global e ausência de repetição entre páginas contra agregação independente. Comparar SHA-256 da base antes e depois dos testes de integração/execução.

Aceite final: partir de cópia completa sem dependências, `.env`, build ou volumes anteriores; executar apenas `docker compose up --build`; aguardar healthcheck; abrir ambas as rotas inclusive por recarga direta; consultar um município; consultar uma UF e avançar o ranking; reiniciar e confirmar resultados e hash preservados. A matriz de rastreabilidade está no plano incremental.

## Risks / Trade-offs

- [Registro municipal inválido representa área relevante] → incluir todos os setores nos agregados da UF, excluir somente da seleção/ranking e mostrar aviso com contagem; não prometer igualdade entre área estadual e soma exclusiva das linhas do ranking.
- [Demografia incompleta pode parecer contraditória com a população] → informar cobertura independente de homens/mulheres e marcar somas parciais; não imputar valores.
- [Varreduras sem índices podem elevar latência] → agregar em SQL sem N+1, medir com os 468.099 setores e registrar limitações. Uma mudança de persistência ou SLA exige decisão posterior de escopo.
- [Somas em ponto flutuante e arredondamento podem afetar empates aparentes] → ordenar pela precisão original, desempatar por código e comparar números em testes com tolerância explícita.
- [Primeiro build precisa de rede e a base hoje está não rastreada no Git] → incluir o arquivo fornecido na entrega reproduzível antes do aceite; fixar dependências e documentar os pré-requisitos reais. O usuário não deve baixar ou copiar a base manualmente para iniciar.
- [Configurações padrão Laravel podem escrever no SQLite] → desabilitar migrations/scripts automáticos e drivers de sessão/cache/fila em banco; impor leitura no mount e na conexão; verificar rejeição de escrita.

## Migration Plan

Não há migração de dados ou sistema anterior. Após autorização para implementar, executar os incrementos de `tasks.md`, mantendo todos os testes que dependem de fixtures fora da base original. A publicação local consiste na entrega completa e no comando Compose.

Encerrar com `docker compose down`. O rollback de uma implementação consiste em retornar à revisão anterior de imagens/código e reiniciar; a fonte censitária continua intacta. Armazenamento de runtime não contém dados censitários e pode ser reconstruído automaticamente. Esta etapa termina na revisão dos artefatos; o apply depende de uma nova solicitação do usuário.
