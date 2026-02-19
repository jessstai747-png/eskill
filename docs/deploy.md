# Deploy (visão geral)

## Pré-requisitos
- PHP 8.0+ (recomendado 8.2+)
- Composer
- MySQL 8 (ou compatível)
- Redis (opcional; cache pode operar em file)

## Variáveis de ambiente (mínimas)
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` (mínimo 32 caracteres)
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

## Integrações (Brevo)
Para habilitar a integração com a Brevo, configure:
- `BREVO_API_KEY`
- `BREVO_BASE_URL` (opcional)
- `BREVO_TIMEOUT_SECONDS` (opcional)
- `BREVO_CACHE_TTL_SECONDS` (opcional)

Observação: ao inicializar a integração, o sistema fará **auto-criação/upgrade** das tabelas de persistência Brevo (MySQL/MariaDB):
- `brevo_lists`
- `brevo_contacts`
- `brevo_sync_runs`

Detalhes e exemplos de uso: `docs/integrations/brevo.md`.

## Passo a passo (produção)
1. Instalar dependências:
   - `composer install --no-dev --optimize-autoloader`
2. Configurar `.env` do ambiente (não versionar).
3. Garantir permissões de escrita em:
   - `storage/cache`
   - `storage/logs`
4. Subir/atualizar serviços:
   - Web server (nginx/apache) apontando para `public/`
   - PHP-FPM
5. Validar health checks internos:
   - `GET /api/integrations/brevo/health` (sessão autenticada)
6. Validar endpoints de listas (opcional):
   - `GET /api/integrations/brevo/lists` (sessão autenticada)
7. Executar sync inicial (recomendado):
   - `POST /api/integrations/brevo/sync/lists` (sessão autenticada)
   - `POST /api/integrations/brevo/sync/contacts` (sessão autenticada)
   - `GET /api/integrations/brevo/status` para conferir o último sync

## CI/CD (GitHub Actions)
- CI: `.github/workflows/tests.yml`
  - Provisiona MySQL/Redis e roda `phpunit` (Unit + Integration).
- CD: `.github/workflows/deploy.yml`
  - Instala dependências e roda testes Unit antes de publicar via `rsync`.
