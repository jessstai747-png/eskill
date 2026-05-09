/**
 * Cloudflare Worker — Proxy ML API
 * eskill.com.br — ml-api-proxy
 *
 * Headers:
 *   X-Proxy-Secret  → validação (deve bater com env.PROXY_SECRET)
 *   X-ML-Path       → path da API ML (ex: /users/123/items/search?limit=50)
 *   Authorization   → Bearer TOKEN_OAUTH2 repassado intacto
 */

const ML_API_BASE = 'https://api.mercadolibre.com';

export default {
  async fetch(request, env) {
    if (request.method === 'OPTIONS') {
      return new Response(null, {
        status: 204,
        headers: {
          'Access-Control-Allow-Origin': '*',
          'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
          'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Proxy-Secret, X-ML-Path',
          'Access-Control-Max-Age': '86400',
        },
      });
    }

    const proxySecret = request.headers.get('X-Proxy-Secret');
    const expectedSecret = env.PROXY_SECRET;

    if (!expectedSecret) {
      return jsonResp({ error: 'Worker misconfigured: PROXY_SECRET not set' }, 500);
    }
    if (!proxySecret || proxySecret !== expectedSecret) {
      return jsonResp({ error: 'Unauthorized' }, 401);
    }

    const mlPath = request.headers.get('X-ML-Path');
    if (!mlPath || !mlPath.startsWith('/')) {
      return jsonResp({ error: 'X-ML-Path header required' }, 400);
    }

    const targetUrl = `${ML_API_BASE}${mlPath}`;
    const mlHeaders = new Headers();
    mlHeaders.set('Accept', 'application/json');
    mlHeaders.set('Content-Type', 'application/json');
    mlHeaders.set('User-Agent', 'eskill-ml-proxy/2.0');

    const authorization = request.headers.get('Authorization');
    if (authorization) {
      mlHeaders.set('Authorization', authorization);
    }

    let mlResp;
    try {
      const body = (request.method !== 'GET' && request.method !== 'HEAD')
        ? await request.text() : undefined;
      mlResp = await fetch(targetUrl, { method: request.method, headers: mlHeaders, body });
    } catch (err) {
      return jsonResp({ error: 'Proxy fetch failed', message: err.message }, 502);
    }

    const respBody = await mlResp.text();
    return new Response(respBody, {
      status: mlResp.status,
      headers: {
        'Content-Type': mlResp.headers.get('Content-Type') || 'application/json',
        'Access-Control-Allow-Origin': '*',
        'X-Proxy-Status': 'ok',
        'X-ML-Status': String(mlResp.status),
      },
    });
  },
};

function jsonResp(data, status = 200) {
  return new Response(JSON.stringify(data), {
    status,
    headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' },
  });
}
