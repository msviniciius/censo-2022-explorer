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

O sistema SHALL somar os valores conhecidos de `demografia.homens` e `demografia.mulheres`, separadamente, vinculados pelos códigos dos setores. `setor.populacao` MUST continuar sendo a fonte da população total. A ausência de linha demográfica ou um valor nulo MUST significar dado indisponível, nunca zero presumido. Cada métrica demográfica MUST informar valor, setores com valor conhecido, total de setores e estado `completo`, `parcial` ou `indisponivel`. Se nenhum valor for conhecido, o valor MUST ser `null`; um zero conhecido MUST continuar sendo zero. As telas MUST identificar explicitamente somas parciais. A distribuição percentual por sexo SHALL usar `SUM(homens) + SUM(mulheres)` do território como denominador e cada soma separada como numerador, multiplicando por 100. MUST NOT usar a população setorial, `SUM(moradores)` ou `SUM(homens + mulheres)` como denominador. Denominador nulo ou zero MUST produzir percentuais `null`. A apresentação MUST identificar a distribuição como referente aos valores demográficos conhecidos, preservando os avisos de cobertura.

#### Scenario: Demografia parcial não reduz a população
- **WHEN** dois setores somam 300 habitantes e somente um informa 40 homens e 60 mulheres
- **THEN** a população continua 300, homens retorna 40 e mulheres 60, ambos parciais com cobertura de um entre dois setores

#### Scenario: Cobertura independente por métrica
- **WHEN** um setor possui homens igual a zero e mulheres nulo, sem outros setores
- **THEN** homens retorna zero completo e mulheres retorna `null` indisponível

#### Scenario: Percentuais por sexo sobre valores conhecidos
- **WHEN** um território tem população setorial 300, soma conhecida de homens 40 e de mulheres 60
- **THEN** os percentuais são 40% e 60%, calculados sobre 100, sem alterar a população 300 nem ocultar a cobertura parcial

#### Scenario: Denominador demográfico indisponível ou zero
- **WHEN** a soma de homens mais a soma de mulheres é nula ou zero
- **THEN** ambos os percentuais são `null`, sem substituir valores ausentes por zero

### Requirement: Distribuição urbana e rural

O sistema SHALL distribuir a quantidade de setores do território: `total` é a contagem de todos os setores, `urban` conta `situacao = 'Urbana'`, `rural` conta `situacao = 'Rural'` e `unclassified` conta situação NULL ou diferente das categorias reconhecidas. `unclassified` SHALL usar o rótulo `Não informada`; situação NULL MUST NOT ser convertida para Rural. Cada setor MUST ser contado uma única vez e `urban + rural + unclassified` MUST corresponder a `total` e a `total_setores`.

Para `total > 0`, os percentuais SHALL ser `urban_pct = urban / total × 100`, `rural_pct = rural / total × 100` e `unclassified_pct = unclassified / total × 100`. A distribuição MUST NOT usar `SUM(setor.populacao)`; a população total SHALL permanecer uma métrica independente calculada por essa soma. Categorias ausentes MUST retornar contagem e percentual zero quando houver setores. Sem setores, todas as contagens MUST ser zero e os três percentuais MUST ser `null`.

#### Scenario: Situação não informada preserva a contagem de setores
- **WHEN** há seis setores urbanos, três rurais e um com situação NULL
- **THEN** total é 10, urban é 6/60%, rural é 3/30% e unclassified é 1/10%, sem incluir o setor NULL em Rural

#### Scenario: Distribuição independe da população residente
- **WHEN** um setor urbano tem 900 habitantes e um rural tem 100 habitantes
- **THEN** a distribuição retorna total 2, urban 1/50%, rural 1/50% e unclassified 0/0%, enquanto a população total permanece 1.000

#### Scenario: Setores com população total zero
- **WHEN** dois setores urbanos e dois rurais têm população total zero
- **THEN** total é 4, urban é 2/50%, rural é 2/50% e unclassified é 0/0%

#### Scenario: Categoria não reconhecida
- **WHEN** um setor tem situação NULL e outro tem situação diferente de Urbana e Rural
- **THEN** total é 2, unclassified é 2/100% e urban e rural são 0/0%

#### Scenario: Território sem setores
- **WHEN** o território não possui setores
- **THEN** total, urban, rural e unclassified são zero e os três percentuais são `null`

### Requirement: Precisão e apresentação dos agregados

A API SHALL retornar números sem formatação local, códigos em strings e ausência de valor como `null`. As duas telas SHALL usar português do Brasil, contagens inteiras, área e densidade com duas casas decimais, percentuais com uma casa decimal, unidades visíveis e `Não disponível` para valores nulos. O arredondamento MUST acontecer apenas na apresentação.

#### Scenario: Formatação não muda a classificação
- **WHEN** duas densidades distintas são visualmente iguais após arredondamento
- **THEN** a API conserva os valores calculados e o ranking mantém a ordem pela precisão original
