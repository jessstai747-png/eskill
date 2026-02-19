---
applyTo: "**/Services/**/*.php,**/Controllers/**/*.php"
---

# Regras para Services e Controllers PHP

## Estrutura do Service
```php
<?php
declare(strict_types=1);

namespace App\Services;

// 1. Imports (use statements)
// 2. Constantes da classe
// 3. Propriedades (tipadas)
// 4. Construtor com dependency injection
// 5. Métodos públicos (API do service)
// 6. Métodos privados (auxiliares)
```

## Padrões Obrigatórios

### Guzzle Client (APIs externas)
- Criar Guzzle client dedicado para cada API externa
- Configurar base_uri, timeout, headers padrão
- Implementar retry com backoff para erros transientes

### Error Handling
```php
try {
    $response = $this->client->request('GET', $endpoint);
    $data = json_decode($response->getBody()->getContents(), true);
    return ['data' => $data, 'error' => null];
} catch (ClientException $e) {
    $status = $e->getResponse()->getStatusCode();
    if ($status === 401) { /* refresh token */ }
    if ($status === 429) { /* rate limit - retry */ }
    $this->logger->error('API error', ['status' => $status, 'endpoint' => $endpoint]);
    return ['data' => null, 'error' => 'Erro na API: ' . $status];
} catch (\Exception $e) {
    $this->logger->error('Unexpected error', ['message' => $e->getMessage()]);
    throw $e;
}
```

### Retry com Backoff
- Implementar para status 429, 500, 502, 503, 504
- Máximo 3 tentativas
- Delay: 1s, 2s, 4s (exponential)

### Tipagem
- Type hints em TODOS os parâmetros e retornos
- NUNCA retornar `mixed` sem justificativa
- Usar union types quando necessário: `string|null`
- Arrays tipados no PHPDoc: `@param array<string, mixed> $data`

## Controllers
- Herdar de `BaseController`
- Lógica mínima — delegar tudo para Services
- Validar input no início do método
- Retornar JSON consistente: `['data' => ..., 'error' => ..., 'message' => ...]`

## NUNCA
- Chamar API sem try/catch
- Hardcodar URLs — usar variáveis de ambiente
- Ignorar rate limiting (especialmente API do ML)
- Retornar response raw da API (transforme para formato interno)
- Colocar lógica de negócio no Controller
- Usar echo/var_dump/print_r — use Monolog
