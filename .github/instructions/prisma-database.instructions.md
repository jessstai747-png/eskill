---
applyTo: "**/Database/**/*.php,**/Database/**/*.sql,**/Models/**/*.php"
---

# Regras para Banco de Dados MySQL / PDO

## Tabelas
- Nomes em snake_case plural: `items`, `ml_accounts`, `seo_keywords`
- Sempre incluir `id` (INT AUTO_INCREMENT PRIMARY KEY), `created_at`, `updated_at`
- Índices para campos usados em WHERE, JOIN, ORDER BY
- Foreign keys com ON DELETE CASCADE ou SET NULL conforme a lógica

## Padrão de Timestamps
```sql
CREATE TABLE exemplo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- campos da tabela
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Models (app/Models/)
```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;

class ExemploModel
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM exemplo WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
```

## Queries
- SEMPRE usar prepared statements (`:param` ou `?`)
- NUNCA concatenar variáveis em SQL
- Usar `SELECT` apenas com campos necessários (evitar `SELECT *` em produção)
- Paginação com `LIMIT` + `OFFSET` ou cursor-based
- Transações para operações multi-tabela
- NUNCA usar `findAll()` sem filtro ou paginação em tabelas grandes

## Migrations (app/Database/migrations/)
- Nomes descritivos: `2024_01_15_create_tech_sheet_suggestions.sql`
- Verificar migration antes de aplicar
- NUNCA editar migrations já aplicadas em produção
- Rodar: `php bin/apply-migrations.php`

## NUNCA
- SQL sem prepared statements (SQL injection!)
- `SELECT *` em queries frequentes
- Queries dentro de loops (N+1)
- Tabelas sem índices adequados
- Migrations com DROP TABLE sem backup
