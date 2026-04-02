# Pesquisa profunda — marca AWA no Mercado Livre

**Data da coleta:** 2026-03-30
**Escopo principal:** anúncios com **marca cadastrada exatamente como `AWA`** no Mercado Livre Brasil
**Objetivo:** mapear categorias, sellers, sinais de SEO/conversão e indicar a melhor abordagem para inventário completo

---

## Resumo executivo

A leitura pública mais fiel para o pedido não é a busca textual ampla por `marca awa`, e sim o recorte com o filtro exato de marca do Mercado Livre:

- **Busca ampla por texto** `marca awa`: **152 resultados**
  - mistura anúncios realmente da marca `AWA` com ruído semântico/comercial
  - aparecem categorias fora do core moto (ex.: casa, fitness, papelaria, construção etc.)
- **Busca com marca exata** `AWA` (`BRAND_7297804`): **124 resultados**
  - este é o recorte principal desta pesquisa
  - o mix fica quase todo concentrado em peças e acessórios para motos
- **Recorte “Somente lojas oficiais” dentro da marca exata**: **101 resultados** distribuídos em **11 lojas oficiais**

### Leitura prática

1. **A marca `AWA` está fortemente concentrada em motopeças e acessórios para motos.**
2. **A busca textual ampla superestima o universo real** porque o termo “awa” puxa ruído; para análise operacional, o recorte correto é o filtro exato da marca.
3. **O padrão de SEO dominante é altamente transacional**, com foco em:
   - tipo da peça
   - aplicação/modelo da moto
   - anos/modelos compatíveis
   - atributo técnico curto (`rosca`, `par`, `modelo original`, `cromado`, `preto`)
4. **Conversão visível no SERP parece muito apoiada em logística e oferta**, não só em título:
   - frete grátis muito forte
   - presença relevante de Full
   - descontos frequentes
   - notas geralmente entre 4.5 e 5.0 nos cards visíveis
5. **Não foi possível garantir, de forma pública e anônima, o nome de 100% dos sellers de todos os 124 anúncios.**
   O Mercado Livre omite seller em muitos cards e bloqueou tentativas complementares
   via API pública e fetch anônimo de páginas de produto.

---

## Fontes usadas

### Evidência pública do Mercado Livre

#### Recorte amplo por texto

- `https://lista.mercadolivre.com.br/marca-awa`
- paginações públicas relacionadas (`_Desde_51_`, `_Desde_101_`, `_Desde_151_`)

#### Recorte principal — marca exata `AWA`

- `https://lista.mercadolivre.com.br/marca-awa_BRAND_7297804_NoIndex_True`
- `https://lista.mercadolivre.com.br/marca-awa_BRAND_7297804_Desde_51_NoIndex_True`
- `https://lista.mercadolivre.com.br/marca-awa_Desde_101_BRAND_7297804_NoIndex_True`

#### Lojas oficiais dentro da marca exata

- `https://lista.mercadolivre.com.br/marca-awa_Loja_all_BRAND_7297804_NoIndex_True`
- `https://lista.mercadolivre.com.br/marca-awa_Desde_51_Loja_all_BRAND_7297804_NoIndex_True`
- `https://lista.mercadolivre.com.br/marca-awa_Desde_101_Loja_all_BRAND_7297804_NoIndex_True`

### Evidência interna do projeto

- `docs/archive/reports/BRAND_ANALYSIS_MODULE.md`
- `ENDPOINTS.md`
- `bin/raiox-conta.php`
- `database/migrations/2026_01_23_create_seo_strategies_tables.sql`
- `IMPLEMENTACAO_POR_FASES.md`

---

## Metodologia usada

### 1) Separação entre ruído e marca real

Primeiro foi avaliada a busca textual ampla `marca awa`.
Ela mostrou **152 resultados**, mas com ruído claro de indexação e recuperação semântica.
Por isso, a pesquisa foi recalibrada para o filtro **exato de marca `AWA`**,
identificado publicamente como `BRAND_7297804`.

### 2) Consolidação por páginas

Foram consolidadas as páginas 1, 2 e 3 do recorte exato da marca:

