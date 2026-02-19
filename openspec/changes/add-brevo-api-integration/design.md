# Design: Integração Brevo (Marketing API)

## Arquitetura
- **BrevoClient**: responsável por HTTP, autenticação (API key), timeouts, parsing (JSON/XML), mapeamento de erros, retentativas (RetryService) e telemetria (LoggingService).
- **BrevoContactsService**: encapsula o domínio “contacts” (CRUD), valida entrada/saída e aplica cache/invalidação (CacheService).
- **BrevoIntegrationController**: expõe endpoints internos protegidos por sessão/auth existente do sistema.

## Autenticação
- Header: `api-key: <BREVO_API_KEY>`
- Config: `BREVO_API_KEY` obrigatório; `BREVO_BASE_URL` e `BREVO_TIMEOUT_SECONDS` opcionais.

## Resiliência
- Retentativas para: 429, 500, 502, 503, 504 e erros transitórios de rede.
- Backoff exponencial + jitter via `App\Services\AI\Core\RetryService`.
- Circuit breaker por operação (ex.: `brevo.contacts.get`).

## Cache
- Read-through cache em operações GET (por email/id e listagens) com TTL (ex.: 300–3600s).
- Invalidação após POST/PUT/DELETE para chaves relacionadas.

## Segurança
- Nunca logar `BREVO_API_KEY`.
- Validar e normalizar email/atributos antes de enviar.
- Tratar erros HTTP sem vazar payload sensível para o cliente.

## Testes
- Unit: usar `GuzzleHttp\Handler\MockHandler` para simular respostas e status codes.
- Integration: executar somente quando `BREVO_API_KEY` e `BREVO_TEST_EMAIL` estiverem definidos; garantir cleanup (DELETE).

