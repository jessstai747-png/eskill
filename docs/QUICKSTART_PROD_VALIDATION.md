# ⚡ Quick Start - Validação de Produção

## 🎯 Objetivo

Validar o fluxo completo de login e rotas do dashboard em **https://eskill.com.br**

## 📦 Instalação (Uma Vez)

```bash
# Instalar dependências Node.js
npm install

# Instalar browsers do Playwright
npx playwright install chromium --with-deps

# Para o script Python (opcional)
pip3 install requests beautifulsoup4
```

## 🚀 Execução Rápida

### Opção 1: Validação Completa com Playwright (RECOMENDADO)

```bash
# Tornar script executável
chmod +x run-prod-validation.sh

# Executar com suas credenciais
./run-prod-validation.sh seu@email.com suasenha
```

**Output:** Screenshots em `storage/playwright-screenshots/`

### Opção 2: Validação Rápida com curl (30 segundos)

```bash
chmod +x quick-prod-validation.sh
./quick-prod-validation.sh seu@email.com suasenha
```

### Opção 3: Python

```bash
python3 prod-validation.py seu@email.com suasenha
```

### Opção 4: Playwright Direto

```bash
PROD_EMAIL=seu@email.com PROD_PASSWORD=suasenha \
npx playwright test tests/e2e/production-validation.spec.ts --reporter=list
```

## 📊 O Que Será Testado

✅ Página de login (campos, CSRF, cookies)
✅ Fluxo completo de autenticação
✅ 12 rotas do dashboard
✅ Erros de console JavaScript
✅ Falhas de network (XHR/Fetch)
✅ Screenshots de todas as páginas

## 📸 Relatório

Após execução:

```bash
# Ver screenshots
ls -lh storage/playwright-screenshots/

# Ver relatório HTML (Playwright)
npx playwright show-report
```

## 🐛 Troubleshooting

### Erro: "Running as root without --no-sandbox"

✅ Já corrigido no `playwright.config.ts`

### Erro: "Cannot find module @types/node"

```bash
npm install
```

### Erro: POLICY_DENIED

Execute via SSH direto no servidor

## 📚 Documentação Completa

- **[PRODUCTION_VALIDATION_REPORT.md](./PRODUCTION_VALIDATION_REPORT.md)** - Relatório completo
- **[PRODUCTION_VALIDATION_GUIDE.md](./PRODUCTION_VALIDATION_GUIDE.md)** - Guia detalhado

## ⚠️ Importante

- ✅ Testes são READ-ONLY (sem ações destrutivas)
- ⚠️ Rodando contra PRODUÇÃO real
- 🔒 Não commite credenciais

## 💡 Próximos Passos Após Execução

1. Revisar screenshots em `storage/playwright-screenshots/`
2. Analisar erros reportados no console
3. Mapear bugs para código-fonte
4. Implementar correções localmente
5. Testar com PHPUnit
6. Revalidar em produção após deploy
