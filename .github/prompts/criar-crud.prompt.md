---
description: "Cria um CRUD completo — migration SQL, Model PDO, Service, Controller, rota"
agent: Implementador
tools:
  - codebase
  - runInTerminal
  - editFiles
  - problems
  - search
  - usages
---

Crie um CRUD completo para a entidade especificada usando PHP 8+ com MySQL/PDO.

## ANTES de implementar:
- Leia `claude-progress.txt` e `project-status.json` para contexto
- Verifique se a entidade já existe no codebase
- Rode `bash bin/init.sh` para verificar ambiente

## DEPOIS de implementar:
- Atualize `project-status.json` → adicione feature ou marque `"passes": true`
- Atualize `claude-progress.txt` → adicione entrada NO TOPO
- Faça `git commit -m "feat: CRUD [entidade]"`

## O que criar:

### 1. Banco de Dados
- Migration SQL em `app/Database/migrations/` com CREATE TABLE
- Campos com tipos adequados, índices, e constraints
- Rodar: `php bin/apply-migrations.php`

### 2. Model (app/Models/)
- Classe PHP com namespace `App\Models`
- CRUD via PDO com prepared statements (NUNCA concatenar SQL)
- `create(array $data): int` — retorna ID inserido
- `findAll(array $filters = [], int $page = 1, int $perPage = 20): array`
- `findById(int $id): ?array`
- `update(int $id, array $data): bool`
- `delete(int $id): bool`
- Error handling em cada operação

### 3. Service (app/Services/)
- Classe com lógica de negócio
- Validação de dados antes de salvar
- Interação com Model e outras dependências
- Logging com Monolog

### 4. Controller (app/Controllers/)
- Extends BaseController
- Rotas REST: list, show, create, update, delete
- Validação de input
- Respostas JSON consistentes: `{ data, error, message }`

### 5. Rota (app/Routes/)
- Registrar rotas no sistema de routing existente
- GET, POST, PUT, DELETE por recurso

### 6. Validação
- `php -l` em cada arquivo criado
- `php vendor/bin/phpunit` se houver testes

## Regras:
- `declare(strict_types=1)` em todos os arquivos
- Prepared statements SEMPRE (nunca SQL injection)
- Type hints completos
- Error handling com try/catch e Monolog

## Output OBRIGATÓRIO (ao final):

### ✅ CRUD Criado
| Arquivo | Tipo | Descrição |
|---------|------|-----------|

### ✔️ Validação: `php -l` OK | migration OK | phpunit OK
### 🔮 Próximos Passos
1. [rodar migration: `php bin/apply-migrations.php`]
2. [testar endpoints REST com curl/Postman]
3. [criar testes unitários para o Service]
4. [adicionar paginação e filtros se necessário]
