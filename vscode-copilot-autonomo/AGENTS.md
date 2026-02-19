# AGENTS.md

> Instruções universais para todos os coding agents (Copilot, Claude Code, Cline, Cursor, etc.)

## Ambiente de Desenvolvimento
- **OS:** Ubuntu / WSL2
- **Node.js:** v20 LTS
- **Package Manager:** npm (verificar se o projeto usa pnpm/yarn antes)
- **Editor:** VS Code via SSH remoto
- **Shell:** bash/zsh
- **Git:** Conventional commits (feat:, fix:, refactor:, etc.)

## Filosofia

### Código Real, Sempre
Este workspace NÃO aceita código placeholder. Toda implementação deve ser funcional e pronta para produção. Se uma integração com API é solicitada, implemente com chamadas reais, tratamento de erro, retry, e tipagem completa.

### Leia Antes de Escrever
Antes de criar ou editar qualquer arquivo:
1. Liste a estrutura do projeto (`ls`, `tree`, `find`)
2. Leia os arquivos relevantes ao que vai modificar
3. Verifique imports, tipos, e dependências existentes
4. Só então comece a implementar

### Valide Após Cada Mudança
Após qualquer edição de código:
1. Rode `tsc --noEmit` para verificar tipos
2. Rode `npm run lint` se disponível
3. Rode `npm test` se houver testes
4. Corrija qualquer erro antes de prosseguir

## Proibições Absolutas
- ❌ `any` no TypeScript
- ❌ Código mock, stub, ou placeholder
- ❌ `console.log` em produção (use um logger)
- ❌ Secrets hardcoded
- ❌ `// TODO: implement` sem implementação real
- ❌ Ignorar erros silenciosamente (`catch {}`)
- ❌ Instalar dependências sem justificativa
- ❌ Alterar `.env`, `.gitignore`, `package.json` sem comunicar
- ❌ Criar READMEs ou documentação não solicitada
- ❌ Refatorar código que não foi pedido para refatorar

## Padrões de Código

### TypeScript / JavaScript
```
- strict: true sempre
- Interfaces para objetos, types para unions
- Funções tipadas com retorno explícito
- async/await preferido sobre .then()
- Error handling com try/catch em todo I/O
- Imports absolutos quando disponíveis (@/)
```

### React
```
- Componentes funcionais + hooks
- Props tipadas com interface
- Custom hooks para lógica compartilhada
- Separação: UI puro vs lógica de negócio
```

### API / Backend
```
- Validação de input em toda rota
- Respostas consistentes: { data, error, message }
- Rate limiting em integrações externas
- Retry com exponential backoff
- Logs estruturados
```

## Estrutura Esperada
```
project/
├── .github/
│   ├── copilot-instructions.md
│   ├── agents/
│   ├── instructions/
│   └── prompts/
├── src/
│   ├── components/
│   ├── pages/ ou app/
│   ├── hooks/
│   ├── services/
│   ├── utils/
│   ├── types/
│   └── lib/
├── tests/
├── AGENTS.md
├── package.json
└── tsconfig.json
```

## Contexto de Negócio
- **AWA Motos** — distribuidora de peças para motos em Araraquara, SP
- **Mercado Livre** — principal canal de vendas, API: api.mercadolibre.com
- **Foco:** Automação de e-commerce, otimização de anúncios, integrações de API
