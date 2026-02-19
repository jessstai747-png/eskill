# Integração Brevo (API real)

## Objetivo
Integração robusta com a API da Brevo (Sendinblue) para operações de contatos (CRUD), com:
- autenticação via API key
- tratamento de erros HTTP
- parsing de resposta (JSON/XML)
- retentativas com backoff/circuit breaker
- cache para leituras e invalidação após escrita
- persistência em banco (MySQL/MariaDB) para auditoria e reprocessamento
- testes unitários e teste de integração real condicional

## Variáveis de ambiente
- `BREVO_API_KEY` (obrigatória)
- `BREVO_BASE_URL` (opcional, default: `https://api.brevo.com/v3`)
- `BREVO_TIMEOUT_SECONDS` (opcional, default: `10`)
- `BREVO_CACHE_TTL_SECONDS` (opcional, default: `300`)
- `BREVO_TEST_EMAIL` (opcional; usado apenas em teste de integração)

## Endpoints Brevo utilizados (upstream)
- `GET /account` (health check)
- `POST /contacts` (create)
- `GET /contacts/{email}` (read)
- `PUT /contacts/{email}` (update)
- `DELETE /contacts/{email}` (delete)
- `GET /contacts` (list)
- `GET /contacts/lists` (list lists)
- `POST /contacts/lists` (create list)
- `GET /contacts/lists/{listId}` (get list)
- `PUT /contacts/lists/{listId}` (update list)
- `DELETE /contacts/lists/{listId}` (delete list)
- `POST /contacts/lists/{listId}/contacts/add` (add contacts)
- `POST /contacts/lists/{listId}/contacts/remove` (remove contacts)

## Endpoints internos (este sistema)
Todos exigem sessão autenticada.

- `GET /api/integrations/brevo/health`
  - Executa um health check no upstream e salva o último resultado em cache (10 min).
- `GET /api/integrations/brevo/status`
  - Retorna o último health check conhecido (se existir) + último sync (listas/contatos) quando disponível.
- `GET /api/integrations/brevo/contacts`
  - Query params: `limit`, `offset`, `modifiedSince`, `sort`
- `POST /api/integrations/brevo/contacts`
  - Body JSON: `{ "email": "...", "attributes": {...}, "listIds": [...], "updateEnabled": true }`
- `GET /api/integrations/brevo/contacts/{email}`
- `PUT /api/integrations/brevo/contacts/{email}`
  - Body JSON: `{ "attributes": {...}, "listIds": [...], "unlinkListIds": [...] }`
- `DELETE /api/integrations/brevo/contacts/{email}`

- `GET /api/integrations/brevo/lists`
  - Query params: `limit`, `offset`, `sort`
- `POST /api/integrations/brevo/lists`
  - Body JSON: `{ "name": "Minha lista", "folderId": 123 }`
- `GET /api/integrations/brevo/lists/{listId}`
- `PUT /api/integrations/brevo/lists/{listId}`
  - Body JSON: `{ "name": "Novo nome" }`
- `DELETE /api/integrations/brevo/lists/{listId}`
- `POST /api/integrations/brevo/lists/{listId}/contacts/add`
  - Body JSON: `{ "emails": ["a@b.com", "c@d.com"] }`
- `POST /api/integrations/brevo/lists/{listId}/contacts/remove`
  - Body JSON: `{ "emails": ["a@b.com"] }`

- `POST /api/integrations/brevo/sync/lists`
  - Query params: `limit` (1–50; default 50)
  - Executa sync paginado de listas e persiste em DB (upsert + soft-delete das listas locais ausentes).
- `POST /api/integrations/brevo/sync/contacts`
  - Query params: `limit` (1–1000; default 500)
  - Executa sync paginado de contatos e persiste em DB (upsert).
- `POST /api/integrations/brevo/sync/all`
  - Query params: `listsLimit` (1–50), `contactsLimit` (1–1000)
  - Executa sync de listas e contatos em sequência.

## Persistência em banco (MySQL/MariaDB)

O sistema mantém um espelho mínimo das entidades Brevo para auditoria, relatórios e reprocessamento.

### Tabelas

- `brevo_lists`
  - Chave: `brevo_list_id`
  - Campos relevantes: `name`, `folder_id`, `raw_json`, `last_synced_at`, `deleted_at`, `created_at`, `updated_at`
- `brevo_contacts`
  - Chave: `email`
  - Campos relevantes: `brevo_contact_id`, `attributes_json`, `list_ids_json`, `email_blacklisted`, `sms_blacklisted`, `raw_json`, `last_synced_at`, `deleted_at`, `created_at`, `updated_at`
- `brevo_sync_runs`
  - Rastreamento de execuções
  - Campos relevantes: `entity` (lists/contacts), `status` (running/success/failed), `processed`, `errors`, `upstream_status`, `message`, `duration_ms`, `meta_json`

### Criação/upgrade automático

A criação do schema é feita automaticamente pela classe:
- `app/Services/Integrations/Brevo/BrevoPersistenceRepository.php` (`ensureSchema()`)

Em produção, recomenda-se validar a criação das tabelas no deploy (ver `docs/deploy.md`).

## Exemplos (cURL)

### Health
```bash
curl -sS -H "Accept: application/json" \
  http://localhost/api/integrations/brevo/health
```

### Criar contato
```bash
curl -sS -X POST -H "Content-Type: application/json" \
  -d '{"email":"teste@example.com","attributes":{"FIRSTNAME":"Teste"}}' \
  http://localhost/api/integrations/brevo/contacts
```

### Buscar contato
```bash
curl -sS http://localhost/api/integrations/brevo/contacts/teste%40example.com
```

### Criar lista
```bash
curl -sS -X POST -H "Content-Type: application/json" \
  -d '{"name":"Minha lista"}' \
  http://localhost/api/integrations/brevo/lists
```

### Adicionar contato em lista
```bash
curl -sS -X POST -H "Content-Type: application/json" \
  -d '{"emails":["teste@example.com"]}' \
  http://localhost/api/integrations/brevo/lists/123/contacts/add
```

## Testes
- Unit:
  - `tests/Unit/Integrations/Brevo/BrevoClientTest.php`
  - `tests/Unit/Integrations/Brevo/BrevoContactsServiceTest.php`
  - `tests/Unit/Integrations/Brevo/BrevoListsServiceTest.php`
- Integração real (condicional):
  - `tests/Integration/BrevoIntegrationTest.php`
  - `tests/Integration/BrevoListsIntegrationTest.php`
  - Requer `BREVO_API_KEY` (e `BREVO_TEST_EMAIL` para CRUD de contatos) em `.env.testing` (ou env do runner)
- Persistência (DB real):
  - `tests/Integration/BrevoPersistenceRepositoryTest.php`
