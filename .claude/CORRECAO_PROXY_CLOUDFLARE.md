# CORREÇÃO — Proxy Cloudflare Workers para API do Mercado Livre
## eskill.com.br — Módulo 20 Brand Search

> **Problema:** IP da Hostinger bloqueado pela API do Mercado Livre
> **Solução:** Cloudflare Workers como proxy reverso — IP do Cloudflare nunca é bloqueado
> **Custo:** Gratuito (100.000 req/dia no plano free)
> **Tempo estimado:** 2-3 horas

---

## Contexto técnico

O servidor eskill.com.br está na Hostinger (hospedagem compartilhada).
O IP compartilhado da Hostinger está na lista negra da API do ML.
Todas as chamadas a api.mercadolibre.com retornam 403 Forbidden.

A solução é criar um Cloudflare Worker que:
1. Recebe a requisição do servidor eskill.com.br
2. Repassa para api.mercadolibre.com usando o IP limpo do Cloudflare
3. Retorna a resposta para o servidor

O Guzzle do projeto passa a chamar o Worker em vez da API ML diretamente.

---

## PASSO 1 — Criar o Cloudflare Worker

### 1.1 Pré-requisitos
- Conta gratuita em cloudflare.com (se não tiver, criar agora)
- Domínio eskill.com.br já deve estar no Cloudflare (verificar)
- Se não estiver: adicionar domínio ao Cloudflare e apontar nameservers

### 1.2 Criar o Worker via dashboard

1. Acessar: https://dash.cloudflare.com
2. Menu lateral → **Workers & Pages**
3. Clicar **Create application** → **Create Worker**
4. Nome do Worker: `ml-api-proxy`
5. Clicar **Deploy** (cria worker vazio)
6. Clicar **Edit code** e substituir pelo código abaixo

### 1.3 Código do Worker (ml-api-proxy)

```javascript
/**
 * Cloudflare Worker — Proxy para API do Mercado Livre
 * eskill.com.br — Módulo 20 Brand Search
 *
 * Este worker recebe requisições do servidor eskill e as repassa
 * para api.mercadolibre.com usando o IP limpo do Cloudflare.
 *
 * Segurança: validar PROXY_SECRET em toda requisição
 */

const ML_API_BASE = 'https://api.mercadolibre.com';
const ALLOWED_ORIGIN = 'https://eskill.com.br';

export default {
  async fetch(request, env) {

    // ── CORS preflight ────────────────────────────────────────────
    if (request.method === 'OPTIONS') {
      return new Response(null, {
        headers: {
          'Access-Control-Allow-Origin': ALLOWED_ORIGIN,
          'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
          'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Proxy-Secret',
          'Access-Control-Max-Age': '86400',
        },
      });
    }

    // ── Validar secret de segurança ───────────────────────────────
    const proxySecret = request.headers.get('X-Proxy-Secret');
    if (!proxySecret || proxySecret !== env.PROXY_SECRET) {
      return new Response(JSON.stringify({ error: 'Unauthorized' }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      });
    }

    // ── Extrair path e query string ───────────────────────────────
    const url = new URL(request.url);

    // O path real da API ML vem no header X-ML-Path
    // Ex: X-ML-Path: /sites/MLB/search?BRAND=7297804&limit=50
    const mlPath = request.headers.get('X-ML-Path');
    if (!mlPath) {
      return new Response(JSON.stringify({ error: 'X-ML-Path header required' }), {
        status: 400,
        headers: { 'Content-Type': 'application/json' },
      });
    }

    // ── Montar URL de destino ─────────────────────────────────────
    const targetUrl = `${ML_API_BASE}${mlPath}`;

    // ── Montar headers para repassar ao ML ───────────────────────
    const mlHeaders = new Headers();

    // Repassar Authorization se existir
    const authorization = request.headers.get('Authorization');
    if (authorization) {
      mlHeaders.set('Authorization', authorization);
    }

    mlHeaders.set('Content-Type', 'application/json');
    mlHeaders.set('Accept', 'application/json');
    mlHeaders.set('User-Agent', 'eskill-ml-proxy/1.0');

    // ── Fazer a requisição para a API ML ─────────────────────────
    let mlResponse;
    try {
      mlResponse = await fetch(targetUrl, {
        method: request.method,
        headers: mlHeaders,
        body: request.method !== 'GET' && request.method !== 'HEAD'
          ? await request.text()
          : undefined,
      });
    } catch (err) {
      return new Response(JSON.stringify({
        error: 'Proxy fetch failed',
        message: err.message,
      }), {
        status: 502,
        headers: { 'Content-Type': 'application/json' },
      });
    }

    // ── Retornar resposta com headers CORS ───────────────────────
    const responseBody = await mlResponse.text();

    return new Response(responseBody, {
      status: mlResponse.status,
      headers: {
        'Content-Type': mlResponse.headers.get('Content-Type') || 'application/json',
        'Access-Control-Allow-Origin': ALLOWED_ORIGIN,
        'X-Proxy-Status': 'ok',
        'X-ML-Status': String(mlResponse.status),
      },
    });
  },
};
```

