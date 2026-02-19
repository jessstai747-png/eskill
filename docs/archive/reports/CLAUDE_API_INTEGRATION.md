# 🔌 Claude API Integration Guide

Guia completo para integrar o sistema de Long-Running Agents com a API real da Anthropic Claude.

---

## 📋 Overview

O sistema suporta dois modos de operação:

1. **🔸 Modo Simulação** (padrão) - Para testes e desenvolvimento
2. **🔹 Modo Produção** - Com Claude API real

### Status Atual

- ✅ ClaudeClient implementado
- ✅ InitializerAgent suporta Claude API
- ✅ CodingAgent suporta Claude API
- ✅ Fallback automático para simulação
- ✅ Script de teste da API

---

## 🚀 Setup Claude API

### 1. Obter API Key

1. Acesse: https://console.anthropic.com/
2. Crie uma conta ou faça login
3. Navegue até **API Keys**
4. Crie uma nova chave
5. Copie a chave (começa com `sk-ant-...`)

### 2. Configurar no Projeto

Adicione no arquivo `.env`:

```bash
ANTHROPIC_API_KEY=sk-ant-api03-...sua-chave-aqui...
```

**⚠️ IMPORTANTE:** Nunca commite o `.env` no Git!

### 3. Testar Conexão

```bash
php scripts/test_claude_api.php
```

**Saída esperada:**
```
🔌 Testing Claude API Connection
================================

✓ API key found in environment
  Length: 108 characters

Creating Claude client...
✓ Client created successfully

Testing API connection...
✅ Connection successful!

Testing message completion...
✓ Response: 4

📊 Token Usage:
   Input tokens:  12
   Output tokens: 1

✅ Claude API is ready to use!
```

---

## 🎯 Como Funciona

### Sistema Híbrido

O sistema detecta automaticamente se Claude API está disponível:

```php
// Sem API key → Modo simulação
php scripts/example_ecommerce_project.php

// Com API key → Usa Claude real
ANTHROPIC_API_KEY=sk-ant-... php scripts/example_ecommerce_project.php
```

### Fallback Automático

Se Claude API falhar (quota, erro de rede, etc), o sistema automaticamente volta para simulação:

```
[InitializerAgent] Using Claude API to generate features
[InitializerAgent] Claude API failed, falling back to simulation: API quota exceeded
[InitializerAgent] Using simulated feature generation
```

---

## 🏗️ Arquitetura da Integração

### ClaudeClient

Localização: `app/Services/ClaudeClient.php`

**Métodos principais:**

```php
// Testar conexão
$client->testConnection(): bool

// Completar mensagem genérica
$client->complete(array $messages, array $options): array

// Gerar feature list (especializado)
$client->generateFeatureList(array $requirements, string $category): array

// Implementar feature (especializado)
$client->implementFeature(array $feature, string $projectPath): array

// Obter estatísticas de uso
$client->getUsageStats(array $response): array
```

### InitializerAgent

**Antes (simulação):**
```php
$initializer = new InitializerAgent();
$result = $initializer->initialize($params);
```

**Depois (com Claude):**
```php
$claudeClient = new ClaudeClient();
$initializer = new InitializerAgent($claudeClient);
$result = $initializer->initialize($params);
// Usa Claude para gerar features
```

### CodingAgent

**Antes (simulação):**
```php
$codingAgent = new CodingAgent();
$result = $codingAgent->executeSession($params);
```

**Depois (com Claude):**
```php
$claudeClient = new ClaudeClient();
$codingAgent = new CodingAgent($claudeClient);
$result = $codingAgent->executeSession($params);
// Usa Claude para implementar código real
```

### AgentService

Auto-detecta e configura Claude:

```php
$service = new AgentService();
// Automaticamente tenta criar ClaudeClient se ANTHROPIC_API_KEY existe
```

---

## 📊 Diferenças de Comportamento

### Geração de Features

| Aspecto | Simulação | Claude API |
|---------|-----------|------------|
| **Quantidade** | Fixa (~3 por requisito) | Inteligente (100+) |
| **Qualidade** | Genérica | Contextual |
| **Testes** | Básicos | Específicos |
| **Tempo** | <1s | 5-15s |

### Implementação de Features

| Aspecto | Simulação | Claude API |
|---------|-----------|------------|
| **Código** | Mock | Real PHP |
| **Arquivos** | Template | Customizado |
| **Lógica** | Simples | Completa |
| **Tempo** | <1s | 10-30s |

---

## 💰 Custos Estimados

### Modelo: Claude 3.5 Sonnet

- **Input**: $3.00 / 1M tokens
- **Output**: $15.00 / 1M tokens

### Por Projeto

**Projeto Pequeno (10 features):**
- Geração features: ~2K input + 15K output = $0.24
- Implementações: ~50K input + 100K output = $1.65
- **Total: ~$2.00**

**Projeto Médio (30 features):**
- Geração features: ~5K input + 50K output = $0.77
- Implementações: ~150K input + 300K output = $4.95
- **Total: ~$6.00**

**Projeto Grande (100 features):**
- Geração features: ~15K input + 150K output = $2.30
- Implementações: ~500K input + 1M output = $16.50
- **Total: ~$19.00**

### Otimizações

1. **Cache de Contexto** - Reusar partes comuns do prompt
2. **Batch Processing** - Agrupar features similares
3. **Fallback Seletivo** - Claude apenas para features complexas

---

## 🔧 Configurações Avançadas

### Customizar Modelo

No `.env`:

```bash
# Usar modelo específico
ANTHROPIC_MODEL=claude-3-5-sonnet-20241022

# Ajustar max tokens
ANTHROPIC_MAX_TOKENS=8192

# Ajustar temperatura (criatividade)
ANTHROPIC_TEMPERATURE=0.7
```

