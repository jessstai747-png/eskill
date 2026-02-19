## ADDED Requirements

### Requirement: Conexão com a API Brevo
O sistema SHALL permitir configurar e autenticar chamadas à API Brevo via variável de ambiente `BREVO_API_KEY`.

#### Scenario: API Key ausente
- **WHEN** uma chamada da integração Brevo for executada sem `BREVO_API_KEY`
- **THEN** o sistema SHALL retornar erro controlado e registrar evento de monitoramento sem expor segredos

#### Scenario: Health check OK
- **WHEN** o endpoint de health da integração for chamado com credenciais válidas
- **THEN** o sistema SHALL retornar `connected=true` e latência/metadata de resposta

### Requirement: CRUD de Contatos
O sistema SHALL suportar operações CRUD de contatos (create, read, update, delete) na Brevo.

#### Scenario: Criar contato
- **WHEN** um usuário autenticado solicitar criação de contato com email válido
- **THEN** o sistema SHALL criar o contato na Brevo e retornar o identificador/estado relevante

#### Scenario: Atualizar contato
- **WHEN** um usuário autenticado solicitar atualização de atributos de um contato existente
- **THEN** o sistema SHALL atualizar os dados na Brevo e invalidar caches relacionados

#### Scenario: Remover contato
- **WHEN** um usuário autenticado solicitar remoção de um contato existente
- **THEN** o sistema SHALL remover o contato na Brevo e invalidar caches relacionados

### Requirement: Tratamento de Erros e Retentativas
O sistema SHALL mapear códigos HTTP e aplicar retentativas para falhas temporárias.

#### Scenario: Rate limit (429)
- **WHEN** a Brevo responder com HTTP 429
- **THEN** o sistema SHALL aplicar retentativas com backoff e retornar erro consistente se exceder tentativas

#### Scenario: Erro não transitório (400/401/403)
- **WHEN** a Brevo responder com erro 4xx não transitório
- **THEN** o sistema SHALL não retentar e SHALL retornar erro validado ao consumidor interno

