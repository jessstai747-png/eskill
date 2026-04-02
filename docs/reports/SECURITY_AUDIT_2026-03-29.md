# Informe de Auditoría de Seguridad

## Resumen Ejecutivo

Se realizó una auditoría técnica profunda del sistema `eskill.com.br` con foco en arquitectura, controles de seguridad, exposición de servicios, eventos operativos, configuración OAuth, gestión de acceso, protección de datos y postura general de cumplimiento.

Resultado ejecutivo:

- **Riesgo global actual:** Alto
- **Hallazgos críticos:** 1
- **Hallazgos altos:** 4
- **Hallazgos medios:** 5
- **Hallazgos bajos:** 2

Los controles base del sistema son sólidos en varias áreas:

- cabeceras HTTP endurecidas
- TLS válido
- sesiones seguras
- CSRF para flujos stateful
- rate limiting
- logging estructurado
- cifrado de tokens sensibles
- webhook signatures

Sin embargo, persisten debilidades materiales que elevan el riesgo:

- configuración OAuth/APP_KEY con placeholders en el entorno auditado
- secreto 2FA almacenado sin cifrado visible
- CORS abierto con `*` en la superficie `OpenClaw`
- exposición de puertos/servicios auxiliares públicamente accesibles
- superficies de ejecución de comandos del sistema

## Alcance

La auditoría cubre:

- aplicación PHP y su arquitectura de seguridad
- middleware, autenticación, 2FA, OAuth y tokens
- políticas de acceso, sesiones y protección CSRF
- logs operativos y de seguridad disponibles en `storage/logs`
- configuración observable desde el host y exposición de puertos
- evidencias de cumplimiento disponibles en el repositorio
- pruebas técnicas no intrusivas sobre endpoints públicos y TLS

Quedan fuera de alcance directo por falta de acceso dedicado:

- configuraciones completas de firewalls perimetrales
- appliances o dispositivos de red administrados externamente
- SIEM corporativo externo
- inventario formal de activos fuera del repositorio
- evidencias documentales corporativas no presentes en este workspace

## Metodología

Se aplicaron estas técnicas:

- revisión estática de código y configuración
- análisis de middleware, autenticación y logging
- revisión de logs operativos y eventos de seguridad
- validación de endpoints HTTPS y cabeceras
- verificación de certificados TLS
- inspección no intrusiva de puertos expuestos desde el host
- ejecución del health check ML
- mapeo contra controles de ISO 27001, GDPR y PCI-DSS según aplicabilidad

## Infraestructura Tecnológica

### Stack principal

- PHP 8 + Composer + PDO MySQL
- Monolog, Guzzle, Redis, PHPMailer
- Nginx como front-end
- múltiples pools PHP-FPM presentes en el host
- Redis y Memcached locales
- Varnish presente
- cron jobs de integración y monitoreo

### Evidencias principales

- stack en `composer.json`
- bootstrap y políticas globales en `public/index.php`
- cabeceras y CSP en `app/Middleware/SecurityMiddleware.php`
- autenticación de API y sesión en `app/Middleware/ApiAuthMiddleware.php` y `app/Middleware/AuthMiddleware.php`
- logging estructurado en `app/Services/StructuredLogService.php`
- auditoría transaccional en `app/Services/AuditLogService.php`

### Exposición de servicios observada

Desde el host auditado se observaron, entre otros:

- `443/tcp` y `443/udp` expuestos por Nginx
- `8080/tcp` expuesto por Nginx
- `6081/tcp` expuesto por Varnish
- Redis, Memcached, MySQL y PHP-FPM principalmente ligados a `127.0.0.1`

Interpretación:

- la exposición local de Redis/Memcached/MySQL está razonablemente contenida
- la exposición pública de `8080` y `6081` requiere justificación operacional y endurecimiento

## Resultados de Validación Técnica

### TLS y cabeceras

Hallazgos positivos:

- `https://eskill.com.br/` responde por HTTPS
- certificado válido de Let's Encrypt
- HSTS habilitado
- `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `CSP`, `Permissions-Policy`, `COOP` y `CORP` presentes

### OAuth / Mercado Libre

Resultado del health check:

- `ok=false`
- `errors=3`
- `warnings=2`
- `1` cuenta ML detectada
- `403 UNAUTHORIZED` en `/users/me` para la cuenta auditada

Diagnóstico operativo detectado:

- `ML_APP_ID`, `ML_CLIENT_SECRET`, `ML_REDIRECT_URI` y `APP_KEY` con placeholder en el entorno auditado
- `ML_REDIRECT_URI` no apunta a `/auth/callback`

### Logs de seguridad y eventos

Resumen observado:

- `storage/logs/app.log`: `289937` líneas
- coincidencias de patrones de seguridad/errores en `app.log`: `31191`
- `storage/logs/tokens.log`: evidencia reciente de error de configuración OAuth
- `storage/logs/token_refresh.log`: evidencia histórica de `invalid_grant`
- `storage/logs/cron_questions.log`: evidencia histórica de proxy no resolvible

Interpretación:

- existe visibilidad operativa real
- también existe ruido de configuración que dificulta la señal útil

## Hallazgos Priorizados

### CRIT-01 — Configuración OAuth y APP_KEY inválidas en el entorno auditado

- **Severidad:** Crítica
- **Impacto:** interrupción de autenticación, fallo de vinculación OAuth, degradación de cifrado operativo
- **Evidencia:** health check ML reporta placeholders en `ML_APP_ID`, `ML_CLIENT_SECRET`, `ML_REDIRECT_URI` y `APP_KEY`
- **Riesgo:** pérdida de disponibilidad de integración y debilidad en manejo seguro de secretos
- **Recomendación:** sustituir placeholders por valores reales, validar `/auth/callback`, establecer `APP_KEY` robusta y bloquear despliegues con valores placeholder

### HIGH-01 — Secreto 2FA almacenado sin cifrado visible

- **Severidad:** Alta
- **Impacto:** compromiso de segundo factor si la base de datos es accedida por un actor no autorizado
- **Evidencia:** `UserService::enableTwoFactor()` persiste `two_factor_secret` en claro y `getTwoFactorSecret()` lo recupera directamente
- **Riesgo:** bypass parcial del modelo MFA ante exfiltración de DB
- **Recomendación:** cifrar `two_factor_secret` con `SecurityService` y rotar secretos existentes

### HIGH-02 — CORS abierto con `Access-Control-Allow-Origin: *` en OpenClaw

- **Severidad:** Alta
- **Impacto:** ampliación innecesaria de superficie de consumo cross-origin
- **Evidencia:** `public/index.php` usa `OPENCLAW_CORS_ORIGIN` con fallback `*`; prueba HTTP confirmó `access-control-allow-origin: *`
- **Riesgo:** abuso desde orígenes no autorizados y mayor exposición de APIs
- **Recomendación:** fijar allowlist explícita por entorno y revisar necesidad real de cada método/cabecera expuesta

### HIGH-03 — Exposición pública de puertos auxiliares

- **Severidad:** Alta
- **Impacto:** aumento de superficie de ataque de red
- **Evidencia:** listeners públicos en `0.0.0.0:8080` y `0.0.0.0:6081`
- **Riesgo:** acceso no previsto a servicios auxiliares, fingerprinting y abuso
- **Recomendación:** restringir por firewall o bind interno; justificar formalmente la exposición si es intencional

### HIGH-04 — Superficie de ejecución de comandos del sistema

- **Severidad:** Alta
- **Impacto:** riesgo de RCE indirecta si alguna ruta o parámetro quedara insuficientemente validado
- **Evidencia:** uso de `exec()`/`shell_exec()` en controladores y servicios operativos como `HealthController`, `OpenSpecController`, `BackupService`, `AIImageAnalyzerService`, `PdfExporter`
- **Riesgo:** escalada técnica en caso de bypass de validaciones
- **Recomendación:** inventariar todos los puntos, encapsular comandos, eliminar superficies innecesarias y aplicar allowlists estrictas

### HIGH-05 — Evidencia de errores de autenticación y proxy en producción operativa

- **Severidad:** Alta
- **Impacto:** pérdida de disponibilidad de integraciones y reconexiones forzadas
- **Evidencia:** `invalid_grant` en logs de refresh y `Could not resolve proxy: proxy.mercadolivre.com` en logs de cron
- **Riesgo:** interrupción recurrente de sincronización con Mercado Libre
- **Recomendación:** rotar credenciales afectadas, eliminar proxies heredados no válidos y monitorizar reconexiones fallidas

### MED-01 — Política de contraseñas inconsistente

- **Severidad:** Media
- **Impacto:** degradación del estándar de seguridad para credenciales actualizadas
- **Evidencia:** registro exige 8 caracteres y complejidad; cambio de contraseña acepta 6 caracteres
- **Riesgo:** usuarios con credenciales más débiles tras cambios posteriores
- **Recomendación:** unificar política mínima en todos los flujos

### MED-02 — Control de acceso disperso

- **Severidad:** Media
- **Impacto:** posibilidad de inconsistencias entre rutas, middleware y constructores
- **Evidencia:** validaciones repartidas entre `public/index.php`, middleware y controladores
- **Riesgo:** bypass accidental o comportamiento divergente entre superficies
- **Recomendación:** centralizar autorización por middleware/policies y reducir lógica ad hoc por controlador

### MED-03 — Invitación de usuarios con rol sin whitelist en el alta inicial

- **Severidad:** Media
- **Impacto:** integridad de RBAC y riesgo de asignaciones no previstas
- **Evidencia:** `UserManagementController::invite()` actualiza el rol recibido sin aplicar la whitelist usada por `updateRole()`
- **Riesgo:** incoherencia de roles y potencial ampliación de privilegios
- **Recomendación:** reutilizar la misma whitelist y validaciones del flujo `updateRole()`

### MED-04 — Exposición de mensajes internos al cliente en borrado de cuenta

- **Severidad:** Media
- **Impacto:** fuga de detalle interno de errores
- **Evidencia:** `AuthController::deleteAccount()` devuelve `"Erro ao excluir conta: " . $e->getMessage()`
- **Riesgo:** ayuda a reconocimiento técnico por un atacante autenticado
- **Recomendación:** registrar el detalle internamente y devolver mensaje genérico al cliente

### MED-05 — Deriva de configuración de base de datos

- **Severidad:** Media
- **Impacto:** ruido operativo y riesgo de errores por fallback no deseado
- **Evidencia:** `app.log` registra repetidamente `DB_DATABASE não definido, usando default: meli`
- **Riesgo:** incidentes por configuración parcial y pérdida de calidad de observabilidad
- **Recomendación:** normalizar variables de entorno y bloquear arranque con configuración DB incompleta en producción

### LOW-01 — Reutilización prolongada del token CSRF

- **Severidad:** Baja
- **Impacto:** menor robustez comparado con rotación más frecuente
- **Evidencia:** token con vigencia de 1 hora
- **Recomendación:** evaluar rotación por sesión regenerada o por operación sensible

### LOW-02 — Dependencia alta de logs de aplicación sin política formal visible de retención

- **Severidad:** Baja
- **Impacto:** crecimiento de almacenamiento y dificultad de análisis
- **Evidencia:** gran volumen de `app.log` y ausencia visible de política documental en el repo
- **Recomendación:** formalizar retención, rotación y clasificación de logs

## Evaluación de Cumplimiento

### ISO 27001

**Controles con evidencia parcial o positiva**

- gestión de acceso autenticado
- segregación básica de roles
- logging y trazabilidad
- cifrado de tokens sensibles
- hardening de cabeceras y transporte
- rate limiting y validación de webhooks

**Brechas relevantes**

- no hay evidencia suficiente en el repo de ISMS formal
- sin inventario documental de activos y clasificación
- sin SoA visible
- sin procedimiento documentado de gestión de vulnerabilidades
- sin runbook formal de respuesta a incidentes más allá de logs/scripts
- sin evidencia de revisiones periódicas de acceso y recertificación

**Estado estimado**

- **Parcialmente alineado**

### GDPR / privacidad

**Controles con evidencia parcial o positiva**

- exportación de datos del usuario
- borrado permanente de cuenta ML
- logging de auditoría
- cifrado de tokens sensibles

**Brechas relevantes**

- no se observa política formal de retención/minimización
- no se observa documentación de base legal/consentimiento
- no se observa registro de actividades de tratamiento
- no se observa inventario formal de subencargados
- no se observa anonimización sistemática de logs fuera del masking general

**Estado estimado**

- **Parcialmente alineado**

### PCI-DSS

**Aplicabilidad**

- no hay evidencia directa en el repositorio de almacenamiento o procesamiento propio de datos de tarjeta
- el canal comercial parece apoyarse principalmente en Mercado Libre

**Conclusión**

- **Aplicabilidad probable limitada o indirecta**
- aun así, deben validarse segmentación, contratos con terceros y ausencia total de CHD en logs, tablas y backups

## Pruebas Técnicas No Intrusivas

### Realizadas

- validación de cabeceras HTTPS
- validación de certificado TLS
- verificación de disponibilidad de endpoints ML y callback
- validación de autenticación del endpoint `/api/auth/oauth-config-status`
- verificación de CORS en `/api/openclaw`
- inspección de listeners locales
- ejecución del health check ML

### No realizadas

- explotación activa
- fuzzing agresivo
- escaneo intrusivo de red
- pruebas autenticadas profundas contra todos los módulos
- validación de dispositivos de red no accesibles desde este entorno

## Métricas Actuales

- **Postura general:** Alto riesgo
- **Health check ML:** 3 errores, 2 warnings
- **Cuentas ML detectadas:** 1
- **Cabeceras de seguridad HTTPS clave:** presentes
- **CORS OpenClaw:** wildcard activo
- **Logs app:** 289937 líneas
- **Eventos/patrones relevantes en app.log:** 31191 coincidencias aproximadas
- **Puertos públicos observados relevantes:** 443, 8080, 6081

## Plan de Acción Recomendado

### Inmediato — 0 a 7 días

1. reemplazar placeholders de `ML_APP_ID`, `ML_CLIENT_SECRET`, `ML_REDIRECT_URI` y `APP_KEY`
2. restringir CORS de OpenClaw a orígenes explícitos
3. revisar y cerrar `8080` y `6081` si no son estrictamente necesarios
4. eliminar proxies heredados inválidos en jobs y crons
5. rotar o reconectar cuentas afectadas por `invalid_grant`

### Corto plazo — 1 a 4 semanas

1. cifrar `two_factor_secret`
2. unificar política de contraseñas
3. corregir validación de roles en `invite()`
4. eliminar exposición de mensajes internos en respuestas
5. reducir ruido de logs y normalizar configuración DB

### Mediano plazo — 1 a 3 meses

1. centralizar autorización en políticas o middleware uniforme
2. inventariar y endurecer todos los usos de `exec()` y `shell_exec()`
3. formalizar retención de logs y datos
4. crear matriz formal de activos, riesgos y controles
5. definir gestión continua de vulnerabilidades y recertificación de accesos

## Mejora Continua Propuesta

- health check de seguridad diario con alertas
- revisión mensual de puertos expuestos
- revisión trimestral de roles y accesos privilegiados
- scanning SAST y revisión de secretos en CI
- revisión periódica de logs por categorías: auth, proxy, SSL, timeout, webhook, privilegios
- matriz de riesgos viva alineada a ISO 27001
- validación de privacidad y retención alineada a GDPR/LGPD en cada release relevante

## Conclusión

El sistema presenta una base técnica razonablemente madura en defensa de aplicación, autenticación, observabilidad y protección HTTP. No obstante, la combinación de errores de configuración sensibles, exposición de superficie auxiliar, debilidades en secretos 2FA y apertura CORS mantiene la postura global en **riesgo alto**.

La prioridad debe concentrarse primero en:

1. corregir configuración crítica de entorno
2. cerrar exposición innecesaria
3. endurecer datos sensibles y 2FA
4. formalizar controles de cumplimiento y operación continua