### 1.4 Configurar a variável de ambiente PROXY_SECRET

Após salvar o código:
1. No dashboard do Worker → aba **Settings** → **Variables**
2. Clicar **Add variable**
3. Nome: `PROXY_SECRET`
4. Valor: gerar uma string aleatória segura (ex: rodar `openssl rand -hex 32` no terminal)
5. Marcar como **Encrypted**
6. Clicar **Save**

### 1.5 Anotar a URL do Worker

Após o deploy, o Worker terá uma URL no formato:
`https://ml-api-proxy.SEU-SUBDOMINIO.workers.dev`

Anotar essa URL — vai para o .env do projeto.

---

## PASSO 2 — Configurar o .env do projeto

Adicionar as variáveis abaixo no arquivo `.env` do eskill.com.br:

```env
# Cloudflare Worker Proxy — API do Mercado Livre
ML_PROXY_ENABLED=true
ML_PROXY_URL=https://ml-api-proxy.SEU-SUBDOMINIO.workers.dev
ML_PROXY_SECRET=MESMO_SECRET_CONFIGURADO_NO_WORKER
```

> ⚠️ NUNCA commitar o .env com o secret. Confirmar que .env está no .gitignore.

---

## PASSO 3 — Criar MercadoLivreProxyService.php

Criar o serviço que encapsula todas as chamadas HTTP à API ML via proxy.
Todos os Services que chamam a API ML devem usar este serviço.

### Arquivo: `app/Services/MercadoLivre/MercadoLivreProxyService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

use App\Core\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;

/**
 * MercadoLivreProxyService
 *
 * Encapsula todas as chamadas HTTP à API do Mercado Livre.
 * Quando ML_PROXY_ENABLED=true, roteia via Cloudflare Worker
 * para contornar bloqueio de IP.
 *
 * Uso:
 *   $proxy = new MercadoLivreProxyService($guzzle, $logger);
 *   $data  = $proxy->get('/sites/MLB/search?BRAND=7297804&limit=50');
 *   $data  = $proxy->get('/users/123456', $accessToken);
 */
class MercadoLivreProxyService
{
    private const ML_API_BASE    = 'https://api.mercadolibre.com';
    private const TIMEOUT        = 30;
    private const CONNECT_TIMEOUT = 10;

    private bool   $proxyEnabled;
    private string $proxyUrl;
    private string $proxySecret;

    public function __construct(
        private readonly Client $httpClient,
        private readonly Logger $logger
    ) {
        $config = Config::getInstance();
        $this->proxyEnabled = $config->get('ML_PROXY_ENABLED', 'false') === 'true';
        $this->proxyUrl     = rtrim($config->get('ML_PROXY_URL', ''), '/');
        $this->proxySecret  = $config->get('ML_PROXY_SECRET', '');
    }

    /**
     * GET na API do ML — com ou sem access token
     *
     * @param string      $path        Ex: /sites/MLB/search?BRAND=7297804&limit=50
     * @param string|null $accessToken Bearer token OAuth do ML
     * @return array Resposta decodificada do JSON
     * @throws \RuntimeException Em caso de erro HTTP ou proxy
     */
    public function get(string $path, ?string $accessToken = null): array
    {
        return $this->request('GET', $path, $accessToken);
    }

    /**
     * POST na API do ML
     */
    public function post(string $path, array $body, ?string $accessToken = null): array
    {
        return $this->request('POST', $path, $accessToken, $body);
    }

    /**
     * PUT na API do ML
     */
    public function put(string $path, array $body, ?string $accessToken = null): array
    {
        return $this->request('PUT', $path, $accessToken, $body);
    }

