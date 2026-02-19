---
applyTo: "**/Views/**/*.php"
---

# Regras para Views PHP (Templates)

## Estrutura da View
```php
<?php
// 1. Variáveis disponíveis (documentadas no topo)
// 2. Includes de header/layout
// 3. Conteúdo da página
// 4. Scripts específicos da página
// 5. Include de footer/layout
```

## Padrões Obrigatórios
- SEMPRE escapar output: `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`
- Usar short echo: `<?= htmlspecialchars($var) ?>` para output
- Separar lógica PHP do HTML — dados devem vir prontos do Controller
- HTML semântico (header, main, nav, section, article, footer)
- Responsivo (funcionar em mobile e desktop)

## Segurança (XSS Prevention)
```php
// ✅ CORRETO — Sempre escapar
<?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>

// ❌ ERRADO — Vulnerável a XSS
<?= $titulo ?>
```

## Padrão de Loading/Error
```php
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php elseif (empty($data)): ?>
    <div class="alert alert-info">Nenhum registro encontrado.</div>
<?php else: ?>
    <!-- conteúdo principal -->
<?php endif; ?>
```

## NUNCA
- Output sem escape (XSS)
- Queries SQL dentro da view
- Lógica de negócio na view (apenas formatação/apresentação)
- JavaScript inline com dados PHP não escapados
- Includes com caminhos dinâmicos não validados
