# Tech Sheet - Email & Export Features

## 📋 Resumo

Implementação de 3 novas features essenciais para o sistema de Ficha Técnica:

1. **Email Service** - Relatórios automáticos por email
2. **Export/Import Service** - Backup e migração de sugestões  
3. **CLI Daily Report** - Worker para envio agendado

---

## 🎯 Features Implementadas

### 1. Email Service (TechSheetEmailService)

**Arquivo:** `app/Services/TechSheetEmailService.php` (395 linhas)

**Funcionalidades:**
- ✅ Envio de relatórios diários formatados (HTML + texto)
- ✅ Alertas críticos imediatos
- ✅ Subject inteligente com emoji de prioridade
- ✅ Design responsivo com CSS inline
- ✅ Estatísticas visuais (completude, alertas, ações)
- ✅ Integração com PHPMailer
- ✅ Configuração via `config/app.php`

**Métodos Principais:**
```php
sendDailyReport(int $accountId, string $email, string $name): bool
sendCriticalAlert(int $accountId, array $recipients, array $alertData): bool
```

**Configuração:**
```php
'email' => [
    'enabled' => true,
    'from' => 'noreply@eskill.com.br',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'your@email.com',
    'smtp_pass' => 'password',
    'smtp_secure' => 'tls',
]
```

**API Endpoint:**
- `POST /api/seo/technical-sheet/send-report` - Envia relatório sob demanda

---

### 2. Export/Import Service (TechSheetExportService)

**Arquivo:** `app/Services/TechSheetExportService.php` (370 linhas)

**Funcionalidades:**
- ✅ Export para CSV (compatível com Excel)
- ✅ Export para JSON (estruturado, versionado)
- ✅ Import de CSV com validação
- ✅ Import de JSON com overwrite opcional
- ✅ Export de templates de categoria
- ✅ Filtros avançados (status, source, confidence, category)
- ✅ Limite configurável (padrão: 10.000)

**Métodos Principais:**
```php
exportToCSV(array $options): string
exportToJSON(array $options): string
importFromCSV(string $content, array $options): array
importFromJSON(string $content, array $options): array
exportCategoryTemplate(string $categoryId): string
```

**Formato CSV:**
```csv
item_id,title,category_id,attribute_id,attribute_name,suggested_value,source,confidence,status,created_at
MLB123,Produto XYZ,MLB1234,COLOR,Cor,Azul,title,85,pending,2026-01-01 10:00:00
```

**Formato JSON:**
```json
{
  "version": "1.0",
  "exported_at": "2026-01-01 12:00:00",
  "account_id": 123,
  "total": 150,
  "suggestions": [...]
}
```

**API Endpoints:**
- `GET /api/seo/technical-sheet/export?format=csv&status=pending` - Exportar
- `POST /api/seo/technical-sheet/import` - Importar
- `GET /api/seo/technical-sheet/export/template/{categoryId}` - Template

---

### 3. CLI Daily Report Worker

**Arquivo:** `bin/tech-sheet-daily-report.php` (118 linhas)

**Funcionalidades:**
- ✅ Envio automático de relatórios
- ✅ Preview antes de enviar
- ✅ Modo dry-run para testes
- ✅ Formatação colorida no terminal
- ✅ Tratamento de erros robusto

**Uso:**
```bash
# Simular
php bin/tech-sheet-daily-report.php \
  --account=123 \
  --email=user@example.com \
  --dry-run

# Enviar
php bin/tech-sheet-daily-report.php \
  --account=123 \
  --email=user@example.com \
  --name="João Silva"
```

**Output:**
```
╔══════════════════════════════════════════════════════════╗
║       Tech Sheet Daily Report Sender                    ║
║       Envio Automático de Relatórios                     ║
╚══════════════════════════════════════════════════════════╝

📊 Preview do Relatório:
   • Data: 2026-01-01
   • Total de itens: 1,234
   • Completude média: 76.3%
   • Prioridade: MEDIUM
   • Alertas críticos: 5
   • Missing required: 12

✅ Relatório enviado com sucesso!
```

**Cron Job:**
```cron
# Enviar diariamente às 08:00
0 8 * * * cd /var/www && php bin/tech-sheet-daily-report.php --account=123 --email=admin@example.com
```

---

## 📂 Arquivos Criados/Modificados

