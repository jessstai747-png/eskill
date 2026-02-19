## ADDED Requirements

### Requirement: Aplicar título otimizado com versionamento
O sistema SHALL permitir aplicar um título otimizado para um item do Mercado Livre via API, registrando snapshot e diff para rollback.

#### Scenario: Aplicação de título bem-sucedida
- **WHEN** o usuário autenticado chama `POST /api/seo/technical-sheet/items/{itemId}/apply-optimized-title` com um `title` válido
- **THEN** o sistema SHALL criar um snapshot do estado anterior
- **AND** SHALL atualizar o item no Mercado Livre (`PUT /items/{itemId}`)
- **AND** SHALL registrar uma entrada em `seo_optimization_history` com `change_type = 'title'`
- **AND** SHALL retornar `success=true` com o título aplicado

### Requirement: Aplicar descrição otimizada com versionamento
O sistema SHALL permitir aplicar uma descrição otimizada para um item do Mercado Livre via API, registrando snapshot e diff para rollback.

#### Scenario: Aplicação de descrição bem-sucedida
- **WHEN** o usuário autenticado chama `POST /api/seo/technical-sheet/items/{itemId}/apply-optimized-description` com `plain_text` válido
- **THEN** o sistema SHALL buscar o estado atual do item e a descrição atual
- **AND** SHALL criar um snapshot do estado anterior contendo a descrição atual
- **AND** SHALL atualizar a descrição no Mercado Livre via `PUT /items/{itemId}/description` com `plain_text`
- **AND** SHALL registrar uma entrada em `seo_optimization_history` com `change_type = 'description'`
- **AND** SHALL retornar `success=true` com a descrição aplicada

### Requirement: Falha do Mercado Livre não deve gravar estado aplicado
O sistema SHALL lidar com erros retornados pela API do Mercado Livre, retornando erro e não marcando a operação como aplicada.

#### Scenario: Erro do ML ao aplicar
- **WHEN** o Mercado Livre retorna erro ao aplicar título ou descrição
- **THEN** o sistema SHALL retornar `success=false` com detalhes do erro
- **AND** SHALL NOT retornar sucesso para a operação

