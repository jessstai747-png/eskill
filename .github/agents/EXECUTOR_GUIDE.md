# 🚀 Executor Agent — Guia de Uso

> Agent especialista em **Deep Analysis** + **Implementação Real e Completa**

## Como Usar

### ✨ Opção 1: Auto-Seleção (Recomendado)
```bash
# Executor analisa project-status.json e escolhe a próxima feature
"Execute a próxima feature que está marcada como passes: false"
```

O Executor vai:
1. Ler `project-status.json`
2. Analisar features falhando
3. Escolher a com mais dependências prontas
4. Fazer deep dive completo
5. Implementar tudo necessário
6. Atualizar harness e fazer commit

### ✅ Opção 2: Feature Específica
```bash
# Especifique Feature ID ou nome
"Executor, implemente SEO-005: Dashboard Analytics"
"Executor, complete Clone Automation"
```

### 🎯 Opção 3: Área/Categoria
```bash
# Deixe o agent escolher dentro de uma categoria
"Executor, próxima feature de Catalog Clone"
"Executor, qual feature de AI está faltando?"
```

## Fluxo de Execução

```
1. INÍCIO (Smoke Tests)
   ✓ pwd → confirma diretório
   ✓ claude-progress.txt → contexto anterior
   ✓ git log → último commit
   ✓ project-status.json → features status
   ✓ bin/init.sh → validação ambiental

2. DEEP DIVE (Análise)
   ✓ Identifica gaps exatos
   ✓ Lê controllers/services/models
   ✓ Mapeia dependências
   ✓ Entende fluxo de dados

3. IMPLEMENTAÇÃO (Código Real)
   ✓ Migration (BD schema)
   ✓ Model (acesso dados)
   ✓ Service (lógica)
   ✓ Controller (HTTP)
   ✓ Rota (registro)
   ✓ Worker (background jobs, se aplicável)
   ✓ Testes (cobertura com @covers)

4. VALIDAÇÃO (QA)
   ✓ php -l (sintaxe)
   ✓ phpunit (testes)
   ✓ Type hints (completos)
   ✓ Error handling (Monolog)

5. FINALIZAÇÃO (Harness)
   ✓ Atualiza project-status.json
   ✓ Atualiza claude-progress.txt
   ✓ git commit (-m "feat(executor): ...")
```

## Características

### 🔍 Analysis (Diferente de outros agents)
- **Deep dive** em TODA feature relacionada
- Não assume nada — lê arquivo por arquivo
- Identifica gaps exatos (linha x coluna, se preciso)
- Mapeia dependências completas

### 💻 Implementação (100% Real)
- Code real, não mock
- Zero placeholders, zero "TODO"
- Error handling com Monolog
- Type hints completos (sem `mixed`)
- Prepared statements (sem SQL injection)
- Retry logic para APIs externas

### 📊 Status Tracking (Automático)
- Atualiza `project-status.json` (last_tested, passes)
- Atualiza `claude-progress.txt` (topo com detalhes)
- Faz git commits descritivos
- E-mail de notificação ao final (opcional)

## Garantias do Executor

| Aspecto | Garantia |
|---------|----------|
| Código | 100% funcional, testado, tipado |
| Cobertura | Todos os cenários primários da feature |
| Performance | Otimizado, cache quando apropriado |
| Segurança | SQL safe, input validated, secrets em .env |
| Documentação | inline comments, PHPDoc completo |
| Compatibilidade | Não quebra features existentes |

## Features Excluídas (Primeira Iteração)

Executor v1 está focado em:
- ✅ CRUD simples (Controllers, Models, Services)
- ✅ Migrations (schema MySQL)
- ✅ Background workers (bin/*.php)
- ✅ PHPUnit tests
- ✅ API integrations (Mercado Livre, AI)
- ✅ Redis cache

---

## 💬 Exemplos de Prompts

### "Implemente do zero"
> "Executor, qual feature está com passes: false? Implemente com deep analysis."

### "Feature específica"
> "Executor, implemente CLONE-003: Automation Scheduler com deep dive"

### "Categoria"
> "Executor, qual feature de Reports está falhando?"

### "Continue anterior"
> "Executor, continua de onde parou ontem"

### "Analize e proponha"
> "Executor, faça deep analysis no que está faltando em Pricing"

---

## 📋 Checklist para Você (usuário)

Antes de invocar Executor:
- [ ] Leia `PROJECT_STATUS.md` — entenda contexto
- [ ] `git status` — verifique estado do repo
- [ ] `.env` — check API keys estão setadas
- [ ] `bin/init.sh` — smoke test passou
- [ ] Backup de dados sensíveis (opcional)

## 🔗 Mudança para Outros Agents

Se durante a execução descobrir necessidade de:
- **Code review**: `@Revisor code review`
- **Debug**: `@Debugger encontrei erro em X`
- **Arquitetura**: `@Arquiteto preciso replanejar`
- **ML API**: `@MercadoLivre integração de webhook`

---

## FAQ

### "Posso interromper o Executor?"
Sim, a qualquer momento. Ao retomar, vai ler `claude-progress.txt` e continuar de onde parou.

### "E se der erro durante implementação?"
Executor trata erros em tempo real. Se não conseguir resolver, vai avisar explicitamente e pedir input.

### "Quanto tempo leva?"
Depende da complexidade da feature:
- CRUD simples: 15-30 min
- Com API externa: 30-60 min
- Com workers + cache: 60+ min

### "Sempre atualiza harness?"
SIM. Ao final de TODA sessão, atualiza `project-status.json` e `claude-progress.txt`.

### "Pode quebrar features existentes?"
Não. Executor roda testes completos antes de marcar como "passes: true".

---

**Invoque agora:**
```
Executor, que feature devo focar?
```
