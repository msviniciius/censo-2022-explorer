# Tasks

Plano de implementação e verificação incremental. Executar os grupos na ordem abaixo; cada incremento inclui a verificação do comportamento que introduz.

Rastreabilidade dos critérios de aceite:









| Capacidade / decisão                                              | Implementação e verificação                            |
| ------------------------------------------------------------------ | ---------------------------------------------------------- |
| census-aggregation — fonte, fórmulas, cobertura e distribuição | 2.1–2.5; apresentação em 3.3; integração em 6.1 e 6.3 |
| municipal-query — busca, detalhes, acessibilidade e estados       | 3.1–3.5; integração em 6.2                              |
| state-query — seleção, agregados, ranking e paginação         | 4.1–4.5; integração em 6.1–6.2                         |
| container-runtime — comando único, preservação e prontidão    | 1.2–1.3, 2.1, 5.1–5.4; aceite limpo em 6.3               |
| Arquitetura Controller → Service → Query → SQLite               | 2.2, 3.2, 4.1–4.2 e 5.3; revisão de integração em 6.4  |

## 1. Estrutura e ambiente de desenvolvimento reproduzível

- [X] 1.1 Criar `backend/` com Laravel 13/PHP 8.4 e `frontend/` com Vue 3, TypeScript estrito, Vite e Tailwind; fixar dependências e lockfiles; verificar instalação pelos lockfiles, checagem de tipos e build do frontend dentro de containers, sem executar scripts que criem ou migrem a base censitária.
- [X] 1.2 Criar a estrutura inicial de Dockerfiles em múltiplos estágios e Compose com PHP-FPM e Nginx, porta 8080 e builds sem dependências no host; verificar `docker compose config` e construção das imagens com as extensões PHP necessárias, incluindo SQLite e `intl`.
- [X] 1.3 Incluir o `censo.sqlite` fornecido na entrega completa e documentar no README os pré-requisitos e o comando de inicialização; verificar tamanho/hash contra o design e que uma cópia da entrega contém o arquivo sem download ou cópia manual posterior.

## 2. Leitura censitária e regras compartilhadas

- [X] 2.1 Configurar conexão censitária somente leitura, separando-a de storage, cache e sessão; adicionar fixtures SQLite temporárias com o esquema observado; verificar leitura bem-sucedida, rejeição de escrita e ausência de qualquer migration/seed aplicada ao original.
- [X] 2.2 Implementar Queries de identidade e agregação municipal/estadual e Services de composição dos resultados, sem acesso direto ao banco por Services; verificar com fixtures soma única por setor, inclusão de setores sem demografia e identidade territorial independente de haver setores.
- [X] 2.3 Implementar população, área e densidade como métricas independentes e distribuição por contagem de setores `urban`, `rural` e `unclassified` (Não informada), com percentuais sobre o total de setores; verificar soma das contagens igual ao total, NULL/categoria desconhecida fora de Rural, populações desiguais ou zero sem afetar a distribuição, território vazio com contagens zero/percentuais nulos e o cálculo de 300 habitantes/10 km² e área zero, usando a tolerância numérica do design.
- [X] 2.4 Implementar valores, percentuais sobre `SUM(homens) + SUM(mulheres)`, cobertura independente de homens/mulheres e contagem estadual de setores sem município identificável; verificar denominador diferente da população setorial, percentuais nulos para denominador nulo/zero, nulos, zeros conhecidos, demografia ausente, estados completo/parcial/indisponível e inclusão dos setores do registro `.` apenas nos agregados da UF.
- [X] 2.5 Documentar fórmulas, DTOs e tratamento das lacunas nos documentos de desenvolvimento; verificar resultados do município `1100015` e da UF `43` contra as referências do design e consultas SQL independentes, preservando o hash original.

## 3. Incremento completo de consulta municipal

- [X] 3.1 Implementar Query de autocomplete com normalização Unicode, parâmetros e escape de busca literal; verificar acentos, caixa, homônimos, ordenação, limite de dez, termo curto/sem resultado, exclusão do registro inválido e caracteres `%`, `_`, aspas e escape.
- [X] 3.2 Expor endpoints de busca e detalhes por Controller → Service → Query, com validação e envelopes JSON do design; verificar contratos HTTP 200/404/422, parâmetros com tipo inválido, códigos em strings e agregados tipados; documentar exemplos dos dois endpoints.
- [X] 3.3 Criar estrutura da SPA, rotas e navegação das duas telas e componentes de apresentação dos agregados; verificar formatação pt-BR, unidades, valores nulos, cobertura parcial e distribuição por quantidade de setores e por sexo (esta última rotulada como valores conhecidos) com testes de componente, além de recarga direta das rotas.
- [X] 3.4 Implementar autocomplete acessível e seleção municipal com debounce de 300 ms; verificar com testes de interação o limite mínimo, setas/Enter/Escape, limpeza da seleção ao editar e descarte de respostas obsoletas tanto da busca quanto dos detalhes.
- [X] 3.5 Concluir estados inicial/carregando/vazio/sucesso/erro e retry da consulta municipal; verificar falhas recuperáveis e consulta real pelo navegador; documentar no README o fluxo municipal e a interpretação dos avisos de cobertura.

