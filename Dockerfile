# ==============================================================================
# Multi-stage Dockerfile para produção
# Mercado Livre Manager - PHP 8.4 + Apache
#
# Nota: composer.lock foi resolvido com doctrine/instantiator 2.1.0, que exige
# PHP ^8.4. A imagem de runtime precisa estar na mesma versão, senão a
# aplicação falha ao carregar (mesma causa raiz corrigida no CI do Playwright).
# ==============================================================================

# Stage 1: Dependencies
FROM composer:2 AS composer-deps

WORKDIR /app

COPY composer.json composer.lock* ./
# composer.json exige ext-redis (*), que a imagem base `composer:2` não tem.
# --ignore-platform-req é seguro aqui: este estágio só resolve/baixa pacotes
# (--no-scripts --no-autoloader), nunca executa código da aplicação que
# realmente precise da extensão carregada. A extensão é instalada de fato no
# estágio de runtime abaixo.
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-redis \
    && rm -rf /root/.composer/cache

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ==============================================================================
# Stage 2: Production image
# ==============================================================================
FROM php:8.4-apache AS production

# Metadados
LABEL maintainer="eskill" \
      description="Mercado Livre Manager" \
      version="1.0"

# Instalar extensões PHP necessárias
# Nota: "json" foi removido da lista de docker-php-ext-install — desde o PHP
# 8.0 ela é compilada estaticamente no core (não gera modules/*.so), e tentar
# instalá-la como módulo falha com "Error 1: cp: cannot stat 'modules/*'".
RUN apt-get update && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libgmp-dev \
        unzip \
        cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        curl \
        mbstring \
        zip \
        gd \
        opcache \
        gmp \
        bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && rm -rf /tmp/pear

# Configurar Apache
RUN a2enmod rewrite headers expires deflate

# Apache VirtualHost - apontar para public/
RUN echo '<VirtualHost *:80>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot /var/www/html/public\n\
    \n\
    <Directory /var/www/html/public>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    \n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# PHP production settings
RUN echo '\
opcache.enable=1\n\
opcache.memory_consumption=128\n\
opcache.interned_strings_buffer=16\n\
opcache.max_accelerated_files=10000\n\
opcache.revalidate_freq=0\n\
opcache.validate_timestamps=0\n\
opcache.save_comments=1\n\
\n\
expose_php=Off\n\
display_errors=Off\n\
display_startup_errors=Off\n\
log_errors=On\n\
error_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT\n\
error_log=/var/www/html/storage/logs/php-error.log\n\
\n\
upload_max_filesize=10M\n\
post_max_size=12M\n\
memory_limit=256M\n\
max_execution_time=60\n\
max_input_time=60\n\
\n\
session.cookie_httponly=1\n\
session.cookie_samesite=Lax\n\
session.use_strict_mode=1\n\
' > /usr/local/etc/php/conf.d/production.ini

# Copiar aplicação
WORKDIR /var/www/html

# Copiar código (sem vendor)
COPY --chown=www-data:www-data . .

# Copiar vendor do stage de dependências
COPY --from=composer-deps --chown=www-data:www-data /app/vendor ./vendor

# Criar diretórios necessários com permissões corretas
RUN mkdir -p \
        storage/logs \
        storage/cache \
        storage/sessions \
    && chown -R www-data:www-data storage \
    && chmod -R 775 storage

# Remover arquivos desnecessários em produção
RUN rm -rf \
    tests/ \
    docs/ \
    examples/ \
    playwright-report/ \
    test-results/ \
    phpunit.xml \
    phpcs.xml \
    phpmd.xml \
    playwright.config.ts \
    docker-compose.yml \
    .env.example \
    .env.testing \
    AGENTS.md \
    CHANGELOG.md \
    README.md \
    *.md \
    cookies.txt \
    create_real_token.php \
    create_test_account.php \
    test_*.php \
    real_auth.php

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/api/health || exit 1

# Porta
EXPOSE 80

# Usuário não-root para execução
USER www-data

# Entrypoint padrão do Apache
CMD ["apache2-foreground"]
