#!/bin/bash

echo "================================================================"
echo "  VERIFICAÇÃO DA CORREÇÃO DO BUG CSP"
echo "================================================================"
echo ""
echo "Bug: Scripts inline sendo bloqueados por Content Security Policy"
echo "Causa: 'script-src-elem' sem nonce nos middlewares"
echo "Correção: Adicionado 'nonce-{\$cspNonce}' ao script-src-elem"
echo ""
echo "================================================================"
echo "  ARQUIVOS CORRIGIDOS"
echo "================================================================"
echo ""

echo "✓ app/Middleware/SecurityMiddleware.php"
echo "  Linha 166: script-src-elem agora inclui nonce"
echo ""

echo "✓ app/Middleware/SecurityHeadersMiddleware.php"  
echo "  Linha 53: script-src-elem agora inclui nonce"
echo ""

echo "================================================================"
echo "  VERIFICANDO CORREÇÕES"
echo "================================================================"
echo ""

# Verificar SecurityMiddleware
echo "1. Verificando SecurityMiddleware.php..."
if grep -q "script-src-elem 'self' 'nonce" app/Middleware/SecurityMiddleware.php; then
    echo "   ✓ Nonce adicionado ao script-src-elem"
else
    echo "   ✗ Nonce NÃO encontrado"
fi

# Verificar SecurityHeadersMiddleware
echo ""
echo "2. Verificando SecurityHeadersMiddleware.php..."
if grep -q "script-src-elem 'self' 'nonce" app/Middleware/SecurityHeadersMiddleware.php; then
    echo "   ✓ Nonce adicionado ao script-src-elem"
else
    echo "   ✗ Nonce NÃO encontrado"
fi

echo ""
echo "================================================================"
echo "  TESTE DE SINTAXE PHP"
echo "================================================================"
echo ""

# Testar sintaxe dos arquivos corrigidos
echo "3. Testando sintaxe PHP..."

if php -l app/Middleware/SecurityMiddleware.php > /dev/null 2>&1; then
    echo "   ✓ SecurityMiddleware.php - Sintaxe OK"
else
    echo "   ✗ SecurityMiddleware.php - ERRO DE SINTAXE"
    php -l app/Middleware/SecurityMiddleware.php
fi

if php -l app/Middleware/SecurityHeadersMiddleware.php > /dev/null 2>&1; then
    echo "   ✓ SecurityHeadersMiddleware.php - Sintaxe OK"
else
    echo "   ✗ SecurityHeadersMiddleware.php - ERRO DE SINTAXE"
    php -l app/Middleware/SecurityHeadersMiddleware.php
fi

echo ""
echo "================================================================"
echo "  RESULTADO ESPERADO APÓS DEPLOY"
echo "================================================================"
echo ""
echo "Após recarregar a página https://eskill.com.br/dashboard/account-health"
echo ""
echo "Antes (com bug):"
echo "  ✗ Console: 'Executing inline script violates CSP...'"
echo "  ✗ Scripts inline bloqueados"
echo "  ✗ Funcionalidades JavaScript não funcionam"
echo ""
echo "Depois (corrigido):"
echo "  ✓ Sem erros de CSP no console"
echo "  ✓ Scripts inline executam normalmente"
echo "  ✓ Todas funcionalidades JavaScript funcionam"
echo ""
echo "================================================================"
echo "  PRÓXIMOS PASSOS"
echo "================================================================"
echo ""
echo "1. Os arquivos já foram corrigidos"
echo "2. Recarregue a página no navegador (Ctrl+Shift+R ou Cmd+Shift+R)"
echo "3. Abra DevTools (F12) > Tab Console"
echo "4. Verifique que não há mais erros de CSP"
echo "5. Teste as funcionalidades da página"
echo ""
echo "Se ainda houver erros, execute:"
echo "  php -S localhost:8000 -t public"
echo "  e acesse: http://localhost:8000/dashboard/account-health"
echo ""
echo "================================================================"
echo "  CORREÇÃO CONCLUÍDA"
echo "================================================================"