## 4. Incremento completo de consulta estadual e ranking

- [X] 4.1 Expor lista de UFs e detalhes estaduais pelo fluxo de camadas definido; verificar lista ordenada, Distrito Federal, UF inexistente/malformada, contrato dos agregados e aviso de setores não identificáveis; documentar os endpoints.
- [X] 4.2 Implementar Query de ranking agregado por UF antes da paginação e seu Service/Controller; verificar densidade decrescente sem arredondamento, empates por código, nulos no final, municípios sem setores, exclusão de registros inválidos e isolamento entre UFs.
- [ ] 4.3 Implementar validação e metadados da paginação; verificar padrões `page=1`/`per_page=25`, opções exclusivas 25/50/100, rejeição de tamanhos fora dessas opções e parâmetros não inteiros, negativos e zero, página além da última, lista vazia, fixture de 55 municípios em três páginas de tamanho 25 sem repetição/omissão e Minas Gerais com 853 registros em 35 páginas; documentar exemplos de resposta e erro.
- [ ] 4.4 Implementar seleção de UF, agregados e tabela de ranking com posição, população, área e densidade, usando componentes compartilhados; verificar controles anterior/próxima, seletor 25/50/100 com padrão 25, retorno à página 1 e consulta server-side ao trocar tamanho, indicação de total/página, formatação e usabilidade por teclado e em tela estreita.
- [ ] 4.5 Implementar estados independentes e retry para UFs, agregados e ranking; verificar troca de UF durante requisição, retorno à página 1, descarte de respostas anteriores e preservação dos agregados quando somente o ranking falhar; documentar o fluxo estadual e a diferença entre totais estaduais e municípios elegíveis ao ranking.

## 5. Inicialização automática e prontidão da entrega

- [X] 5.1 Completar entrypoint com configuração padrão, chave persistida em storage separado e preparação automática das permissões; verificar inicialização sem `.env` ou volumes prévios e reinicialização sem passos adicionais, com cache/sessão fora da base censitária.
- [X] 5.2 Finalizar montagem somente leitura com `create_host_path: false` e validação de arquivo/esquema na inicialização; verificar, em cópias isoladas, arquivo ausente, ilegível, corrompido e esquema incompatível, com falha legível sem banco substituto e sem prontidão indevida.
- [X] 5.3 Implementar `/api/health` pelo fluxo de camadas e healthcheck Compose, com logs e respostas JSON genéricas; verificar HTTP 200 com base disponível, 503 quando a leitura falhar, API desconhecida com 404 JSON e ausência de stack trace/SQL/caminhos na resposta.
- [X] 5.4 Finalizar imagens de runtime com assets e dependências incorporados, Nginx na mesma origem e fallback das rotas da interface; verificar que não dependem de Vite/Node em execução ou bind mounts de código; atualizar README com endereço, execução, encerramento, testes e diagnósticos de inicialização, conferindo os comandos documentados.

## 6. Aceite integrado da primeira versão

- [ ] 6.1 Executar a suíte backend e checar contratos/ranking contra agregação independente da base real; medir planos e latências de autocomplete, detalhes e ranking sem N+1, registrando ambiente e limitações, sem alterar índices ou o arquivo de origem.
- [ ] 6.2 Executar checagem TypeScript, build, testes de componentes e fluxo automatizado em navegador contra os containers: consulta municipal com distribuição, troca para consulta estadual, seleção de UF e avanço/retorno de página; verificar também teclado, recuperação de falha e recarga das duas rotas.
- [ ] 6.3 Em cópia completa isolada sem dependências, `.env`, assets ou volumes prévios, iniciar exclusivamente com `docker compose up --build`; verificar saúde, ambas as consultas e paginação, encerrar/reiniciar e comparar o SHA-256 do SQLite antes/depois; registrar o resultado do aceite em ambiente limpo.
- [ ] 6.4 Revisar o conjunto entregue contra as quatro specs e o fluxo das camadas, executar `openspec validate build-census-explorer --strict` e registrar evidências dos critérios atendidos; verificar que apenas as funcionalidades da primeira versão foram implementadas antes de considerar a mudança concluída.