- página 1: base da marca exata
- página 2: `_Desde_51_`
- página 3: `_Desde_101_`

### 3) Varredura específica de lojas oficiais

Foi feito um segundo recorte em **`Somente lojas oficiais`** para encontrar sellers visíveis com maior confiabilidade.

### 4) Cruzamento com o projeto

Os dados públicos foram cruzados com a estrutura interna do projeto para interpretar:

- consistência de marca
- CTR/conversão por keyword/categoria
- sellers conhecidos no ecossistema AWA
- melhor abordagem técnica para inventário total

---

## Categorias encontradas

## Recorte principal — marca exata `AWA` (`124 resultados`)

| Categoria | Resultados | Leitura |
| --- | ---: | --- |
| `Peças de Motos e Quadriciclos` | 102 | Categoria dominante; core da marca no ML |
| `Aces. de Motos e Quadriciclos` | 21 | Segunda categoria mais relevante; complementares e retrofit |
| `Aces. de Carros e Caminhonetes` | 1 | Residual/outlier |

### Conclusão por categoria

A marca `AWA`, quando observada pelo filtro exato, está **praticamente toda posicionada no vertical de motocicletas**. O peso real está em:

- retrovisores/espelhos
- guidões
- manetes
- pedaleiras/borrachas/estribos
- bagageiros/bauleto/base/suporte
- acessórios de aplicação específica por modelo/ano

---

## Diferença entre busca ampla e marca exata

A busca ampla `marca awa` retornou **152 resultados** e distribuiu itens em categorias extras que não devem ser tratadas como o núcleo estratégico da marca:

| Categoria da busca ampla | Resultados aproximados |
| --- | ---: |
| `Peças de Motos e Quadriciclos` | 109 |
| `Aces. de Motos e Quadriciclos` | 15 |
| `Casa, Móveis e Decoração` | 7–8 |
| `Esportes e Fitness` | 6 |
| `Arte, Papelaria e Armarinho` | 3 |
| `Construção` | 2 |
| outras categorias residuais | 1 cada |

### Interpretação

Esse universo ampliado mistura:

- produtos realmente da marca `AWA`
- ruído de catálogo/pesquisa
- resultados contaminados por termos parecidos ou relevância comercial

**Para SEO, pricing, sellers e benchmark real da marca, o dataset correto é o filtro exato com 124 resultados.**

---

## Sellers identificados

## Sellers oficiais encontrados publicamente

O Mercado Livre informa **11 lojas oficiais** no recorte da marca exata. Pelas páginas públicas e cards com seller visível, foi possível identificar nominalmente os seguintes sellers:

| Seller oficial | Evidência pública | Quantidade exposta no filtro |
| --- | --- | ---: |
| `Mds73 Pecas e Acessorios` | filtro + múltiplos cards | 30 |
| `Spec` | filtro + múltiplos cards | 13 |
| `Novaes Moto Pecas` | filtro + múltiplos cards | 9 |
| `JATEMMAIS` | filtro + múltiplos cards | 9 |
| `Laramassa Moto Pecas` | filtro + múltiplos cards | 6 |
| `LARAMASSA` | filtro + múltiplos cards | 4 |
| `MOVEGIRO` | card público no recorte oficial | não exposto no filtro recuperado |
| `RO MOSCA BRANCA` | card público no recorte oficial | não exposto no filtro recuperado |
| `SUPER RACING MOTO PECAS` | cards públicos no recorte oficial | não exposto no filtro recuperado |
| `SMX MOTOPECAS` | cards públicos no recorte oficial | não exposto no filtro recuperado |
| `+ 1 loja oficial adicional` | ML informa 11 lojas oficiais, mas o nome não ficou totalmente acessível no extrator público | não identificado nominalmente |

### Observação importante

O extrator público conseguiu provar com segurança:

- **existem 11 lojas oficiais** no recorte `AWA`
- **10 delas** foram identificadas nominalmente por evidência direta nas páginas públicas
- **1 loja oficial adicional** aparece no total do ML, mas o nome não foi recuperado integralmente via extração anônima

