## ADDED Requirements

### Requirement: Seleção de loja concorrente (seller público)
O sistema SHALL permitir informar um `sellerId` público do Mercado Livre e obter um resumo da loja (nickname, reputação, total de anúncios) e uma listagem paginada de anúncios.

#### Scenario: Buscar seller válido
- **WHEN** o usuário informa um `sellerId` válido
- **THEN** o sistema retorna `seller_nickname`, `seller_reputation`, `total_items` e facets (marcas/categorias)

#### Scenario: Seller inválido ou indisponível
- **WHEN** o `sellerId` não existe ou a API pública falha
- **THEN** o sistema retorna erro compreensível e não cria jobs

### Requirement: Organização por categoria e filtro por marca
O sistema SHALL organizar/permitir visualizar anúncios por `category_id` e filtrar por marca (`BRAND`) quando disponível.

#### Scenario: Agrupar por categoria
- **WHEN** o frontend solicita a listagem do seller
- **THEN** o backend inclui facet de categorias (contagem por `category_id`) para organização

#### Scenario: Filtrar por marca
- **WHEN** o usuário seleciona uma marca
- **THEN** o sistema lista apenas anúncios cuja marca extraída corresponde (case-insensitive)

### Requirement: Ajuste de preço antes de executar
O sistema SHALL permitir escolher uma estratégia de preço e visualizar prévia de preço (antes/depois) para os itens selecionados.

#### Scenario: Prévia de preço com markup
- **WHEN** o usuário escolhe `markup_percent` e informa valor
- **THEN** o sistema retorna uma prévia de preço por item (source → target)

#### Scenario: Estratégia competitiva
- **WHEN** o usuário escolhe `competitive/aggressive/premium`
- **THEN** o sistema retorna a prévia com melhor esforço e marca itens sem dados suficientes

### Requirement: Clonagem em massa por seleção
O sistema SHALL permitir selecionar um conjunto de anúncios (por categoria, por marca, ou seleção manual) e disparar clonagem em massa para uma conta de destino.

#### Scenario: Criar job em massa
- **WHEN** o usuário confirma a seleção e parâmetros (target_account_id, estratégia de preço)
- **THEN** o sistema cria um job pai e itens filhos para processamento assíncrono

#### Scenario: Acompanhar progresso
- **WHEN** o usuário consulta o status do job
- **THEN** o sistema retorna contadores (total/processed/success/failed/duplicates) e amostra de erros

### Requirement: Guardrails de conteúdo (modo seguro)
O sistema SHALL operar em modo seguro por padrão, evitando copiar automaticamente conteúdo potencialmente autoral (descrição e imagens) sem confirmação explícita.

#### Scenario: Modo padrão
- **WHEN** o usuário inicia clonagem em massa sem flags de conteúdo
- **THEN** o sistema NÃO copia descrição/imagens automaticamente; mantém campos mínimos para criação

#### Scenario: Confirmação explícita
- **WHEN** o usuário habilita flags como `include_description` e/ou `include_pictures`
- **THEN** o sistema registra a decisão no job e exibe avisos claros no frontend