### Novos Arquivos (4)
1. `app/Services/TechSheetEmailService.php` - 395 linhas
2. `app/Services/TechSheetExportService.php` - 370 linhas  
3. `bin/tech-sheet-daily-report.php` - 118 linhas
4. `tests/Unit/Services/TechSheetExportServiceTest.php` - 103 linhas

### Arquivos Modificados (2)
1. `app/Controllers/TechnicalSheetController.php`
   - Adicionados 5 novos endpoints
   
2. `app/Routes/api.php`
   - 4 novas rotas

---

## 🔌 Novos Endpoints API

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/seo/technical-sheet/export` | Exporta sugestões (CSV/JSON) |
| POST | `/api/seo/technical-sheet/import` | Importa sugestões |
| POST | `/api/seo/technical-sheet/send-report` | Envia relatório por email |
| GET | `/api/seo/technical-sheet/export/template/{categoryId}` | Template de categoria |

---

## 💡 Casos de Uso

### Backup Semanal
```bash
# Exportar todas as sugestões
curl "https://eskill.com.br/api/seo/technical-sheet/export?format=json" \
  -o backup-$(date +%Y%m%d).json
```

### Migração entre Contas
```bash
# Exportar de conta A
curl "https://eskill.com.br/api/seo/technical-sheet/export?format=json" > export.json

# Importar em conta B (após trocar de conta)
curl -X POST "https://eskill.com.br/api/seo/technical-sheet/import" \
  -H "Content-Type: application/json" \
  -d @export.json
```

### Relatório sob Demanda
```bash
curl -X POST "https://eskill.com.br/api/seo/technical-sheet/send-report" \
  -H "Content-Type: application/json" \
  -d '{"email": "gerente@empresa.com", "name": "Gerente"}'
```

### Template de Categoria
```bash
# Criar template de categoria mais vendida
curl "https://eskill.com.br/api/seo/technical-sheet/export/template/MLB1234" \
  -o template-eletronicos.json
```

---

## ✅ Testes

### Cobertura
- `TechSheetExportServiceTest.php` - 7 testes
- Validação de formatos CSV/JSON
- Import/Export round-trip
- Templates de categoria

**Executar:**
```bash
php vendor/bin/phpunit tests/Unit/Services/TechSheetExportServiceTest.php
```

---

## 🚀 Como Usar

### 1. Configurar Email

Editar `config/app.php`:
```php
'email' => [
    'enabled' => true,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_user' => 'seu@email.com',
    'smtp_pass' => 'senha',
]
```

### 2. Testar Envio

```bash
php bin/tech-sheet-daily-report.php \
  --account=123 \
  --email=teste@example.com \
  --dry-run
```

### 3. Agendar Envio Diário

```bash
crontab -e

# Adicionar:
0 8 * * * cd /var/www && php bin/tech-sheet-daily-report.php --account=123 --email=admin@example.com >> /var/log/tech-sheet-reports.log 2>&1
```

### 4. Exportar Dados

Via navegador:
```
https://eskill.com.br/api/seo/technical-sheet/export?format=csv&status=pending
```

Via CLI:
```bash
curl "https://eskill.com.br/api/seo/technical-sheet/export?format=json&limit=1000" > backup.json
```

---

## 📊 Estatísticas

**Novos Arquivos:** 4 (986 linhas totais)  
**Endpoints API:** 4 novos  
**CLI Workers:** 1 novo  
**Testes:** 7 (100% passing)

---

## 🎯 Próximos Passos

1. **Slack/Telegram Integration** - Alertas em tempo real
2. **Scheduled Reports** - Relatórios semanais/mensais
3. **Report Templates** - Customização de relatórios
4. **Bulk Operations** - Import/export em massa via UI
5. **Email Analytics** - Taxa de abertura, cliques

---

## 📝 Notas Técnicas

### PHPMailer
- Requer `composer require phpmailer/phpmailer`
- Suporta SMTP, sendmail, mail()
- HTML + texto alternativo

### CSV Encoding
- UTF-8 BOM para Excel
- Escape de vírgulas e aspas
- Compatible com Google Sheets

### JSON Versioning
- Versão 1.0 atual
- Retrocompatibilidade garantida
- Schema validation

---

**Data:** 2026-01-01  
**Versão:** 1.2.0  
**Status:** ✅ Implementado e Testado