Não vou inventar o 11º nome — SEO gosta de precisão, e eu também.

## Sellers internos já conhecidos no projeto

O próprio repositório já registra contas ligadas ao ecossistema AWA:

| Seller/Conta interna | Fonte |
| --- | --- |
| `AWA_MOTOS` | `ENDPOINTS.md` |
| `AWA_ACESSORIOS` | `ENDPOINTS.md` |

### Leitura estratégica

Essas contas internas são importantes para benchmark e auditoria, mas **não apareceram explicitamente nos cards públicos do recorte pesquisado** durante esta coleta. Isso pode significar:

- contas com catálogo fora da primeira exposição pública
- catálogo ativo em outros recortes/ordenamentos
- uso operacional interno sem visibilidade direta neste SERP específico
- ou simplesmente limitação da camada pública consultada

---

## Padrões de produtos dominantes

Pelo conjunto de títulos recuperados no recorte exato de marca, os clusters mais fortes são:

### 1. Retrovisores / espelhos

Cluster mais dominante do dataset.

Padrões recorrentes:

- `retrovisor`
- `espelho retrovisor`
- `par retrovisor`
- aplicação por moto e ano
- atributos como `rosca`, `modelo original`, `mini`, `articulado`, `rebaixado`

### 2. Guidões

Padrões recorrentes:

- `guidão`
- `guidao`
- `cromado`
- `com bucha`
- aplicação por modelo (`Twister`, `Bros`, `XRE`, `Fazer`, `Hornet` etc.)

### 3. Manetes

Padrões recorrentes:

- `manete freio`
- `manete embreagem`
- `par de manetes`
- compatibilidade por moto/ano

### 4. Bauletos / Proos / bagageiros / suporte de baú

Padrões recorrentes:

- `Bauleto Awa Proos`
- `29 litros`
- `41 litros`
- `base universal`
- `suporte`, `bagageiro`, `fixação`

### 5. Apoio / estribo / pedaleira / borracha

Padrões recorrentes:

- `borracha estribo`
- `pedaleira`
- `apoio`
- aplicação por CG/Biz/Bros/Yes/Intruder etc.

---

## Sinais de SEO e conversão observados no SERP

## Indicadores estruturais do próprio Mercado Livre

No recorte exato da marca `AWA` (`124 resultados`), os filtros públicos mostraram:

| Sinal | Quantidade |
| --- | ---: |
| `Frete grátis` | 117 |
| `Full` | 64 |
| `Parcelamento sem juros` | 68 |
| `Mais de 5% OFF` | 54 |
| `Mais de 10% OFF` | 45 |
| `Mais de 15% OFF` | 18 |
| `Mais de 20% OFF` | 11 |
| `Mais de 25% OFF` | 8 |
| `Mais de 30% OFF` | 6 |
| `Mais de 40% OFF` | 4 |
| `Mais de 60% OFF` | 1 |
| `Melhores vendedores` | 105 |
| `É ajustável` | 59 |
| `É kit` | 3 |
| `Estilo urbano` | 80 |
| `Touring` | 6 |
| `Esportivo` | 1 |

## Indicadores no recorte de lojas oficiais (`101 resultados`)

| Sinal | Quantidade |
| --- | ---: |
| `Frete grátis` | 97 |
| `Full` | 17 |
| `Parcelamento sem juros` | 32 |
| `Mais de 5% OFF` | 26 |
| `Mais de 10% OFF` | 13 |
| `Mais de 20% OFF` | 5 |
| `Mais de 30% OFF` | 4 |
| `Oferta do dia` | 2 |
| `Urbano` | 59 |

### O que isso indica

A conversão no universo `AWA` parece depender de um pacote muito claro:

1. **Título aplicado corretamente**
2. **Preço competitivo**
3. **Frete grátis**
4. **Full quando possível**
5. **Desconto visível**
6. **nota/reputação já consolidada**

Em outras palavras: **título bom sem oferta/logística forte não parece ser o jogo completo** nesse mercado.

---

## Padrão de título que mais aparece

O modelo dominante dos anúncios públicos segue algo próximo de:

