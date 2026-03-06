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

### .vscode/

Workspace pronto para autonomia: `settings.json`, `extensions.json`, `mcp.json` e documentação de tokens MCP.

### .devcontainer/

Ambiente isolado para autonomia máxima com Dev Container e bootstrap pós-criação.

### AGENTS.md

Arquivo universal de instruções reconhecido por múltiplos agents (Copilot, Claude Code, Cline, Cursor). Define ambiente, regras de código, e proibições.

### .github/copilot-instructions.md

Instruções globais específicas do Copilot. Define stack, padrões de código, naming, estrutura de pastas, e contexto de negócio.

### .github/agents/

| Agent             | Comando                   | Função                                        |
| ----------------- | ------------------------- | --------------------------------------------- |
| **Executor**      | `/agents` → Executor      | Executa features de forma incremental         |
| **Implementador** | `/agents` → Implementador | Implementa features completas com código real |
| **Revisor**       | `/agents` → Revisor       | Revisa código sem modificar                   |
| **Arquiteto**     | `/agents` → Arquiteto     | Planeja antes de implementar                  |
| **Debugger**      | `/agents` → Debugger      | Diagnostica causa raiz antes de corrigir      |
| **MercadoLivre**  | `/agents` → MercadoLivre  | Especialista em API e SEO do ML               |
| **Jesse Agent**   | `/agents` → Jesse Agent   | Persona personalizada para o workflow local   |

### .github/instructions/

| Arquivo          | Aplica a                                  | Função                            |
| ---------------- | ----------------------------------------- | --------------------------------- |
| react-components | `Views/**/*.php`                          | Regras para componentes/templates |
| services-api     | `Services/**/*.php, Controllers/**/*.php` | Regras para services e APIs       |
| tests            | `*Test.php, tests/**/*.php`               | Regras para testes                |
| prisma-database  | `Database/**/*.php, Models/**/*.php`      | Regras para banco/modelos         |
| codacy           | `**`                                      | Regras de análise local           |

### .github/prompts/

| Comando                | Função                                    |
| ---------------------- | ----------------------------------------- |
| `/implementar-api`     | Integração completa com API externa       |
| `/criar-crud`          | CRUD completo (DB + API + Service + Hook) |
| `/criar-componente`    | Componente React com testes               |
| `/criar-testes`        | Criação de testes com foco em cobertura   |
| `/refatorar`           | Refatoração segura com testes             |
| `/corrigir-bug`        | Debug com diagnóstico de causa raiz       |
| `/auditar-projeto`     | Auditoria completa do projeto             |
| `/implementar-feature` | Implementação guiada de feature completa  |
| `/otimizar-anuncio-ml` | Otimização de anúncio Mercado Livre       |
| `/seguranca`           | Revisão e endurecimento de segurança      |

---

## 🚀 Como Instalar

### Opção 1: Script automático

```bash
bash setup-copilot.sh /caminho/do/seu/projeto
```

O script agora instala automaticamente:

- `AGENTS.md`
- `.github/`
- `.vscode/settings.json`
- `.vscode/extensions.json`
- `.vscode/mcp.json`
- `.vscode/MCP_CONFIG.md`
- `.devcontainer/devcontainer.json`
- `.devcontainer/post-create.sh`
- `bin/mcp-ml-start.sh`
- `bin/mcp-ml-token.php`

### Opção 2: Manual

1. Copie a pasta `.github/` para a raiz do seu projeto
2. Copie `AGENTS.md` para a raiz do seu projeto
3. Copie `.vscode/settings.json`, `.vscode/extensions.json`, `.vscode/mcp.json` e `.vscode/MCP_CONFIG.md`
4. Copie `.devcontainer/` se quiser o modo isolado com autonomia máxima
5. Se quiser usar o MCP do Mercado Livre, copie também `bin/mcp-ml-start.sh` e `bin/mcp-ml-token.php`

### Settings do VS Code

1. Abra o projeto no VS Code
2. Aceite as extensões recomendadas
3. Se necessário, `Ctrl+Shift+P` → `Reload Window`
4. Para autonomia máxima: `Dev Containers: Reopen in Container`

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

### MCPs e Tokens

Depois do setup, o workspace já vem com `.vscode/mcp.json`. Na primeira utilização, configure os tokens solicitados pelo VS Code ou siga as instruções de `.vscode/MCP_CONFIG.md`.

Se o projeto de destino não usar a integração Mercado Livre, você pode remover a entrada `mercadolibre-mcp-server` do `mcp.json` e ignorar os arquivos `bin/mcp-ml-*`.

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
- Se quiser aplicar globalmente, copie o conteúdo de `.vscode/settings.json` para suas User Settings
- Instruction files são lidos automaticamente pelo Copilot
- AGENTS.md é reconhecido por Copilot, Claude Code, e Cursor
- Prompts aparecem como slash commands no chat
- Custom agents aparecem no dropdown de agents

Criado por Claude para Jess @ AWA Motos — Fevereiro 2026
