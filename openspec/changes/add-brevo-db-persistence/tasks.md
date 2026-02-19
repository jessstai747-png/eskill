## 1. Implementação
- [x] 1.1 Definir schema MySQL para persistência Brevo (listas, contatos, sync runs)
- [x] 1.2 Implementar criação/upgrade automático do schema (idempotente)
- [x] 1.3 Persistir resultados de `create/update/delete/get/list` (upsert + soft delete)
- [x] 1.4 Implementar operação de sync (pull) paginada + registro de execução
- [x] 1.5 Expor endpoints internos para status/sync (autenticados)

## 2. Testes
- [x] 2.1 Testes de persistência (DB real) para upsert/soft-delete
- [x] 2.2 Testes de integração real com Brevo (somente com env configurado)

## 3. Documentação
- [x] 3.1 Documentar schema/tabelas e estratégia de sync
- [x] 3.2 Documentar env vars e práticas de deploy/rollback
