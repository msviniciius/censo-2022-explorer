# Spec Delta

## Purpose

Garantir que consultas municipais e estaduais apresentem métricas consistentes e rastreáveis ao banco censitário fornecido, explicitando lacunas sem inventar dados.

## ADDED Requirements

### Requirement: Fonte de verdade e abrangência territorial

O sistema MUST obter os dados censitários exclusivamente de `censo.sqlite` e preservar seu conteúdo. Códigos territoriais MUST ser strings. Municípios selecionáveis MUST ter código de sete dígitos e nome não vazio. Agregados estaduais MUST incluir todos os setores vinculados à UF, inclusive os de registros municipais não selecionáveis, e informar quantos setores estão nessa condição.

#### Scenario: Registro municipal sem identificação utilizável
- **WHEN** a base contém o município `.` sem nome, vinculado à UF `43`, com dois setores
- **THEN** esse registro não aparece na busca nem no ranking, seus setores contribuem para os agregados estaduais e a resposta estadual informa dois setores sem identificação municipal válida

#### Scenario: Consulta preserva o arquivo original
- **WHEN** consultas municipais, estaduais e de ranking são executadas
- **THEN** o conteúdo de `censo.sqlite` permanece idêntico ao anterior e nenhuma tabela ou índice é criado nele

### Requirement: População, área e densidade territoriais

O sistema SHALL somar `setor.populacao` e `setor.area_km2` uma única vez por setor do território. A densidade MUST ser a população total dividida pela área total, em habitantes por km², sem arredondamento intermediário e sem calcular média das densidades setoriais ou municipais. Área total zero MUST produzir densidade `null`. Território sem setores MUST retornar população, área e densidade `null` e contagem de setores zero.

#### Scenario: Densidade calculada sobre totais
- **WHEN** um território contém setores de 100 habitantes/1 km² e 200 habitantes/9 km²
- **THEN** os agregados são 300 habitantes, 10 km² e 30 hab/km², independentemente da presença de demografia

#### Scenario: Área zero e ausência de setores
- **WHEN** um território tem área total zero ou não possui setores
- **THEN** a densidade é `null`, sem divisão por zero, infinito ou valor artificial

#### Scenario: Agregado estadual ponderado pela área
- **WHEN** uma UF contém municípios com populações e áreas diferentes
- **THEN** sua densidade usa a soma das populações dividida pela soma das áreas de todos os setores da UF

### Requirement: Agregados demográficos com cobertura explícita

O sistema SHALL somar os valores conhecidos de `demografia.homens` e `demografia.mulheres`, separadamente, vinculados pelos códigos dos setores. `setor.populacao` MUST continuar sendo a fonte da população total. A ausência de linha demográfica ou um valor nulo MUST significar dado indisponível, nunca zero presumido. Cada métrica demográfica MUST informar valor, setores com valor conhecido, total de setores e estado `completo`, `parcial` ou `indisponivel`. Se nenhum valor for conhecido, o valor MUST ser `null`; um zero conhecido MUST continuar sendo zero. As telas MUST identificar explicitamente somas parciais.

#### Scenario: Demografia parcial não reduz a população
- **WHEN** dois setores somam 300 habitantes e somente um informa 40 homens e 60 mulheres
- **THEN** a população continua 300, homens retorna 40 e mulheres 60, ambos parciais com cobertura de um entre dois setores

#### Scenario: Cobertura independente por métrica
- **WHEN** um setor possui homens igual a zero e mulheres nulo, sem outros setores
- **THEN** homens retorna zero completo e mulheres retorna `null` indisponível

### Requirement: Distribuição urbana e rural

O sistema SHALL distribuir a população de `setor.populacao` pelas situações `Urbana`, `Rural` e `Não informada`, esta última para situação nula, vazia ou diferente dos dois valores reconhecidos. MUST retornar contagem populacional e percentual do total para cada categoria, incluindo categorias com zero. A soma das populações das categorias MUST corresponder à população total do território; percentuais MUST ser `null` quando o total for zero ou indisponível. Territórios sem setores MUST retornar valores populacionais `null` em todas as categorias.

#### Scenario: Situação não informada preserva o total
- **WHEN** há 60 habitantes em setores urbanos, 30 em rurais e 10 em setores sem situação
- **THEN** a distribuição retorna 60/60%, 30/30% e 10/10%, respectivamente, com total 100

#### Scenario: População total zero
- **WHEN** os setores do território têm população total zero
- **THEN** as três contagens são zero e seus percentuais são `null`

### Requirement: Precisão e apresentação dos agregados

A API SHALL retornar números sem formatação local, códigos em strings e ausência de valor como `null`. As duas telas SHALL usar português do Brasil, contagens inteiras, área e densidade com duas casas decimais, percentuais com uma casa decimal, unidades visíveis e `Não disponível` para valores nulos. O arredondamento MUST acontecer apenas na apresentação.

#### Scenario: Formatação não muda a classificação
- **WHEN** duas densidades distintas são visualmente iguais após arredondamento
- **THEN** a API conserva os valores calculados e o ranking mantém a ordem pela precisão original