`[Tipo da peça] [aplicação/modelo] [ano(s)] [atributo técnico curto] [AWA opcional]`

### Exemplos de blocos recorrentes

- tipo da peça:
  - `retrovisor`
  - `espelho retrovisor`
  - `guidão`
  - `manete`
  - `bagageiro`
  - `bauleto`
- aplicação:
  - `Honda Biz`
  - `CG Titan`
  - `Fan`
  - `Bros`
  - `XRE`
  - `Twister`
  - `Fazer`
  - `Factor`
  - `Lead`
  - `YBR`
- detalhe técnico:
  - `rosca esquerda`
  - `modelo original`
  - `par`
  - `articulado`
  - `cromado`
  - `preto`
  - `com bucha`

### Conclusão de SEO

Os anúncios que mais “conversam” com o padrão do ML e do mercado não vendem “marca pela marca”; eles vendem **compatibilidade + intenção de compra + detalhe técnico**.

A marca `AWA` funciona melhor como:

- **atributo canônico de marca** no cadastro
- **reforço de confiança** no título quando houver espaço
- **não** como único eixo do título

---

## Conversão SEO — leitura com base no projeto

O repositório reforça que a análise correta de SEO/conversão deve cruzar três camadas:

### 1. CTR / click rate por keyword e categoria

Em `database/migrations/2026_01_23_create_seo_strategies_tables.sql`, o projeto já estrutura:

- `click_rate`
- `conversion_rate`
- histórico por `keyword`
- performance por `category_id`

Ou seja: o próprio projeto foi desenhado para medir se o título melhora **clique** e **conversão**, não só estética textual.

### 2. Conversão por visitas e pedidos

Em `bin/raiox-conta.php`, o projeto calcula:

- visitas do seller
- pedidos
- conversão por período
- comparação entre janelas (`7d` vs `7d anteriores`)

Isso é exatamente o que deve ser usado para validar se uma mudança de SEO teve efeito real.

### 3. Consistência de marca

Em `docs/archive/reports/BRAND_ANALYSIS_MODULE.md`, o projeto já trata problemas que impactam a conversão indireta:

- `missing_brand`
- `brand_in_title_not_attribute`
- `wrong_brand`
- `misspelled_brand`

### Leitura final de conversão

Para a marca `AWA`, a hipótese mais forte é:

- **CTR melhora** quando o anúncio explicita melhor a aplicação da peça
- **conversão melhora** quando o título reduz ambiguidade e o anúncio mantém preço/logística competitivos
- **consistência de marca** é indispensável para escalar auditoria, catálogo e benchmark

---

## O que mais pesa para rankear/vender nesse universo AWA

### Fatores fortes

- compatibilidade clara por moto/modelo/ano
- frete grátis
- Full
- desconto visível
- reputação/nota do seller
- foto e oferta alinhadas com o título

### Fatores médios

- presença da marca `AWA` no título
- parcelamento sem juros
- uso de termos como `modelo original`

### Fatores fracos ou secundários

- título “genérico” só com marca
- branding isolado sem aplicação
- anúncios muito amplos, sem ano/modelo

---

## Melhor abordagem para o catálogo da AWA

## 1. Usar `AWA` como marca canônica

Para cadastro, auditoria e IA:

- **marca oficial/canônica:** `AWA`
- `Awa Motos` deve ser tratada como **contexto comercial / loja / operação**, não como valor de atributo de marca

## 2. Padronizar títulos no modelo de alta intenção

Formato recomendado:

`[peça principal] [moto/modelo] [ano(s)] [diferencial curto] AWA`

Exemplos de estrutura:

- `Retrovisor Honda Biz 125 2016 a 2023 Rosca Esquerda AWA`
- `Guidão CB Twister 250F 2016 a 2022 Cromado Com Bucha AWA`
- `Bauleto AWA Proos 41 Litros Com Base Universal`

## 3. Priorizar as duas categorias que realmente importam

Ordem de foco:

1. `Peças de Motos e Quadriciclos`
2. `Aces. de Motos e Quadriciclos`

## 4. Medir SEO por resultado real

