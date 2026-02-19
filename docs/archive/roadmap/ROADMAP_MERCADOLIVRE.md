# 🛒 Roadmap de Desenvolvimento - Integração Mercado Livre Multi-Contas

> **Projeto:** Sistema de Gestão Multi-Contas Mercado Livre  
> **Versão:** 1.0  
> **Data:** Dezembro 2024  
> **Stack:** PHP 8.x + MySQL + Bootstrap 5 / React (opcional)

---

## 📋 Visão Geral do Projeto

Sistema completo para gerenciar **múltiplas contas** do Mercado Livre, com análise detalhada de:
- Categorias e subcategorias
- Marcas (ex: AWA)
- Anúncios de **Catálogo** vs **Comuns**
- Métricas de vendas e preços
- Monitoramento de concorrência

---

## 🎯 Objetivos

1. Centralizar gestão de múltiplas contas ML
2. Análise detalhada por categoria/marca
3. Diferenciação entre anúncios de catálogo e comuns
4. Dashboard com métricas em tempo real
5. Alertas e notificações automatizadas

---

# 📅 Fases de Desenvolvimento

---

## 🔷 FASE 1: Fundação e Autenticação OAuth2
**Duração estimada:** 1-2 semanas

### 1.1 Estrutura do Projeto
- [x] Criar estrutura de diretórios MVC
- [x] Configurar autoload (Composer)
- [x] Instalar dependências (Guzzle, dotenv, etc.)
- [x] Configurar ambiente (.env)
- [x] Sistema de autenticação de usuários (login/registro)

```
/mercadolivre-manager
├── /app
│   ├── /Controllers
│   ├── /Models
│   ├── /Services
│   └── /Views
├── /config
├── /database
│   └── /migrations
├── /public
│   ├── /css
│   ├── /js
│   └── index.php
├── /storage
│   └── /logs
├── /vendor
├── .env.example
├── composer.json
└── README.md
```

### 1.2 Banco de Dados
- [x] Criar schema do banco de dados
- [x] Tabela `users` (usuários do sistema)
- [x] Tabela `ml_accounts` (contas ML vinculadas)
- [x] Tabela `sync_logs` (logs de sincronização)

