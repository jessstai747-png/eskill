---
description: "Implementa uma integração completa com API externa — service PHP, tipos, error handling, retry"
agent: Implementador
tools:
  - codebase
  - runInTerminal
  - editFiles
  - fetch
  - problems
  - search
  - usages
---

Implemente uma integração REAL e COMPLETA com a API especificada usando PHP 8+ e Guzzle.

## ANTES de implementar:
- Leia `claude-progress.txt` e `project-status.json` para contexto
- Rode `bash bin/init.sh` para verificar ambiente
- Trabalhe em UMA integração por vez

## DEPOIS de implementar:
- Atualize `project-status.json` → marque feature como `"passes": true`
- Atualize `claude-progress.txt` → adicione entrada NO TOPO
- Faça `git commit -m "feat: integração com [API]"`

## Checklist obrigatório:

1. **Pesquise a API** — Use #fetch para ler a documentação se tiver URL
2. **Crie o service** — Em `app/Services/` com:
   - Guzzle client dedicado com baseURL e timeout
   - Métodos tipados para cada endpoint (type hints completos)
   - Retry com exponential backoff (3 tentativas, status 429/500/502/503)
   - Tratamento de TODOS os status HTTP relevantes
   - Logging com Monolog em cada chamada e erro
3. **Crie o model (se necessário)** — Em `app/Models/` para persistência
4. **Migration SQL** — Em `app/Database/migrations/` se houver tabela nova
5. **Rode validação** — `php -l arquivo.php` e `php vendor/bin/phpunit`

## Regras ABSOLUTAS:
- ❌ ZERO código mock ou placeholder
- ❌ ZERO `mixed` sem justificativa
- ❌ ZERO secrets hardcoded (use $_ENV ou getenv())
- ✅ `declare(strict_types=1)` em todo arquivo
- ✅ Error handling real com try/catch em cada chamada
- ✅ Type hints completos em todos os parâmetros e retornos
- ✅ Rate limiting quando a API exigir
- ✅ Monolog para logging (nunca echo/var_dump)

## Output OBRIGATÓRIO (ao final):

### ✅ Implementado
| Arquivo | Ação | Descrição |
|---------|------|-----------|

### ✔️ Validação: `php -l` OK | phpunit OK
### 🔮 Próximos Passos
1. [teste com credenciais reais]
2. [criar worker background se necessário]
3. [monitoramento de rate limits]
4. [documentar endpoints integrados]
