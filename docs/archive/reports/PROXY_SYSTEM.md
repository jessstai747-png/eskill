# Sistema de Proxy e Fallback para API do Mercado Livre

## 📋 Resumo da Implementação

Este documento descreve a solução profissional implementada para contornar o bloqueio da API de busca do Mercado Livre em servidores de data center.

## 🔍 Problema Identificado

O Mercado Livre implementou um **PolicyAgent** que bloqueia requisições de busca (`/sites/MLB/search`) vindas de IPs de data center. Isso NÃO é um problema de token - os tokens estão funcionando corretamente para endpoints autenticados como `/users/me` e `/items/{id}`.

**Resposta típica do bloqueio:**
```json
{
  "message": "forbidden",
  "error": "forbidden",
  "status": 403,
  "blocked_by": "PolicyAgent"
}
```

## ✅ Solução Implementada

### 1. ProxyService (`/app/Services/ProxyService.php`)

Sistema completo de gerenciamento de proxies com:
- **Pool de proxies** com suporte a múltiplos tipos (HTTP, HTTPS, SOCKS4, SOCKS5)
- **Rotação automática** (round-robin, random, best)
- **Health checks** periódicos
- **Blacklist automática** após 3 falhas
- **Estatísticas** de uso e taxa de sucesso
- **Persistência** no banco de dados

**Métodos principais:**
- `getNextProxy()` - Obtém próximo proxy disponível (round-robin)
- `getBestProxy()` - Obtém proxy com melhor taxa de sucesso
- `testProxy()` - Testa um proxy específico
- `recordSuccess()` / `recordFailure()` - Registra resultados
- `executeWithFallback()` - Executa request com fallback automático

### 2. AlternativeSearchService (`/app/Services/AlternativeSearchService.php`)

Serviço de busca alternativo quando API está bloqueada:
- **Web scraping** com User-Agent de navegador
- **Busca por vendedores** conhecidos da marca
- **Cache de dados históricos**
- Rotação automática de User-Agents

### 3. DeepResearchService (Modificado)

O serviço principal agora implementa uma **cadeia de fallback**:

```
1. API Oficial do ML
   ↓ (se bloqueado)
2. AlternativeSearchService (scraping)
   ↓ (se falhar)
3. Cache Histórico
   ↓ (se não houver)
4. Erro informativo para o usuário
```

### 4. Interface de Gerenciamento (`/settings/proxies`)

Painel administrativo para gerenciar proxies:
- Adicionar/remover proxies
- Testar conectividade
- Ver estatísticas
- Limpar blacklist

### 5. API de Proxies

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/api/proxies` | Lista todos os proxies |
| POST | `/api/proxies` | Adiciona novo proxy |
| DELETE | `/api/proxies/{id}` | Remove proxy |
| POST | `/api/proxies/{id}/test` | Testa proxy específico |
| POST | `/api/proxies/test-all` | Testa todos os proxies |
| POST | `/api/proxies/clear-blacklist` | Limpa blacklist |
| GET | `/api/proxies/status` | Status do sistema |

## 🔧 Configuração

### Arquivo `.env`

```env
# Proxy Configuration
ML_PROXY_ENABLED=true
ML_PROXY_TYPE=http
ML_PROXY_HOST=proxy.exemplo.com
ML_PROXY_PORT=8080
ML_PROXY_USER=seu_usuario
ML_PROXY_PASS=sua_senha
```

### Banco de Dados

Tabelas criadas:
- `ml_proxies` - Pool de proxies
- `ml_proxy_logs` - Logs de uso
- `ml_research_cache` - Cache de pesquisas

Para criar as tabelas:
```bash
php scripts/migrate.php
```

## 📊 Diagnóstico

Execute o script de diagnóstico para verificar o sistema:

```bash
php scripts/diagnose_proxy_system.php
```

## 🌐 Provedores de Proxy Recomendados

Para melhor performance, use serviços de **proxy residencial brasileiro**:

1. **BrightData** (brightdata.com) - Premium, alta qualidade
2. **Oxylabs** (oxylabs.io) - Grande pool de IPs
3. **SmartProxy** (smartproxy.com) - Custo-benefício
4. **Webshare** (webshare.io) - Econômico

## 📝 Arquivos Criados/Modificados

### Novos Arquivos
- `/app/Services/ProxyService.php`
- `/app/Services/AlternativeSearchService.php`
- `/app/Controllers/ProxyController.php`
- `/app/Views/settings/proxies.php`
- `/database/migrations/2025_12_19_create_proxies_table.php`
- `/scripts/diagnose_proxy_system.php`

### Arquivos Modificados
- `/app/Services/DeepResearchService.php` - Adicionado sistema de fallback
- `/app/Services/MercadoLivreClient.php` - Adicionado suporte a proxy
- `/app/Controllers/SettingsController.php` - Adicionado método proxies()
- `/app/Views/deep_research/index.php` - Melhor tratamento de erro
- `/public/index.php` - Novas rotas
- `/.env` - Variáveis de proxy

## 🚀 Como Usar

1. **Configure um proxy** em `/settings/proxies` ou no `.env`
2. **Teste o proxy** usando o botão "Testar"
3. **Execute o Deep Research** normalmente

O sistema tentará automaticamente:
1. Usar a API oficial
2. Se bloqueado, usar proxy configurado
3. Se ainda falhar, usar busca alternativa
4. Se nada funcionar, mostrar dados do cache

## ⚠️ Notas Importantes

- O bloqueio é por **IP do servidor**, não por conta do usuário
- Tokens de autenticação funcionam normalmente para outros endpoints
- A solução ideal é usar **proxy residencial brasileiro**
- O cache histórico serve como último recurso