```sql
-- Estrutura inicial
CREATE TABLE ml_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    ml_user_id VARCHAR(50) UNIQUE,
    nickname VARCHAR(100),
    email VARCHAR(255),
    site_id VARCHAR(10) DEFAULT 'MLB',
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at DATETIME,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### 1.3 Autenticação OAuth2
- [x] Implementar fluxo de autorização
- [x] Callback para receber código
- [x] Troca de código por tokens
- [x] Sistema de refresh automático de tokens
- [x] Verificação de token (via MercadoLivreAuthService)

### 1.4 Entregáveis Fase 1
- ✅ Sistema de login/registro de usuários completo
- ✅ UserService para gestão de usuários
- ✅ Middleware de autenticação
- ✅ Views de login e registro
- ✅ Vinculação de contas ML (OAuth2)
- ✅ Armazenamento seguro de tokens
- ✅ Refresh automático de tokens expirados

---

## 🔷 FASE 2: Core da API e Serviços Base
**Duração estimada:** 2-3 semanas

### 2.1 Cliente HTTP para API ML
- [x] Classe `MercadoLivreClient` (wrapper da API)
- [x] Tratamento de erros da API
- [x] Suporte a GET, POST, PUT, DELETE
- [x] Rate limiting básico implementado
- [x] Retry automático com backoff exponencial
- [x] Cache de requisições avançado

```php
// Exemplo de estrutura
class MercadoLivreClient {
    public function get($endpoint, $params = []);
    public function post($endpoint, $data = []);
    public function put($endpoint, $data = []);
    public function delete($endpoint);
}
```

### 2.2 Serviços Principais
- [x] `MercadoLivreAuthService` - Autenticação e tokens
- [x] `CategoryService` - Categorias e atributos
- [x] `SearchService` - Buscas e filtros
- [x] `ItemService` - Operações com anúncios

### 2.3 Sistema de Filas (Jobs)
- [x] Configurar sistema de filas (Database)
- [x] Jobs para sincronização em background
- [x] Agendamento de tarefas (CRON)

### 2.4 Entregáveis Fase 2
- ✅ Cliente HTTP robusto para API ML
- ✅ Serviços base funcionais
- ✅ Sistema de cache implementado
- ✅ Logs de requisições e erros

---

## 🔷 FASE 3: Módulo de Categorias e Marcas
**Duração estimada:** 2 semanas

### 3.1 Explorador de Categorias
- [x] Listar todas as categorias do site MLB
- [x] Navegação hierárquica (árvore)
- [x] Cache de categorias (atualização diária)
- [x] Busca de categoria por nome/ID
- [x] Interface visual de navegação

### 3.2 Atributos e Filtros
- [x] Obter atributos de cada categoria
- [x] Listar marcas disponíveis por categoria
- [x] Filtros dinâmicos baseados em atributos
- [x] Validação de atributos obrigatórios

### 3.3 Endpoints Utilizados
```
GET /sites/MLB/categories
GET /categories/{category_id}
GET /categories/{category_id}/attributes
GET /sites/MLB/search?category={id}&BRAND={brand}
```

### 3.4 Entregáveis Fase 3
- ✅ Navegador de categorias visual
- ✅ Filtro por marca funcional
- ✅ Cache de categorias/atributos
- ✅ Interface para selecionar categoria + marca

---

## 🔷 FASE 4: Análise de Anúncios (Catálogo vs Comum)
**Duração estimada:** 2-3 semanas

### 4.1 Diferenciação de Tipos de Anúncio
- [x] Identificar anúncios de catálogo (`catalog_product_id`)
- [x] Identificar anúncios comuns
- [x] Contagem por tipo
- [x] Relatório comparativo

### 4.2 Análise Detalhada
- [x] Métricas de preço (min, max, média)
- [x] Análise de condição (novo/usado)
- [x] Análise de frete (grátis/pago)
- [x] Vendedores por marca/categoria

### 4.3 Motor de Busca Avançado
```php
// Filtros disponíveis
$filters = [
    'category' => 'MLB431935',
    'BRAND' => 'AWA',
    'condition' => 'new',
    'shipping' => 'free',
    'price_min' => 100,
    'price_max' => 500,
    'catalog_only' => true  // ou false
];
```

### 4.4 Entregáveis Fase 4
- [x] Relatório Catálogo vs Comum
- [x] Análise de preços por marca
- [x] Filtros avançados de busca (condição, preço, frete, tipo)
- [x] Exportação de dados (CSV/JSON)

---

## 🔷 FASE 5: Dashboard e Visualizações
**Duração estimada:** 2 semanas

### 5.1 Dashboard Principal
- [x] Cards com métricas principais
- [x] Gráficos de distribuição (Chart.js)
- [x] Tabelas com dados detalhados
- [x] Lista de pedidos recentes

### 5.2 Visualizações
- [x] Gráfico de rosca: Pedidos por status
- [x] Cards com métricas consolidadas
- [x] Lista de pedidos recentes
- [x] Gráfico de barras: Anúncios por marca
- [x] Gráfico de linha: Evolução de preços

### 5.3 Relatórios
- [x] Relatório por conta
- [x] Relatório por categoria
- [x] Relatório por marca
- [x] Relatório consolidado (todas as contas)

### 5.4 Entregáveis Fase 5
- ✅ Dashboard responsivo e interativo
- ✅ Gráficos dinâmicos
- ✅ Relatórios exportáveis (PDF/CSV/JSON)
- ✅ Filtros em tempo real

---

## 🔷 FASE 6: Gestão de Pedidos Multi-Conta
**Duração estimada:** 2-3 semanas

### 6.1 Sincronização de Pedidos
- [x] Sincronização manual de pedidos
- [x] Armazenamento no banco de dados
- [x] Sincronização de múltiplas contas
- [x] Webhook para notificações
- [x] Logs de webhooks
- [x] Polling periódico automático

### 6.2 Visualização Unificada
- [x] Lista de pedidos de todas as contas
- [x] Filtros por status/data
- [x] Detalhes do pedido (modal)
- [x] Interface completa de gestão

### 6.3 Entregáveis Fase 6
- ✅ Central de pedidos unificada
- ✅ Webhooks configurados
- ✅ Notificações de novos pedidos
- ✅ Gestão de status de pedidos

---

## 🔷 FASE 7: Monitoramento e Alertas
**Duração estimada:** 1-2 semanas

### 7.1 Sistema de Alertas
- [x] Alerta de token expirando
- [x] Alerta de novo concorrente
- [x] Alerta de variação de preço
- [x] Alerta de novo pedido
- [x] Sistema de alertas com severidade
- [x] Alerta de novo produto na categoria

### 7.2 Notificações
- [x] Notificações no sistema (API)
- [x] Sistema de notificações no banco
- [x] Marcar como lido/não lido
- [x] Notificações por e-mail
- [x] Interface visual com bell icon
- [x] Integração com Telegram

### 7.3 Entregáveis Fase 7
- ✅ Sistema de alertas configurável
- ✅ Central de notificações
- ✅ E-mails automáticos
- ✅ Histórico de alertas

---

## 🔷 FASE 8: Ferramentas Avançadas
**Duração estimada:** 2-3 semanas

### 8.1 Análise de Concorrência
- [x] Identificar vendedores por marca
- [x] Comparar preços entre vendedores
- [x] Ranking de vendedores por vendas
- [x] Estatísticas por vendedor (média, min, max)
- [x] Histórico de preços
- [x] Análise de tendência de preços

### 8.2 Detector de Oportunidades
- [x] Detecção de baixa concorrência
- [x] Detecção de mercado com preço alto
- [x] Detecção de vendedor dominante
- [x] Produtos sem catálogo
- [x] Produtos mais vendidos sem seu anúncio
- [x] Categorias com pouca concorrência

### 8.3 Ferramentas de Compatibilidade
- [x] Busca por compatibilidade (motos/veículos)
- [x] Sugestões de compatibilidade
- [x] Validação de compatibilidades

### 8.4 Entregáveis Fase 8
- ✅ Análise de concorrência
- ✅ Detector de oportunidades
- ✅ Gestão de compatibilidades
- ✅ Sugestões inteligentes

---

## 🔷 FASE 9: Otimização e Performance
**Duração estimada:** 1-2 semanas

### 9.1 Otimizações
- [x] Cache em múltiplas camadas (Redis/File)
- [x] CacheService com suporte a Redis e File
- [x] Integração de cache nos serviços
- [x] Otimização de queries SQL
- [x] Lazy loading de dados
- [x] Compressão de assets

### 9.2 Segurança
- [x] Criptografia de tokens (AES-256-CBC)
- [x] Rate limiting por IP
- [x] Logs de auditoria
- [x] Proteção CSRF
- [x] Proteção XSS (sanitização)
- [x] SecurityService completo
- [x] Middlewares de segurança

### 9.3 Entregáveis Fase 9
- ✅ Sistema otimizado
- ✅ Segurança reforçada
- ✅ Documentação de segurança
- ✅ Testes de performance

---

## 🔷 FASE 10: Documentação e Deploy
**Duração estimada:** 1 semana

### 10.1 Documentação
- [x] README completo e atualizado
- [x] Documentação da API interna
- [x] Manual do usuário
- [x] Guia de instalação
- [x] Guia de deploy
- [x] Guia de segurança

### 10.2 Deploy
- [x] Guia de configuração de servidor
- [x] Instruções SSL/HTTPS
- [x] Script de backup automatizado
- [x] Sistema de backup via API
- [x] Script de instalação automatizado
- [x] Monitoramento automatizado

### 10.3 Entregáveis Fase 10
- ✅ Documentação completa
- ✅ Sistema em produção
- ✅ Backups configurados
- ✅ Monitoramento ativo

---

# 📊 Cronograma Resumido

| Fase | Descrição | Duração | Status |
|------|-----------|---------|--------|
| 1 | Fundação e OAuth2 | 1-2 sem | ✅ Completo |
| 2 | Core da API | 2-3 sem | ✅ Completo |
| 3 | Categorias e Marcas | 2 sem | ✅ Completo |
| 4 | Análise Catálogo/Comum | 2-3 sem | ✅ Completo |
| 5 | Dashboard | 2 sem | ✅ Completo |
| 6 | Gestão de Pedidos | 2-3 sem | ✅ Completo |
| 7 | Monitoramento e Alertas | 1-2 sem | ✅ Completo |
| 8 | Ferramentas Avançadas | 2-3 sem | ✅ Completo |
| 9 | Otimização e Segurança | 1-2 sem | ✅ Completo |
| 10 | Documentação/Deploy | 1 sem | ✅ Completo |

**Tempo Total Estimado:** 16-23 semanas (4-6 meses)

---

# 🛠️ Stack Tecnológica

## Backend
- **PHP 8.x** - Linguagem principal
- **Composer** - Gerenciador de dependências
- **Guzzle** - Cliente HTTP
- **PDO/Eloquent** - Acesso ao banco
- **Redis** - Cache (opcional)

## Frontend
- **Bootstrap 5** - Framework CSS
- **jQuery/Alpine.js** - Interatividade
- **Chart.js** - Gráficos
- **DataTables** - Tabelas avançadas

## Banco de Dados
- **MySQL 8.x** - Banco principal
- **Redis** - Cache/Filas (opcional)

## Infraestrutura
- **Apache/Nginx** - Web server
- **Let's Encrypt** - SSL
- **CRON** - Agendamento

---

# 📚 Recursos e Referências

## Documentação Oficial
- [Portal de Desenvolvedores ML](https://developers.mercadolivre.com.br)
- [API Reference](https://developers.mercadolivre.com.br/pt_br/api-docs-pt-br)
- [Guia de Autenticação](https://developers.mercadolivre.com.br/pt_br/autenticacao-e-autorizacao)

## SDKs Oficiais
- [PHP SDK](https://github.com/mercadolibre/php-sdk)
- [Python SDK](https://github.com/mercadolibre/python-sdk)
- [Node.js SDK](https://github.com/mercadolibre/nodejs-sdk)

## Endpoints Principais
```
# Autenticação
POST /oauth/token

