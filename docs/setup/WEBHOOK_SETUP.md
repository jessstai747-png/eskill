# 🔔 Configuração de Webhooks - Mercado Livre

Este documento explica como configurar webhooks para receber notificações em tempo real do Mercado Livre.

## 📋 O que são Webhooks?

Webhooks são notificações em tempo real enviadas pelo Mercado Livre quando eventos importantes acontecem, como:
- Novo pedido recebido
- Pergunta de cliente
- Mudança no status de um anúncio
- E muito mais...

## 🔧 Configuração

### 1. Obter URL do Webhook

A URL do webhook deve ser acessível publicamente. Exemplo:
```
https://seusite.com.br/eskill/public/webhook/ml
```

**Importante:** Para desenvolvimento local, você pode usar:
- [ngrok](https://ngrok.com/) - Túnel HTTPS gratuito
- [localtunnel](https://localtunnel.github.io/www/) - Alternativa gratuita

### 2. Configurar no Mercado Livre

1. Acesse [developers.mercadolivre.com.br](https://developers.mercadolivre.com.br)
2. Vá em sua aplicação
3. Procure por "Webhooks" ou "Notificações"
4. Adicione a URL do webhook
5. Selecione os tópicos que deseja receber:
   - `orders` - Pedidos
   - `items` - Anúncios
   - `questions` - Perguntas
   - `payments` - Pagamentos

### 3. Verificar Configuração

O sistema já está preparado para receber webhooks em:
```
POST /webhook/ml
GET /webhook/ml
```

## 📨 Tópicos Suportados

### Orders (Pedidos)
Quando um novo pedido é criado ou atualizado:
```json
{
  "topic": "orders",
  "resource": "/orders/123456789",
  "user_id": "123456"
}
```

### Items (Anúncios)
Quando um anúncio é criado ou modificado:
```json
{
  "topic": "items",
  "resource": "/items/MLB123456789"
}
```

### Questions (Perguntas)
Quando uma pergunta é feita:
```json
{
  "topic": "questions",
  "resource": "/questions/123456"
}
```

## 🔍 Logs e Debugging

Todos os webhooks recebidos são logados na tabela `webhook_logs`:

```sql
SELECT * FROM webhook_logs ORDER BY created_at DESC LIMIT 10;
```

## ✅ Verificação

Para testar se o webhook está funcionando:

1. Configure o webhook no ML
2. Realize uma ação que dispare o evento (ex: criar um pedido de teste)
3. Verifique os logs:
   ```sql
   SELECT * FROM webhook_logs WHERE topic = 'orders' ORDER BY created_at DESC;
   ```
4. Verifique se o pedido foi sincronizado:
   ```sql
   SELECT * FROM ml_orders ORDER BY synced_at DESC LIMIT 5;
   ```

## 🛠️ Troubleshooting

### Webhook não está sendo recebido

1. **Verifique se a URL está acessível:**
   ```bash
   curl -X POST https://seusite.com.br/eskill/public/webhook/ml
   ```

2. **Verifique os logs do servidor:**
   - Apache: `/var/log/apache2/error.log`
   - Nginx: `/var/log/nginx/error.log`

3. **Verifique os logs do PHP:**
   - `storage/logs/app.log`

### Erro 404

- Verifique se a rota está configurada em `public/index.php`
- Verifique se o `.htaccess` está configurado corretamente

### Erro 500

- Verifique os logs de erro do PHP
- Verifique se o banco de dados está acessível
- Verifique se as tabelas foram criadas

## 📊 Monitoramento

### Verificar Webhooks Recebidos

```sql
SELECT 
    topic,
    COUNT(*) as total,
    MAX(created_at) as ultimo
FROM webhook_logs
GROUP BY topic
ORDER BY ultimo DESC;
```

### Verificar Notificações Criadas

```sql
SELECT 
    type,
    COUNT(*) as total,
    MAX(created_at) as ultimo
FROM notifications
GROUP BY type
ORDER BY ultimo DESC;
```

## 🔐 Segurança

**Importante:** Em produção, considere:

1. **Validação de origem:** Verificar se o webhook realmente vem do ML
2. **Rate limiting:** Limitar requisições por IP
3. **Autenticação:** Adicionar token de autenticação
4. **HTTPS obrigatório:** Sempre usar HTTPS em produção

## 📚 Referências

- [Documentação Webhooks ML](https://developers.mercadolivre.com.br/pt_br/notificacoes-webhooks)
- [Tópicos Disponíveis](https://developers.mercadolivre.com.br/pt_br/notificacoes-webhooks#topicos-disponiveis)

---

**Última atualização:** 15 de Dezembro de 2024

