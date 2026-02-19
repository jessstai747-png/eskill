---
description: "Cria uma view/dashboard PHP completa com template, CSS e funcionalidade"
agent: Implementador
tools:
  - codebase
  - runInTerminal
  - editFiles
  - problems
  - search
---

Crie uma view/dashboard PHP completa seguindo os padrões do projeto eskill.com.br.

## ANTES de criar:
- Leia `project-status.json` para contexto das features
- Leia `claude-progress.txt` para mudanças recentes
- Verifique views existentes em `app/Views/` para seguir padrões

## DEPOIS de criar:
- Atualize `project-status.json` → marque feature relacionada como `"passes": true`
- Atualize `claude-progress.txt` → adicione entrada NO TOPO
- Faça `git commit -m "feat: view [nome da view]"`

## O que criar:

### View (app/Views/)
- Template PHP com HTML semântico
- Seguir padrão das views existentes em `app/Views/dashboard/`
- Seções de loading, error, empty states quando aplicável
- Interface responsiva
- Acessibilidade básica (aria-labels, semântica HTML)

### Controller (se necessário)
- Método no controller para renderizar a view
- Passar dados processados para a view
- Validação de permissões

### Service (se necessário)
- Lógica para buscar e processar dados da view
- Cache com Redis se dados forem pesados

### CSS/JS (public/)
- Estilos em `public/css/` se necessário
- Scripts em `public/js/` se necessário
- Seguir padrões existentes do projeto

### Checklist:
- [ ] Template PHP funcional com dados reais
- [ ] Responsivo (funciona em mobile e desktop)
- [ ] Error handling (mostra mensagens amigáveis)
- [ ] `php -l` em todos os arquivos PHP criados
- [ ] Seguro contra XSS (escape de output com htmlspecialchars)

## Output OBRIGATÓRIO (ao final):

### ✅ View Criada
| Arquivo | Tipo | Descrição |
|---------|------|-----------|

### ✔️ Validação: `php -l` OK | XSS safe | Responsivo
### 🔮 Próximos Passos
1. [testar no browser desktop e mobile]
2. [adicionar carregamento assíncrono de dados se necessário]
3. [criar testes para o controller da view]