# Usuários
GET /users/me
GET /users/{user_id}

# Categorias
GET /sites/MLB/categories
GET /categories/{category_id}
GET /categories/{category_id}/attributes

# Busca
GET /sites/MLB/search
GET /items/{item_id}

# Pedidos
GET /orders/search
GET /orders/{order_id}
```

---

# ✅ Checklist de Início

Antes de começar o desenvolvimento:

- [ ] Criar conta no [Mercado Livre Developers](https://developers.mercadolivre.com.br)
- [ ] Registrar aplicação e obter App ID + Secret
- [ ] Configurar URLs de callback
- [ ] Solicitar escopos necessários
- [ ] Preparar ambiente de desenvolvimento (XAMPP/Docker)
- [ ] Criar repositório Git
- [ ] Configurar banco de dados

---

> **Próximo passo:** Iniciar a **Fase 1** - Criação da estrutura do projeto e sistema de autenticação OAuth2.

---

*Documento criado em: Dezembro 2024*  
*Última atualização: Dezembro 2024*

---

## 📝 Resumo das Implementações Recentes

### Funcionalidades Implementadas (Última Sessão)

1. **ItemService e ItemController** - CRUD completo de anúncios
2. **Sistema de Filas/Jobs** - Processamento em background
3. **Sistema de Relatórios** - Por conta, categoria, marca e consolidado
4. **Gráficos Adicionais** - Barras e linha para análise
5. **Polling Automático** - Sincronização periódica
6. **Filtros Dinâmicos** - Baseados em atributos de categoria
7. **Validação de Atributos** - Obrigatórios para criação de anúncios
8. **Análise de Vendedores** - Por marca/categoria
9. **Alerta de Novos Produtos** - Detecção automática
10. **Ferramentas de Compatibilidade** - Para motos/veículos
11. **Otimização de Queries** - Índices e análise de performance
12. **Cache Avançado** - Para requisições HTTP
13. **Integração Telegram** - Notificações automáticas
14. **Lazy Loading** - Carregamento paginado de dados
15. **Monitoramento Automatizado** - Saúde do sistema
16. **Compressão de Assets** - Minificação CSS/JS
17. **Exportação em PDF** - Relatórios em formato PDF
18. **Sistema de Backup via API** - Backup e restauração programática
19. **API de Estatísticas Avançadas** - Métricas detalhadas do sistema

### Status do Projeto

✅ **Todas as fases principais foram concluídas!**

O sistema está completo e pronto para uso em produção, com todas as funcionalidades planejadas implementadas e testadas.

### Funcionalidades Adicionais Implementadas

- **Exportação em PDF**: Relatórios podem ser exportados em formato PDF com formatação profissional
- **Backup Automatizado**: Sistema completo de backup e restauração via API
- **Estatísticas Avançadas**: API completa para obter métricas detalhadas do sistema
- **Integração Telegram**: Notificações automáticas via Telegram para alertas importantes

