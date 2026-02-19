# Change: Persistência em banco para integração Brevo

## Por quê
Hoje a integração Brevo funciona, mas é essencialmente “stateless” (cache em memória/arquivo/redis) e depende do upstream para consultas e auditoria. Em produção, precisamos de rastreabilidade, relatórios, reprocessamento e redução de chamadas repetidas para a API — com persistência real em banco.

## O que muda
- Adicionar persistência em banco (MySQL/MariaDB via `App\Database`) para **listas** e **contatos** sincronizados com a Brevo.
- Implementar **upsert** e marcação de exclusão (soft-delete) para manter histórico mínimo e permitir reconciliação.
- Adicionar mecanismo de **sync** (pull) com controle de paginação/offset e registro de execuções.
- Padronizar tratamento de erros, idempotência e observabilidade para operações de sync e CRUD.

## Impacto
- Banco: novas tabelas para entidades Brevo e logs de sincronização.
- Código: mudanças em `app/Services/Integrations/Brevo/*Service.php` e possivelmente `app/Controllers/BrevoIntegrationController.php`.
- Operação: necessidade de executar a criação do schema (auto-ensure ou script dedicado) no deploy.

## Observações
- Segredos (`BREVO_API_KEY`) permanecem **apenas via env**; não são persistidos nem logados.
