# Matriz de Endpoints e Domínios — Mercado Livre

## Objetivo

Organizar a integração por domínio funcional, relacionando capacidade de negócio, família de endpoints oficiais, componentes internos e critérios de aceite.

## Legenda

- **Criticidade**
  - `P0` essencial para operação
  - `P1` importante para operação ampliada
  - `P2` evolutivo
- **Modo**
  - `sync` leitura ou mutação síncrona
  - `async` via webhook/job/worker
  - `hybrid` combinação de ambos

## Matriz

| Domínio | Endpoints/Famílias oficiais | Criticidade | Modo | Componentes internos base | Critério de aceite resumido |
| --- | --- | --- | --- | --- | --- |
| OAuth / Conta | autorização, token, refresh, `/users/me` | P0 | sync | `MercadoLivreAuthService`, `AuthController`, `MercadoLivreClient` | conta conecta, refresh funciona, reconexão é guiada |
| Identidade do vendedor | perfil da conta e capacidades | P0 | sync | `MercadoLivreClient`, `MercadoLivreOrchestratorService` | conta ativa validada após OAuth |
| Itens / anúncios | items, descrições, atributos, variações, preço, estoque | P0 | hybrid | `MercadoLivreClient`, serviços ML de items, sync workers | sync incremental consistente por conta |
| Pedidos | orders, detalhes, estados relacionados | P0 | hybrid | `OrderService`, workers de pedidos, webhook service | pedido novo e update refletidos localmente |
| Perguntas | questions e resposta | P0 | hybrid | `QuestionService`, `MercadoLivreWebhookService`, worker de perguntas | pergunta recebida e respondida com auditoria |
| Envios | shipments, tracking, estados logísticos | P0 | hybrid | `ShipmentSyncService`, worker de envios, webhook service | tracking e status consistentes |
| Pagamentos | payments / financeiro associado | P1 | hybrid | `MercadoLivreWebhookService`, serviços financeiros | conciliação por pedido e conta |
| Reclamações | claims | P1 | async | `MercadoLivreWebhookService` | eventos persistidos e rastreáveis |
| Mensagens | messages | P1 | async | `MercadoLivreWebhookService` | mensagens recebidas e classificadas |
| Feedback | feedback | P1 | async | `MercadoLivreWebhookService` | eventos processados com histórico |
| Webhooks | orders, items, questions, shipments, payments, claims, messages, feedback | P0 | async | `MercadoLivreWebhookController`, `WebhookInboxService`, `MercadoLivreWebhookReplayService` | idempotência e replay válidos |
| Pricing / analytics | pricing, métricas, governança | P2 | hybrid | pasta `app/Services/MercadoLivre` | módulos avançados sem impactar core |

## Componentes Internos Relevantes

### Núcleo

- `app/Services/MercadoLivreAuthService.php`
- `app/Services/MercadoLivreClient.php`
- `app/Services/MercadoLivreOrchestratorService.php`

### Inbound / Webhooks

- `app/Controllers/MercadoLivreWebhookController.php`
- `app/Services/WebhookInboxService.php`
- `app/Services/MercadoLivreWebhookService.php`
- `app/Services/MercadoLivreWebhookReplayService.php`

### Operação assíncrona

- `bin/orders-sync-worker.php`
- `bin/questions-sync-worker.php`
- `bin/shipments-sync-worker.php`
- `bin/auto-token-refresh-worker.php`
- `current_crontab`

## Gaps Prioritários Ligados à Matriz

### 1. Runtime assíncrono duplicado

- `auto-token-refresh-worker.php` acumula funções que hoje coexistem com workers dedicados
- ação: consolidar desenho operacional por domínio

### 2. Documentação de webhook desalinhada

- documentação precisa refletir apenas as rotas ativas
- ação: alinhar setup e runbooks com o endpoint realmente exposto

### 3. Testes mais fortes por domínio

- alguns fluxos ainda têm cobertura mais estrutural do que comportamental
- ação: amarrar cada domínio P0 a smoke test real e integração controlada

## Critérios de Aceite Transversais

Todos os domínios devem cumprir:

- autenticação e conta ativa válidas
- logs estruturados por chamada
- classificação de erro consistente
- reconciliação de estado local
- cobertura mínima unitária e de integração
- validação em staging antes de produção