### Timeout

Por padrão, requisições têm timeout de 120 segundos. Para ajustar:

```php
// Em ClaudeClient.php, linha ~175
CURLOPT_TIMEOUT => 180, // 3 minutos
```

### Retry Logic

Adicionar retry automático em caso de falha temporária:

```php
private function makeRequestWithRetry(array $payload, int $maxRetries = 3): array
{
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            return $this->makeRequest($payload);
        } catch (\RuntimeException $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            sleep(2 ** $attempt); // Exponential backoff
        }
    }
}
```

---

## 🧪 Testes

### Teste Básico

```bash
php scripts/test_claude_api.php
```

### Teste com Projeto Real

```bash
# Criar projeto usando Claude
php scripts/example_ecommerce_project.php
```

### Comparar Simulação vs Claude

```bash
# Projeto 1: Simulação
unset ANTHROPIC_API_KEY
php scripts/example_ecommerce_project.php

# Projeto 2: Claude
export ANTHROPIC_API_KEY=sk-ant-...
php scripts/example_ecommerce_project.php

# Comparar resultados
diff storage/agent_projects/1/feature_list.json \
     storage/agent_projects/2/feature_list.json
```

---

## 📈 Monitoramento

### Logs

Ativar logs detalhados:

```php
// Em ClaudeClient.php
private bool $debug = true;

private function makeRequest(array $payload): array
{
    if ($this->debug) {
        error_log("Claude API Request: " . json_encode($payload));
    }
    // ...
}
```

### Métricas

Rastrear uso de tokens:

```php
$response = $client->complete($messages);
$usage = $client->getUsageStats($response);

echo "Tokens: {$usage['input_tokens']} in / {$usage['output_tokens']} out\n";
```

---

## ⚠️ Troubleshooting

### Erro: "ANTHROPIC_API_KEY not configured"

**Causa:** API key não encontrada no `.env`

**Solução:**
```bash
echo "ANTHROPIC_API_KEY=sk-ant-..." >> .env
```

### Erro: "Claude API error (HTTP 401)"

**Causa:** API key inválida

**Solução:**
1. Verificar key no console Anthropic
2. Regenerar key se necessário
3. Atualizar `.env`

### Erro: "Claude API error (HTTP 429)"

**Causa:** Quota de API excedida

**Solução:**
1. Aguardar reset de quota (geralmente 1min)
2. Ou fazer upgrade do plano Anthropic
3. Sistema volta para simulação automaticamente

### Erro: "Failed to parse feature list from Claude response"

**Causa:** Claude retornou formato inesperado

**Solução:**
1. Verificar prompt em `ClaudeClient::buildFeatureListPrompt()`
2. Adicionar parsing mais robusto
3. Logs do erro ajudam a debug

### Performance Lenta

**Causa:** Claude API tem latência (5-30s por chamada)

**Solução:**
1. Aceitar que é mais lento que simulação
2. Executar em background/cron
3. Usar simulação para desenvolvimento rápido

---

## 🎓 Best Practices

### 1. Desenvolvimento Local

Use **simulação** para desenvolvimento rápido:
```bash
# .env.local
# ANTHROPIC_API_KEY=  # comentado
```

### 2. CI/CD

Use **simulação** nos testes automatizados para velocidade e custo zero.

### 3. Produção

Use **Claude API** para projetos reais onde qualidade é crítica.

### 4. Staging

Use **Claude API** com modelo menor (Claude Haiku) para economia:
```bash
ANTHROPIC_MODEL=claude-3-haiku-20240307
```

### 5. Caching

Cache de resultados para evitar chamadas duplicadas:
```php
$cacheKey = md5(json_encode($requirements));
if ($cached = $this->getCache($cacheKey)) {
    return $cached;
}
```

---

## 📚 Próximos Passos

### 1. ✅ Implementar no CodingAgent

Adicionar lógica real de implementação usando Claude:

```php
private function implementFeature(array $feature, string $projectPath): array
{
    if ($this->useClaudeApi) {
        $result = $this->claudeClient->implementFeature($feature, $projectPath);
        
        // Aplicar mudanças no filesystem
        foreach ($result['files_to_create'] as $file) {
            file_put_contents($file['path'], $file['content']);
        }
        
        return $result;
    }
    
    // Fallback: simulação
    return $this->simulateImplementation($feature);
}
```

### 2. Adicionar Cache de Contexto

Usar o recurso de Prompt Caching da Anthropic:
```php
$payload = [
    'model' => $this->model,
    'system' => [
        [
            'type' => 'text',
            'text' => 'You are an expert developer...',
            'cache_control' => ['type' => 'ephemeral']
        ]
    ],
    // ...
];
```

### 3. Implementar Streaming

Para feedback em tempo real:
```php
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
    echo $data;
    return strlen($data);
});
```

### 4. Adicionar Telemetria

Rastrear performance e custos:
```php
$metrics = [
    'project_id' => $projectId,
    'operation' => 'generate_features',
    'tokens_in' => $usage['input_tokens'],
    'tokens_out' => $usage['output_tokens'],
    'cost' => $this->calculateCost($usage),
    'duration_ms' => $duration,
];

$this->logMetrics($metrics);
```

---

## 🔗 Referências

- [Anthropic API Docs](https://docs.anthropic.com/claude/reference/getting-started-with-the-api)
- [Claude Models](https://docs.anthropic.com/claude/docs/models-overview)
- [Prompt Caching](https://docs.anthropic.com/claude/docs/prompt-caching)
- [Best Practices](https://docs.anthropic.com/claude/docs/intro-to-prompting)

---

**Status:** 🟢 Sistema pronto para usar Claude API

**Próxima Ação:** Adicionar sua API key no `.env` e testar!
