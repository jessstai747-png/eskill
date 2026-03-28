# 🧪 Validação de Produção - eskill.com.br

Este documento descreve como executar a validação completa do fluxo de login e rotas do dashboard em produção usando browser automation real.

## 📋 Arquivos Criados

1. **`tests/e2e/production-validation.spec.ts`** - Teste Playwright completo
2. **`run-prod-validation.sh`** - Script para executar os testes
3. **`playwright.config.ts`** - Atualizado para suportar execução como root (--no-sandbox)

## 🚀 Como Executar

### Opção 1: Com credenciais via argumentos

```bash
chmod +x run-prod-validation.sh
./run-prod-validation.sh seu@email.com suasenha
```

### Opção 2: Com variáveis de ambiente

```bash
export PROD_EMAIL=seu@email.com
export PROD_PASSWORD=suasenha
chmod +x run-prod-validation.sh
./run-prod-validation.sh
```

### Opção 3: Executar diretamente com npx

```bash
PROD_EMAIL=seu@email.com PROD_PASSWORD=suasenha npx playwright test tests/e2e/production-validation.spec.ts --reporter=list
```

### Opção 4: Sem credenciais (testes limitados)

Alguns testes, como inspeção da página de login, não requerem autenticação:

```bash
npx playwright test tests/e2e/production-validation.spec.ts --reporter=list
```

Os testes que requerem autenticação serão automaticamente pulados.

## 📊 O Que o Teste Faz

### 1. Inspeção da Página de Login

- ✅ Verifica campos de email e password
- ✅ Verifica token CSRF (input hidden `_token`)
- ✅ Verifica meta tag CSRF (`csrf-token`)
- ✅ Verifica cookie de sessão (PHPSESSID)
- ✅ Captura screenshot da página
- ✅ Coleta erros de console e network

### 2. Login com Credenciais Reais

- ✅ Preenche formulário de login
- ✅ Submit do formulário
- ✅ Verifica redirecionamento para dashboard
- ✅ Captura screenshots antes/depois do submit
- ✅ Detecta mensagens de erro
- ✅ Coleta erros de console e network

### 3. Smoke Test de Todas as Rotas do Dashboard

Testa cada rota com:

- ✅ Status HTTP (deve ser 200-399)
- ✅ Screenshot da página
- ✅ Erros de console (JavaScript)
- ✅ Falhas de network (XHR/Fetch)
- ✅ Verificação de redirecionamento para login (sessão expirada)

**Rotas testadas:**

1. `/dashboard` - Dashboard Principal
2. `/dashboard/accounts` - Contas
3. `/dashboard/analytics` - Analytics
4. `/dashboard/account-health` - Account Health
5. `/dashboard/items` - Items
6. `/dashboard/orders` - Orders
7. `/dashboard/questions` - Questions
8. `/dashboard/messages` - Messages
9. `/dashboard/claims` - Claims
10. `/dashboard/seo-killer` - SEO Killer
11. `/dashboard/financials` - Financials
12. `/dashboard/pricing` - Pricing

### 4. Diagnóstico de Falhas

- ✅ Testa endpoint `/api/auth/login` diretamente
- ✅ Identifica se problema é de CSRF/sessão ou de credenciais

## 📸 Screenshots

Todos os screenshots são salvos em: **`storage/playwright-screenshots/`**

Formato: `{nome-da-rota}_{timestamp}.png`

Exemplos:

- `login-page_2026-03-23T23-57-10-123Z.png`
- `login-before-submit_2026-03-23T23-57-15-456Z.png`
- `route-dashboard-principal_2026-03-23T23-57-20-789Z.png`

## 📋 Formato de Saída

### Console Output

```
🔍 Testando: Dashboard Principal (/dashboard)
📊 Status HTTP: 200
✅ Campos email e password encontrados
🔑 CSRF Token (input hidden): Encontrado
🔑 CSRF Token (meta tag): Encontrado
🍪 Cookie de sessão: PHPSESSID
📸 Screenshot salvo: storage/playwright-screenshots/login-page_2026-03-23...png

📋 Resumo Dashboard Principal:
  • Status: 200
  • Console Errors: 0
  • Network Errors: 0
  • URL Final: https://eskill.com.br/dashboard
```

### Erros Detectados

Se houver problemas, a saída incluirá:

```
❌ Erros de console em SEO Killer:
[CONSOLE ERROR] Uncaught TypeError: Cannot read property 'foo' of undefined
[PAGE ERROR] ReferenceError: $ is not defined

⚠️  Requests com problema em SEO Killer:
[404] GET https://eskill.com.br/api/seo/stats
[FAILED] POST https://eskill.com.br/api/seo/optimize - net::ERR_CONNECTION_REFUSED
```

## 🔧 Relatório HTML

Após executar os testes, você pode visualizar um relatório HTML detalhado:

```bash
npx playwright show-report
```

## ⚠️ IMPORTANTE - Segurança

1. **NÃO COMMITE CREDENCIAIS** - Use variáveis de ambiente
2. **NÃO FAÇA AÇÕES DESTRUTIVAS** - O teste é read-only
3. **USE EM PRODUÇÃO COM CUIDADO** - Testa servidor real

## 🐛 Troubleshooting

### Erro: "Running as root without --no-sandbox"

✅ **RESOLVIDO** - O `playwright.config.ts` foi atualizado para incluir `--no-sandbox`

### Erro: "Credenciais não fornecidas"

Forneça as credenciais via:

- Argumentos: `./run-prod-validation.sh email senha`
- Env vars: `PROD_EMAIL=... PROD_PASSWORD=...`

### Erro: "Failed to launch browser"

Instale as dependências do Playwright:

```bash
npx playwright install --with-deps chromium
```

### Testes são pulados

Se você vir "Requer credenciais" é porque os testes de autenticação precisam de credenciais reais. Execute com `PROD_EMAIL` e `PROD_PASSWORD`.

## 🔍 Próximos Passos Após Execução

1. **Revisar screenshots** em `storage/playwright-screenshots/`
2. **Analisar erros** de console e network reportados
3. **Mapear falhas para código** no repositório
4. **Implementar correções** localmente
5. **Testar com PHPUnit** e Playwright local
6. **Revalidar em produção** após deploy

## 📚 Referências

- Playwright Docs: https://playwright.dev/docs/intro
- Configuração: `playwright.config.ts`
- Teste: `tests/e2e/production-validation.spec.ts`
- Script: `run-prod-validation.sh`