Não validar melhoria por “título bonito”. Validar por:

- CTR antes/depois
- conversão antes/depois
- pedidos por item
- visitas por item
- impacto por categoria

---

## Limitações da coleta pública

Não foi possível garantir inventário público completo de todos os sellers com nome exposto porque:

1. **muitos cards do Mercado Livre não exibem o nome do seller** no extrato textual público
2. **páginas de produto redirecionaram para tracking/DoubleClick** quando consultadas anonimamente
3. **tentativas de API pública de busca retornaram `403 forbidden`**
4. **requisições automatizadas via terminal sofreram bloqueios/validação anti-bot**

### O que dá para afirmar com segurança

- a marca exata `AWA` tem **124 resultados** no recorte principal consultado
- existem **11 lojas oficiais** nesse universo
- foi possível nomear **10 lojas oficiais** publicamente
- o mix é predominantemente de motopeças/acessórios para motos
- o ranking/conversão aparentam depender fortemente de oferta + logística + aplicação técnica no título

---

## Melhor abordagem para listar 100% dos sellers sem escapar nenhum

Se o objetivo for fechar um inventário **completo, auditável e exportável**, a melhor abordagem não é scraping público anônimo. O melhor caminho é:

### Caminho recomendado

1. **usar o módulo interno de análise de marca do projeto**
   - `GET /api/brand/awa/sellers`
   - `GET /api/brand/awa/report`
   - `GET /api/brand/awa/analyze`

2. **executar a coleta autenticada com conta Mercado Livre válida**
   - isso permite capturar seller de item sem depender do card público
   - reduz ruído e bloqueio anti-bot

3. **exportar o inventário em CSV/JSON**
   - seller
   - item_id
   - título
   - categoria
   - marca cadastrada
   - frete/full
   - preço
   - reputação e localização quando disponíveis

4. **cruzar com `AWA_MOTOS` e `AWA_ACESSORIOS`** no ambiente interno
   - para distinguir seller próprio, seller parceiro e seller marketplace genérico

### Resultado esperado com a abordagem correta

- lista 100% fechada de sellers
- contagem por seller
- participação por categoria
- benchmark de SEO/conversão por seller
- auditoria de consistência de marca

---

## Conclusão final

A marca `AWA` no Mercado Livre, quando observada pelo filtro exato, está **claramente posicionada em motopeças e acessórios para motos**, com predominância absoluta de:

- retrovisores
- guidões
- manetes
- bagageiros/bauletos
- itens de aplicação específica por modelo e ano

O comportamento público do SERP mostra que a conversão nesse universo depende de um tripé:

1. **título com aplicação precisa**
2. **oferta/logística competitiva**
3. **reputação do seller**

Para estratégia operacional:

- trate `AWA` como marca canônica
- não use a busca textual ampla como fonte principal de decisão
- concentre SEO nas duas categorias moto
- valide qualquer melhoria por CTR/conversão, não por feeling

E, para a sua exigência de **“listar todos os sellers sem escapar um”**, a resposta honesta é:

- **publicamente e anonimamente:** chegou-se muito perto, com forte evidência, mas não com fechamento de 100% garantido
- **com a melhor abordagem técnica (autenticada + módulo interno):** o fechamento completo é viável

---

## Apêndice — sellers identificados nesta coleta

### Oficiais nomeados publicamente

- `Mds73 Pecas e Acessorios`
- `Spec`
- `Novaes Moto Pecas`
- `JATEMMAIS`
- `Laramassa Moto Pecas`
- `LARAMASSA`
- `MOVEGIRO`
- `RO MOSCA BRANCA`
- `SUPER RACING MOTO PECAS`
- `SMX MOTOPECAS`

### Contas internas conhecidas do ecossistema

- `AWA_MOTOS`
- `AWA_ACESSORIOS`

### Observação final do apêndice

- O Mercado Livre indicou **11 lojas oficiais** no total para o recorte consultado.
- A extração pública conseguiu nomear **10** delas com segurança.
- O nome da 11ª loja oficial não ficou integralmente acessível pela camada pública consultada nesta sessão.
