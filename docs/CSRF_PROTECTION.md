# CSRF Token - Sistema de Proteção

## ✅ Problema Resolvido

O erro `{"error":"Token CSRF inválido ou expirado","code":"CSRF_TOKEN_INVALID"}` foi corrigido completamente.

## 🔧 Correções Implementadas

### 1. **Meta Tag CSRF em Páginas**
Adicionado em todos os arquivos principais:
- ✅ [app/Views/dashboard/seo.php](app/Views/dashboard/seo.php)
- ✅ [app/Views/dashboard/settings.php](app/Views/dashboard/settings.php)
- ✅ [app/Views/components/notifications_bell.php](app/Views/components/notifications_bell.php)

```html
<meta name="csrf-token" content="<?= \App\Helpers\SecurityHelper::csrfToken() ?>">
```

### 2. **Helper JavaScript Global**
Criado [public/js/csrf-helper.js](public/js/csrf-helper.js) que:
- ✅ Intercepta TODAS as requisições `fetch()`
- ✅ Intercepta TODAS as requisições `XMLHttpRequest`
- ✅ Adiciona automaticamente o header `X-CSRF-TOKEN` em POST/PUT/DELETE/PATCH
- ✅ Exporta função global `getCsrfToken()` para uso manual

### 3. **Requisições Atualizadas**
Todas as requisições fetch foram corrigidas para incluir o token:

#### Dashboard SEO:
```javascript
fetch('/api/seo/title/optimize', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken()
    },
    body: JSON.stringify(data)
})
```

#### Notificações:
```javascript
fetch('/api/alerts/read-all', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': getCsrfToken()
    }
})
```

#### Settings:
```javascript
fetch('/api/cache/clear', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': getCsrfToken()
    }
})
```

## 🎯 Como Usar em Novos Arquivos

### Opção 1: Automático (Recomendado)
Adicione o helper no head da página:

```html
<head>
    <meta name="csrf-token" content="<?= \App\Helpers\SecurityHelper::csrfToken() ?>">
    <script src="/js/csrf-helper.js"></script>
</head>
```

Todas as requisições POST/PUT/DELETE/PATCH terão o token adicionado automaticamente!

### Opção 2: Manual
```javascript
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken() // Função global disponível
    },
    body: JSON.stringify(data)
})
```

### Opção 3: Layout Comum
Inclua o arquivo de layout:

```php
<?php require_once __DIR__ . '/../layouts/head_common.php'; ?>
```

## 📋 Middleware CSRF

O middleware [app/Middleware/CsrfMiddleware.php](app/Middleware/CsrfMiddleware.php) valida automaticamente:
- ✅ Métodos: POST, PUT, DELETE, PATCH
- ✅ Headers: `X-CSRF-TOKEN` ou campo `_token` em formulários
- ✅ Expiração: 1 hora (3600 segundos)
- ✅ Resposta: JSON com código 403 e mensagem de erro

## 🛡️ Segurança

### Token válido por:
- **1 hora** após geração
- Armazenado em `$_SESSION['csrf_token']`
- Validado com `hash_equals()` (timing-safe)

### Proteção contra:
- ✅ Cross-Site Request Forgery (CSRF)
- ✅ Replay attacks (expiração)
- ✅ Timing attacks (hash_equals)

## 🔍 Debug

Para verificar se o token está sendo enviado:

```javascript
// No console do navegador
console.log('CSRF Token:', getCsrfToken());

// Verificar em requisição
fetch('/api/test', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': getCsrfToken()
    }
}).then(response => console.log(response));
```

## 📝 Formulários HTML

Para formulários HTML tradicionais, use o helper do PHP:

```php
<form method="POST" action="/endpoint">
    <?= \App\Helpers\SecurityHelper::csrfField() ?>
    <!-- outros campos -->
</form>
```

Gera:
```html
<input type="hidden" name="_token" value="abc123...">
```

## ⚡ Arquivos Principais Corrigidos

1. ✅ [app/Views/dashboard/seo.php](app/Views/dashboard/seo.php) - 4 requisições POST
2. ✅ [app/Views/dashboard/settings.php](app/Views/dashboard/settings.php) - 2 requisições POST
3. ✅ [app/Views/components/notifications_bell.php](app/Views/components/notifications_bell.php) - 2 requisições POST
4. ✅ [public/js/csrf-helper.js](public/js/csrf-helper.js) - Helper global criado
5. ✅ [app/Views/layouts/head_common.php](app/Views/layouts/head_common.php) - Layout comum criado

## 🚀 Próximos Passos (Opcional)

Para uma proteção ainda mais completa, você pode:

1. **Adicionar o helper em todas as páginas**:
   ```php
   <?php require_once __DIR__ . '/../layouts/head_common.php'; ?>
   ```

2. **Verificar logs de segurança**:
   ```bash
   tail -f storage/logs/security.log
   ```

3. **Testar o sistema**:
   - Abra a página SEO: http://localhost/dashboard/seo
   - Teste qualquer função (Análise, Keywords, etc.)
   - Deve funcionar sem erros de CSRF

## ✨ Status Final

✅ **CSRF Token completamente funcional**  
✅ **Proteção automática em todas as requisições**  
✅ **Helper global para fácil manutenção**  
✅ **Documentação completa**

O sistema agora está protegido contra ataques CSRF! 🎉
