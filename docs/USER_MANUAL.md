# 📖 Manual do Usuário - Mercado Livre Manager

Guia completo para uso do sistema de gestão multi-contas do Mercado Livre.

---

## 🚀 Primeiros Passos

### 1. Acessar o Sistema

Abra seu navegador e acesse:
```
http://localhost/eskill/public/dashboard
```

### 2. Vincular Primeira Conta

1. Clique em **"Vincular Conta"** no menu
2. Você será redirecionado para o Mercado Livre
3. Faça login e autorize o acesso
4. Você será redirecionado de volta ao sistema
5. Sua conta estará vinculada automaticamente

---

## 📊 Dashboard

### Visão Geral

O dashboard mostra:
- **Contas Ativas:** Total de contas ML vinculadas
- **Pedidos (30 dias):** Total de pedidos recebidos
- **Receita Total:** Soma de todos os pedidos
- **Tokens Expirando:** Contas com tokens próximos de expirar

### Gráficos

- **Pedidos por Status:** Gráfico de rosca mostrando distribuição
- **Pedidos Recentes:** Lista dos 5 pedidos mais recentes

### Notificações

Clique no **ícone de sino** no menu para ver:
- Alertas não lidos
- Novos pedidos
- Tokens expirando
- Outras notificações importantes

---

## 🗂️ Navegador de Categorias

### Acessar

Menu → **Categorias**

### Funcionalidades

1. **Navegação Hierárquica**
   - Expanda/colapse categorias clicando nas setas
   - Navegue pela árvore de categorias

2. **Busca**
   - Digite no campo de busca para encontrar rapidamente
   - Resultados aparecem em tempo real

3. **Detalhes da Categoria**
   - Clique em uma categoria para ver:
     - ID e nome
     - Total de itens
     - Subcategorias
     - Marcas disponíveis
   - Botão para analisar diretamente

---

## 🔍 Análise de Anúncios

### Acessar

Menu → **Análise**

### Como Usar

1. **Selecionar Categoria**
   - Escolha no dropdown ou busque por nome
   - Categorias são carregadas automaticamente

2. **Selecionar Marca**
   - Após selecionar categoria, as marcas são carregadas
   - Escolha a marca desejada

3. **Configurar Filtros (Opcional)**
   - **Condição:** Novo, Usado ou Todos
   - **Preço Mínimo/Máximo:** Defina faixa de preço
   - **Frete Grátis:** Filtrar apenas com frete grátis
   - **Tipo de Anúncio:** Catálogo, Comum ou Todos

4. **Analisar**
   - Clique em **"Analisar"**
   - Aguarde o processamento
   - Resultados aparecem abaixo

### Resultados

A análise mostra:
- **Total de Anúncios:** Quantidade total encontrada
- **Catálogo vs Comum:** Distribuição por tipo
- **Estatísticas de Preço:** Mínimo, máximo e média
- **Gráfico:** Visualização da distribuição
- **Exportar:** Botão para baixar dados (CSV/JSON)

---

## 📦 Gestão de Pedidos

### Acessar

Menu → **Pedidos**

### Funcionalidades

1. **Sincronizar Pedidos**
   - Clique em **"Sincronizar Pedidos"**
   - Sistema busca pedidos de todas as contas ativas
   - Pedidos são salvos no banco de dados

2. **Filtros**
   - **Status:** Filtrar por status do pedido
   - **Data Inicial/Final:** Período desejado
   - **Limite:** Quantidade de resultados

3. **Visualizar Pedido**
   - Clique em **"Ver"** para ver detalhes completos
   - Modal mostra todas as informações do pedido

### Status de Pedidos

- `paid` - Pago
- `confirmed` - Confirmado
- `ready_to_ship` - Pronto para Enviar
- `shipped` - Enviado
- `delivered` - Entregue
- `cancelled` - Cancelado

---

## ⚙️ Configurações

### E-mail

Para receber notificações por e-mail, configure no `.env`:

```env
EMAIL_ENABLED=true
EMAIL_FROM=seu-email@dominio.com
EMAIL_REPLY_TO=seu-email@dominio.com
```

### Cache

Configure cache Redis (opcional) no `.env`:

```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=sua_senha
```

---

## 🔔 Notificações e Alertas

### Tipos de Alertas

1. **Token Expirando**
   - Aviso quando token expira em menos de 7 dias
   - E-mail enviado automaticamente (se configurado)

2. **Novo Pedido**
   - Notificação quando novo pedido é recebido
   - E-mail enviado automaticamente (se configurado)

3. **Novo Concorrente**
   - Alerta quando novo vendedor aparece na categoria/marca

4. **Variação de Preço**
   - Alerta quando há variação significativa de preços

### Gerenciar Alertas

- Clique no **ícone de sino** para ver alertas
- Clique em um alerta para marcar como lido
- Use **"Marcar todas como lidas"** para limpar todos

---

## 📤 Exportação de Dados

### Exportar Análise

1. Realize uma análise (veja seção Análise)
2. Clique em **"Exportar"**
3. Escolha formato:
   - **CSV** - Para Excel/Planilhas
   - **JSON** - Para desenvolvimento/análise

### Dados Exportados

- Lista completa de anúncios
- Tipo (Catálogo/Comum)
- Preços e condições
- Informações de vendedores
- Links dos anúncios

---

## 🔍 Análise de Concorrência

### Via API

Use o endpoint `/api/competitors/analyze` para:
- Ver todos os vendedores de uma marca
- Comparar preços entre vendedores
- Ver ranking por vendas
- Analisar estatísticas de mercado

### Detectar Oportunidades

Use o endpoint `/api/competitors/opportunities` para:
- Encontrar categorias com pouca concorrência
- Identificar produtos sem catálogo
- Descobrir produtos mais vendidos sem seu anúncio

---

## 📈 Histórico de Preços

### Registrar Histórico

Use o endpoint `/api/price-history/record` para salvar preços atuais.

### Consultar Histórico

Use o endpoint `/api/price-history` para ver evolução de preços.

### Analisar Tendência

Use o endpoint `/api/price-history/trend` para:
- Ver se preços estão aumentando/diminuindo
- Calcular variação percentual
- Tomar decisões baseadas em dados

---

## 🛠️ Dicas e Truques

### 1. Cache de Categorias

Categorias são cacheadas por 24 horas. Para forçar atualização, limpe o cache.

### 2. Rate Limiting

O sistema limita a 100 requisições/minuto por IP. Se exceder, aguarde 1 minuto.

### 3. Tokens Automáticos

Tokens são renovados automaticamente quando necessário. Você não precisa fazer nada.

### 4. Webhooks

Configure webhooks no ML Developers para receber notificações em tempo real.

### 5. Múltiplas Contas

Você pode vincular quantas contas ML quiser. Todas aparecem no dashboard.

---

## ❓ Perguntas Frequentes

### Como renovar um token manualmente?

Tokens são renovados automaticamente. Se houver problema, desvincule e vincule novamente.

### Por que não vejo meus pedidos?

1. Verifique se a conta está vinculada
2. Clique em "Sincronizar Pedidos"
3. Verifique os filtros aplicados

### Como exportar dados?

Após realizar uma análise, clique em "Exportar" e escolha o formato.

### Os dados são atualizados em tempo real?

Depende:
- **Pedidos:** Via webhook (tempo real) ou sincronização manual
- **Análises:** Sempre busca dados atuais da API
- **Categorias:** Cache de 24 horas

### Posso usar sem vincular conta?

Sim, mas funcionalidades limitadas:
- ✅ Buscar categorias
- ✅ Analisar anúncios públicos
- ❌ Gerenciar pedidos
- ❌ Receber notificações

---

## 🆘 Suporte

### Logs

Verifique logs em:
- `storage/logs/app.log` - Logs da aplicação
- Banco de dados `webhook_logs` - Logs de webhooks
- Banco de dados `audit_logs` - Logs de auditoria

### Problemas Comuns

1. **Erro 404:** Verifique se a rota está correta
2. **Erro 429:** Aguarde 1 minuto (rate limit)
3. **Erro 403:** Verifique token CSRF em formulários
4. **Token expirado:** Sistema renova automaticamente

---

**Última atualização:** 15 de Dezembro de 2024