    /**
     * Método principal — roteia via proxy ou direto
     */
    private function request(
        string $method,
        string $path,
        ?string $accessToken,
        ?array $body = null
    ): array {
        if ($this->proxyEnabled) {
            return $this->requestViaProxy($method, $path, $accessToken, $body);
        }

        return $this->requestDirect($method, $path, $accessToken, $body);
    }

    /**
     * Requisição via Cloudflare Worker
     */
    private function requestViaProxy(
        string $method,
        string $path,
        ?string $accessToken,
        ?array $body
    ): array {
        $headers = [
            'X-Proxy-Secret' => $this->proxySecret,
            'X-ML-Path'      => $path,
            'Content-Type'   => 'application/json',
            'Accept'         => 'application/json',
        ];

        if ($accessToken) {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        $this->logger->debug('MercadoLivreProxy [VIA PROXY]', [
            'method' => $method,
            'path'   => $path,
        ]);

        try {
            $options = [
                'headers'         => $headers,
                'timeout'         => self::TIMEOUT,
                'connect_timeout' => self::CONNECT_TIMEOUT,
            ];

            if ($body !== null) {
                $options['json'] = $body;
            }

            $response = $this->httpClient->request($method, $this->proxyUrl, $options);
            $contents = (string) $response->getBody();
            $decoded  = json_decode($contents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON from proxy: ' . json_last_error_msg());
            }

            return $decoded;

        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $this->logger->error('MercadoLivreProxy error via proxy', [
                'path'   => $path,
                'status' => $statusCode,
                'error'  => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                "ML Proxy request failed [{$statusCode}]: " . $e->getMessage(),
                $statusCode,
                $e
            );
        }
    }

    /**
     * Requisição direta à API ML (quando proxy está desabilitado)
     */
    private function requestDirect(
        string $method,
        string $path,
        ?string $accessToken,
        ?array $body
    ): array {
        $url     = self::ML_API_BASE . $path;
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if ($accessToken) {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        $this->logger->debug('MercadoLivreProxy [DIRETO]', [
            'method' => $method,
            'url'    => $url,
        ]);

        try {
            $options = [
                'headers'         => $headers,
                'timeout'         => self::TIMEOUT,
                'connect_timeout' => self::CONNECT_TIMEOUT,
            ];

            if ($body !== null) {
                $options['json'] = $body;
            }

            $response = $this->httpClient->request($method, $url, $options);
            $contents = (string) $response->getBody();
            $decoded  = json_decode($contents, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON from ML API: ' . json_last_error_msg());
            }

            return $decoded;

        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $this->logger->error('MercadoLivreProxy error direct', [
                'url'    => $url,
                'status' => $statusCode,
                'error'  => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                "ML API request failed [{$statusCode}]: " . $e->getMessage(),
                $statusCode,
                $e
            );
        }
    }

    /**
     * Verifica se o proxy está ativo
     */
    public function isProxyEnabled(): bool
    {
        return $this->proxyEnabled;
    }

    /**
     * Testa a conexão com a API ML (via proxy ou direto)
     * Útil para diagnóstico no painel
     */
    public function testConnection(): array
    {
        $start = microtime(true);

        try {
            $result = $this->get('/sites/MLB');
            $ms     = round((microtime(true) - $start) * 1000);

            return [
                'success'      => true,
                'via_proxy'    => $this->proxyEnabled,
                'response_ms'  => $ms,
                'site_id'      => $result['id'] ?? null,
                'site_name'    => $result['name'] ?? null,
            ];
        } catch (\Throwable $e) {
            $ms = round((microtime(true) - $start) * 1000);

            return [
                'success'     => false,
                'via_proxy'   => $this->proxyEnabled,
                'response_ms' => $ms,
                'error'       => $e->getMessage(),
            ];
        }
    }
}
```

---

## PASSO 4 — Adaptar BrandSearchService para usar o proxy

Substituir as chamadas Guzzle diretas pelo `MercadoLivreProxyService`.

### Alterações em `app/Services/MercadoLivre/BrandSearchService.php`

**Antes (chamada direta):**
```php
public function __construct(
    private BrandSearchModel $model,
    private Client $httpClient,
    private Logger $logger
) {}

// Dentro dos métodos:
$response = $this->httpClient->get('https://api.mercadolibre.com/sites/MLB/search?BRAND=...');
```

**Depois (via proxy):**
```php
public function __construct(
    private BrandSearchModel $model,
    private MercadoLivreProxyService $proxy,  // ← substituir Client por proxy
    private Logger $logger
) {}

// Dentro dos métodos:
$data = $this->proxy->get('/sites/MLB/search?BRAND=' . $brandId . '&limit=1', $accessToken);
$data = $this->proxy->get('/users/' . $sellerId, $accessToken);
```

**Regra:** Toda chamada `$this->httpClient->get('https://api.mercadolibre.com...')` deve
ser substituída por `$this->proxy->get('/path...', $accessToken)`.

Fazer o mesmo em TODOS os outros Services que chamam a API ML:
- CompetitorAnalysisService.php
- DynamicPricingService.php
- ItemService.php
- E qualquer outro em app/Services/MercadoLivre/

---

## PASSO 5 — Adicionar rota de diagnóstico

Adicionar endpoint para testar o proxy diretamente pelo painel.

### Em `app/Routes/api.php` — adicionar:
```php
Router::get('/api/ml-proxy/test', [DiagnosticController::class, 'testProxy']);
```

### Em `app/Controllers/DiagnosticController.php` — adicionar método:
```php
public function testProxy(): void
{
    $proxy  = new MercadoLivreProxyService($this->httpClient, $this->logger);
    $result = $proxy->testConnection();
    $this->jsonResponse($result, $result['success'] ? 200 : 502);
}
```

---

## PASSO 6 — Testar tudo

### 6.1 Testar o Worker diretamente (curl)
```bash
# Substituir com sua URL e secret reais
curl -X GET "https://ml-api-proxy.SEU-SUBDOMINIO.workers.dev" \
  -H "X-Proxy-Secret: SEU_SECRET" \
  -H "X-ML-Path: /sites/MLB" \
  -v

# Esperado: {"id":"MLB","name":"Brasil",...} com HTTP 200
```

### 6.2 Testar via endpoint de diagnóstico
```bash
curl -H "Authorization: Bearer SEU_TOKEN" \
  https://eskill.com.br/api/ml-proxy/test

# Esperado:
# {
#   "success": true,
#   "via_proxy": true,
#   "response_ms": 245,
#   "site_id": "MLB",
#   "site_name": "Brasil"
# }
```

### 6.3 Rodar o worker do Brand Search
```bash
# Após confirmar proxy funcionando:
php bin/brand-search-worker.php --search-id=1

# Acompanhar logs:
tail -f storage/logs/brand-search.log

# Verificar banco após conclusão:
SELECT status, progress, total_sellers, total_items
FROM brand_searches
WHERE id = 1;
```

---

## Checklist de validação

- [ ] Worker deployado em Cloudflare com código correto
- [ ] `PROXY_SECRET` configurado como variável encrypted no Worker
- [ ] `.env` atualizado com `ML_PROXY_ENABLED`, `ML_PROXY_URL`, `ML_PROXY_SECRET`
- [ ] `MercadoLivreProxyService.php` criado em `app/Services/MercadoLivre/`
- [ ] `BrandSearchService.php` adaptado para usar `MercadoLivreProxyService`
- [ ] `curl` no Worker retorna HTTP 200 com dados do ML
- [ ] Endpoint `/api/ml-proxy/test` retorna `success: true`
- [ ] Worker `brand-search-worker.php` processa busca sem erro 403
- [ ] Banco tem dados em `brand_sellers` após execução do worker
- [ ] Tela mostra resultados reais ao clicar Buscar

---

## Observações importantes

**Rate limit do Cloudflare Workers free:**
- 100.000 requisições/dia — suficiente para uso normal
- Se ultrapassar: upgrade para $5/mês (10 milhões req/mês)

**Segurança:**
- O `PROXY_SECRET` impede uso não autorizado do Worker
- Nunca expor a URL do Worker publicamente sem autenticação
- Adicionar `ML_PROXY_URL` e `ML_PROXY_SECRET` ao `.env.example` sem os valores reais

**Se o Worker retornar 401:**
- Verificar se o `PROXY_SECRET` no `.env` é idêntico ao configurado no Cloudflare

**Se o Worker retornar 403 do ML mesmo via Cloudflare:**
- O IP do Cloudflare raramente é bloqueado — se acontecer, trocar a região do Worker
- No dashboard: Workers → ml-api-proxy → Settings → mudar região

---

*eskill.com.br — Correção de IP bloqueado via Cloudflare Workers Proxy — 2026-04-06*
