# 🤖 VS Code Copilot — Kit de Autonomia Máxima

## O que é isso?

Um kit completo de configuração para transformar o GitHub Copilot Agent no VS Code em um programador autônomo que:
- Não para pra pedir permissão a cada ação
- Conhece seu projeto, stack e regras de código
- Implementa código real (zero mocks/placeholders)
- Corrige erros automaticamente
- Tem personas especializadas para diferentes tarefas

---

## 📦 Conteúdo do Kit

### settings.json
Configuração completa do VS Code com todas as 50+ settings otimizadas para autonomia máxima. Inclui auto-approve de terminal com lista granular de comandos seguros/bloqueados.

### AGENTS.md
Arquivo universal de instruções reconhecido por múltiplos agents (Copilot, Claude Code, Cline, Cursor). Define ambiente, regras de código, e proibições.

### .github/copilot-instructions.md
Instruções globais específicas do Copilot. Define stack, padrões de código, naming, estrutura de pastas, e contexto de negócio.

### .github/agents/ (5 Custom Agents)

| Agent | Comando | Função |
|-------|---------|--------|
| **Implementador** | `/agents` → Implementador | Implementa features completas com código real |
| **Revisor** | `/agents` → Revisor | Revisa código sem modificar |
| **Arquiteto** | `/agents` → Arquiteto | Planeja antes de implementar |
| **Debugger** | `/agents` → Debugger | Diagnostica causa raiz antes de corrigir |
| **MercadoLivre** | `/agents` → MercadoLivre | Especialista em API e SEO do ML |

### .github/instructions/ (4 Instruction Files)

| Arquivo | Aplica a | Função |
|---------|----------|--------|
| react-components | `*.tsx` | Regras para componentes React |
| services-api | `services/**/*.ts` | Regras para services e APIs |
| tests | `*.test.ts, *.spec.ts` | Regras para testes |
| prisma-database | `*.prisma, prisma/**` | Regras para Prisma/DB |

### .github/prompts/ (7 Slash Commands)

| Comando | Função |
|---------|--------|
| `/implementar-api` | Integração completa com API externa |
| `/criar-crud` | CRUD completo (DB + API + Service + Hook) |
| `/criar-componente` | Componente React com testes |
| `/refatorar` | Refatoração segura com testes |
| `/corrigir-bug` | Debug com diagnóstico de causa raiz |
| `/auditar-projeto` | Auditoria completa do projeto |
| `/otimizar-anuncio-ml` | Otimização de anúncio Mercado Livre |

---

## 🚀 Como Instalar

### Opção 1: Script automático
```bash
bash setup-copilot.sh /caminho/do/seu/projeto
```

### Opção 2: Manual
1. Copie a pasta `.github/` para a raiz do seu projeto
2. Copie `AGENTS.md` para a raiz do seu projeto
3. Copie o conteúdo de `settings.json` para suas settings do VS Code

### Settings do VS Code
1. `Ctrl+Shift+P` → "Open User Settings (JSON)"
2. Cole o conteúdo do `settings.json` (merge com suas settings existentes)
3. `Ctrl+Shift+P` → "Reload Window"

---

## 🎯 Como Usar

### Agent Mode básico
1. Abra o Chat do Copilot (`Ctrl+L`)
2. Selecione "Agent" no dropdown de modo
3. Digite seu pedido e deixe o agent trabalhar

### Slash Commands
1. No chat, digite `/` e selecione o comando
2. Exemplo: `/implementar-api` e descreva qual API integrar

### Custom Agents
1. No chat, digite `/agents` ou clique no dropdown de agents
2. Selecione o agent especializado (Implementador, Revisor, etc.)
3. O agent vai seguir as regras específicas da persona

### Dica de Ouro
Para máxima autonomia, use o agent dentro de um **Dev Container**. Isso permite ativar `chat.tools.autoApprove: true` com segurança total, pois tudo roda isolado.

---

## ⚠️ Personalização

O `copilot-instructions.md` vem configurado para a stack React + TypeScript + Node.js. **Personalize** para cada projeto:

- Mude a stack se for diferente (Vue, Angular, Python, etc.)
- Ajuste os comandos (npm → pnpm, yarn, etc.)
- Adicione contexto específico do projeto
- Ajuste a estrutura de pastas

Os agents e prompts são genéricos o suficiente para funcionar em qualquer projeto TypeScript/React, mas personalize conforme necessário.

---

## 📝 Notas

- Settings são para **VS Code Workspace** — aplicam apenas ao projeto atual
- Se quiser aplicar globalmente, coloque em User Settings
- Instruction files são lidos automaticamente pelo Copilot
- AGENTS.md é reconhecido por Copilot, Claude Code, e Cursor
- Prompts aparecem como slash commands no chat
- Custom agents aparecem no dropdown de agents

Criado por Claude para Jess @ AWA Motos — Fevereiro 2026
