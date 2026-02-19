# Change: Integração completa com API Brevo (Marketing)

## Por quê
O sistema atual não possui uma integração robusta e reutilizável com uma API de marketing digital com operações CRUD, cache, retentativas, observabilidade e testes automatizados. Isso limita integrações com CRM/Email/SMS e automações.

## O que muda
- Adicionar integração com a API Brevo (https://api.brevo.com/v3) para gerenciamento de contatos (CRUD) e verificação de conectividade.
- Padronizar: autenticação via API Key, client HTTP com parsing JSON/XML, mapeamento de erros HTTP, retentativas e timeouts.
- Implementar cache para leituras (GET/listagem) e invalidação em operações de escrita (POST/PUT/DELETE).
- Expor endpoints internos (protegidos) para consumir a integração de forma segura.
- Adicionar testes unitários (com mocks HTTP) e testes de integração condicionais (somente quando variáveis de ambiente de teste existirem).
- Adicionar monitoramento de status da integração (health/ping + logs).
- Documentar endpoints e fluxos principais.

## Impacto
- Código afetado: novos Services/Controllers/Routes para integração Brevo; adição de docs e testes.
- Segurança: armazenamento e uso de `BREVO_API_KEY` via `.env`; sem logar segredos; validação de entrada/saída.
- Performance: cache com TTL configurável e retries com backoff para falhas transitórias (429/5xx).

