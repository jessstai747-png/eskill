# Account Monitor MVP

Monitoramento de contas do Mercado Livre via eskill OpenClaw API com alertas Telegram.

## Setup

```bash
cd account-monitor
npm install
cp .env.example .env
# Editar .env com suas credenciais
npm run build
```

## Comandos

| Comando | Descricao |
|---------|-----------|
| `npm run collect` | Coletar dados da API |
| `npm run analyze` | Analise + alertas Telegram |
| `npm run digest` | Digest diario |
| `npm run cron` | Modo daemon com agendamento |
| `npx tsx src/index.ts run` | Pipeline completo |
| `npx tsx src/index.ts health` | Health check |

## Cron

```crontab
PROJECT=/home/eskill/htdocs/eskill.com.br/account-monitor
*/10 * * * * cd $PROJECT && npx tsx src/index.ts collect
0 * * * *    cd $PROJECT && npx tsx src/index.ts analyze
0 9 * * *    cd $PROJECT && npx tsx src/index.ts digest
```

## Arquitetura

```
src/
  index.ts          CLI + cron scheduler
  config.ts         Env loading + validacao
  types.ts          TypeScript interfaces
  eskillClient.ts   HTTP wrapper com retry e paginacao
  db.ts             SQLite - migrations e DAL
  collector.ts      Coleta sellers + orders
  analyzer.ts       Analise de saude - score 24/7
  notifier.ts       Alertas Telegram
  digest.ts         Digest diario
```

## Status de Saude

| Status | Condicao |
|--------|----------|
| active | Vendas nos ultimos 7 dias |
| warning | 7-13 dias sem venda |
| alert | 14-29 dias sem venda |
| critical | 30+ dias sem venda |
| new | Conta criada ha menos de 7 dias |
| watch_247 | Score 24/7 >= 80 |

## Score 24/7

Detecta atividade suspeita de operacao 24h:

- Vendas entre 00h-05h em 4+ dias da semana: +40 pontos
- 18+ horas distintas de venda em 3+ dias: +40 pontos
- 50+ pedidos em 7 dias: +20 pontos
- Score >= 80 dispara alerta watch_247

## Env Vars

Ver `.env.example` para todas as variaveis necessarias.
