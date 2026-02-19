## ADDED Requirements

### Requirement: Persistência local de entidades Brevo
O sistema SHALL persistir em banco de dados (MySQL/MariaDB) um espelho mínimo de listas e contatos da Brevo para auditoria, relatórios e reprocessamento.

#### Scenario: Upsert de lista após create/update
- **WHEN** uma lista for criada ou atualizada via integração Brevo
- **THEN** o sistema MUST gravar/atualizar o registro local correspondente (por `brevo_list_id`)
- **AND** MUST registrar `updated_at` e `last_synced_at`

#### Scenario: Soft-delete de lista após delete
- **WHEN** uma lista for removida via integração Brevo
- **THEN** o sistema MUST marcar o registro local como removido (soft-delete) e registrar timestamp

### Requirement: Operação de sync paginada e idempotente
O sistema SHALL oferecer um mecanismo de sincronização (pull) que percorra paginação/offset da Brevo com controle de erros e registro de execução.

#### Scenario: Sync bem-sucedido
- **WHEN** o operador acionar um sync de listas/contatos
- **THEN** o sistema MUST buscar páginas até o fim, persistir resultados e registrar uma execução com métricas (itens processados, duração)

#### Scenario: Sync resiliente a falhas transitórias
- **WHEN** a Brevo responder 429/5xx durante o sync
- **THEN** o sistema MUST aplicar retries com backoff e respeitar timeouts
- **AND** MUST registrar falha da execução se esgotar retries

### Requirement: Segurança de segredos
O sistema MUST obter `BREVO_API_KEY` exclusivamente via variáveis de ambiente e MUST NOT persistir ou logar esse segredo.

#### Scenario: Log seguro
- **WHEN** ocorrer erro upstream
- **THEN** o sistema MUST registrar somente metadados (status/operation/duração)
- **AND** MUST NOT incluir API keys em logs/DB/respostas
