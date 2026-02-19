# 🔔 Sistema de Notificações em Tempo Real com Áudio

Sistema de notificações push para pedidos e perguntas do Mercado Livre com suporte a áudio personalizado.

## 📋 Funcionalidades

- ✅ **Polling Automático**: Verificação periódica de novas notificações
- ✅ **Notificações Desktop**: Suporte a Web Notifications API
- ✅ **Sons Personalizados**: Sons diferentes para pedidos, perguntas e mensagens
- ✅ **Horário Silencioso**: Configuração de período sem sons
- ✅ **Toast Notifications**: Notificações visuais dentro do app
- ✅ **Configurações Persistentes**: Salvas no banco de dados por usuário

## 🚀 Como Funciona

### 1. Webhook do Mercado Livre
Quando um novo pedido ou pergunta é recebido:
```
ML → Webhook → WebhookController → RealTimeNotificationService
```

### 2. Polling do Frontend
```javascript
// O frontend verifica periodicamente por novas notificações
GET /api/notifications/poll
```

### 3. Processamento de Notificações
```javascript
// Quando uma nova notificação é detectada:
1. Toca som de alerta (se habilitado)
2. Mostra notificação desktop (se habilitado)
3. Exibe toast no app
4. Atualiza badge de contagem
```

## 📁 Arquivos do Sistema

### Backend
- `app/Services/RealTimeNotificationService.php` - Serviço principal
- `app/Controllers/RealTimeNotificationController.php` - API endpoints
- `app/Controllers/WebhookController.php` - Recebe webhooks do ML

### Frontend
- `public/js/realtime-notifications.js` - Sistema de polling e áudio
- `public/sounds/` - Arquivos de áudio
- `app/Views/components/notification_settings.php` - UI de configuração

## 🔧 Endpoints da API

### Polling
```
GET /api/notifications/poll
```
Retorna novas notificações não entregues.

### Notificações Não Lidas
```
GET /api/notifications/realtime/unread
GET /api/notifications/realtime/unread?type=order
```

### Marcar como Lida
```
POST /api/notifications/realtime/{id}/read
POST /api/notifications/realtime/read-all
```

### Configurações
```
GET /api/notifications/realtime/settings
POST /api/notifications/realtime/settings
```
Body:
```json
{
    "sound_enabled": true,
    "sound_volume": 80,
    "sound_order": "order_notification",
    "sound_question": "question_notification",
    "sound_message": "message_notification",
    "desktop_enabled": true,
    "polling_interval": 30,
    "quiet_hours_start": "22:00",
    "quiet_hours_end": "07:00"
}
```

### Testar Som
```
POST /api/notifications/test-sound
Body: {"type": "order"}
```

### Estatísticas
```
GET /api/notifications/realtime/stats
```

## 🎵 Sons Disponíveis

| Nome | Descrição |
|------|-----------|
| `order_notification` | Som padrão de pedido |
| `question_notification` | Som padrão de pergunta |
| `message_notification` | Som padrão de mensagem |
| `cash_register` | Caixa registradora |
| `cha_ching` | Cha-ching (dinheiro) |
| `bell` | Sino |
| `chime` | Campainha |
| `pop` | Pop curto |
| `alert` | Alerta |
| `success` | Sucesso |

### Gerando Sons MP3 (opcional)
Se você tem FFmpeg instalado:
```bash
php scripts/generate_notification_sounds.php
```

Caso contrário, o sistema usa Web Audio API como fallback.

## ⚙️ Configuração

### 1. Configurar Webhooks no Mercado Livre
No portal de desenvolvedores do ML, configure os webhooks:
- **URL**: `https://seusite.com.br/webhook/ml`
- **Tópicos**: `orders`, `questions`

### 2. Tabelas do Banco
As tabelas são criadas automaticamente na primeira execução:
- `realtime_notifications` - Armazena as notificações
- `notification_settings` - Configurações por usuário

### 3. Personalizar Sons
Substitua os arquivos MP3 em `/public/sounds/` por sons personalizados.

## 🖥️ Uso no Frontend

### Inicialização Automática
O sistema inicializa automaticamente quando a página carrega:
```javascript
// Já está configurado no head_common.php
window.enableRealTimeNotifications = true;
```

### Acesso Manual
```javascript
// Acessar instância global
const notifications = window.realTimeNotifications;

// Parar polling
notifications.stopPolling();

// Iniciar polling
notifications.startPolling();

// Testar som
notifications.testSound('order');

// Marcar todas como lidas
notifications.markAllAsRead();
```

### Callbacks Personalizados
```javascript
const notifications = new RealTimeNotifications({
    onOrderNotification: (notification) => {
        console.log('Novo pedido:', notification);
        // Sua lógica personalizada
    },
    onQuestionNotification: (notification) => {
        console.log('Nova pergunta:', notification);
    },
    onCountUpdate: (counts) => {
        console.log('Contadores:', counts);
    }
});
```

## 🔐 Permissões

O sistema solicita permissão para notificações desktop automaticamente.
Se o usuário negar, apenas toasts e sons serão utilizados.

## 📊 Painel de Configurações

Acesse: **Dashboard → Configurações → Notificações**

Ou inclua o componente em qualquer view:
```php
<?php include __DIR__ . '/../components/notification_settings.php'; ?>
```

## 🐛 Troubleshooting

### Sons não tocam
1. Verifique se o volume não está em 0
2. Verifique se "Ativar Sons" está marcado
3. Alguns navegadores bloqueiam autoplay - interaja com a página primeiro
4. Verifique se não está no horário silencioso

### Notificações desktop não aparecem
1. Verifique permissões do navegador
2. Clique em "Permitir" quando solicitado
3. Verifique configurações do navegador em `chrome://settings/content/notifications`

### Polling não funciona
1. Verifique se está autenticado
2. Verifique console para erros
3. Verifique se a API está respondendo: `GET /api/notifications/poll`

## 📝 Changelog

### v1.0.0 (2024-12-22)
- Sistema inicial de notificações
- Suporte a pedidos e perguntas
- Sons personalizáveis
- Horário silencioso
- Notificações desktop
