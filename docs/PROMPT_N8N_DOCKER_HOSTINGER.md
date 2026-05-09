# Prompt — Setup n8n Self-Hosted com Docker no Hostinger VPS

## Contexto

Preciso instalar e configurar o **n8n** (ferramenta de automação de workflows) em um
servidor **Hostinger VPS** com acesso SSH, usando Docker e Docker Compose.

O n8n vai ser usado para orquestrar automações com a API do Mercado Livre (MLB),
consumindo endpoints de uma API PHP interna (eskill.com.br).

---

## Pré-requisitos a verificar primeiro

Antes de qualquer instalação, verifique no servidor:

```bash
# Verificar SO
cat /etc/os-release

# Verificar se Docker já está instalado
docker --version

# Verificar se Docker Compose está instalado
docker compose version

# Verificar espaço em disco
df -h

# Verificar RAM disponível
free -h

# Verificar se porta 5678 está livre
ss -tlnp | grep 5678
```

---

## PASSO 1 — Instalar Docker (se não estiver instalado)

```bash
# Atualizar pacotes
apt update && apt upgrade -y

# Instalar dependências
apt install -y ca-certificates curl gnupg lsb-release

# Adicionar chave GPG oficial do Docker
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
chmod a+r /etc/apt/keyrings/docker.gpg

# Adicionar repositório Docker
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | tee /etc/apt/sources.list.d/docker.list > /dev/null

# Instalar Docker Engine + Compose
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Habilitar Docker no boot
systemctl enable docker
systemctl start docker

# Testar
docker run hello-world
```

---

## PASSO 2 — Criar estrutura de diretórios

```bash
# Criar pasta principal do n8n
mkdir -p /opt/n8n
mkdir -p /opt/n8n/data        # dados persistentes do n8n
mkdir -p /opt/n8n/backups     # backups de workflows

cd /opt/n8n
```

---

## PASSO 3 — Criar arquivo .env

Crie o arquivo `/opt/n8n/.env` com o seguinte conteúdo
(substitua os valores entre < > pelos reais):

```env
# ── n8n Core ────────────────────────────────────────────────
N8N_HOST=<IP_DO_SEU_VPS_OU_DOMINIO>
N8N_PORT=5678
N8N_PROTOCOL=http
WEBHOOK_URL=http://<IP_DO_SEU_VPS_OU_DOMINIO>:5678/

# ── Autenticação básica (protege o painel) ──────────────────
N8N_BASIC_AUTH_ACTIVE=true
N8N_BASIC_AUTH_USER=admin
N8N_BASIC_AUTH_PASSWORD=<SENHA_FORTE_AQUI>

# ── Timezone ────────────────────────────────────────────────
GENERIC_TIMEZONE=America/Sao_Paulo
TZ=America/Sao_Paulo

# ── Persistência de dados ───────────────────────────────────
N8N_USER_FOLDER=/home/node/.n8n

# ── Limpeza automática de execuções (evita disco cheio) ─────
EXECUTIONS_DATA_PRUNE=true
EXECUTIONS_DATA_MAX_AGE=168        # manter 7 dias (em horas)
EXECUTIONS_DATA_SAVE_ON_ERROR=all
EXECUTIONS_DATA_SAVE_ON_SUCCESS=last
EXECUTIONS_DATA_SAVE_MANUAL_EXECUTIONS=true

# ── Mercado Livre ────────────────────────────────────────────
ML_ACCESS_TOKEN=<SEU_TOKEN_OAUTH2_ML>

# ── eskill API ───────────────────────────────────────────────
ESKILL_API_URL=https://eskill.com.br
ESKILL_API_KEY=<SUA_CHAVE_API_INTERNA>

# ── Google Sheets (preencher após conectar o Google) ─────────
GOOGLE_SHEET_ID=<ID_DA_SUA_PLANILHA>
```

---

## PASSO 4 — Criar docker-compose.yml

Crie o arquivo `/opt/n8n/docker-compose.yml`:

```yaml
version: "3.8"

services:
  n8n:
    image: n8nio/n8n:latest
    container_name: n8n
    restart: unless-stopped
    ports:
      - "5678:5678"
    environment:
      - N8N_HOST=${N8N_HOST}
      - N8N_PORT=${N8N_PORT}
      - N8N_PROTOCOL=${N8N_PROTOCOL}
      - WEBHOOK_URL=${WEBHOOK_URL}
      - N8N_BASIC_AUTH_ACTIVE=${N8N_BASIC_AUTH_ACTIVE}
      - N8N_BASIC_AUTH_USER=${N8N_BASIC_AUTH_USER}
      - N8N_BASIC_AUTH_PASSWORD=${N8N_BASIC_AUTH_PASSWORD}
      - GENERIC_TIMEZONE=${GENERIC_TIMEZONE}
      - TZ=${TZ}
      - N8N_USER_FOLDER=${N8N_USER_FOLDER}
      - EXECUTIONS_DATA_PRUNE=${EXECUTIONS_DATA_PRUNE}
      - EXECUTIONS_DATA_MAX_AGE=${EXECUTIONS_DATA_MAX_AGE}
      - EXECUTIONS_DATA_SAVE_ON_ERROR=${EXECUTIONS_DATA_SAVE_ON_ERROR}
      - EXECUTIONS_DATA_SAVE_ON_SUCCESS=${EXECUTIONS_DATA_SAVE_ON_SUCCESS}
      - EXECUTIONS_DATA_SAVE_MANUAL_EXECUTIONS=${EXECUTIONS_DATA_SAVE_MANUAL_EXECUTIONS}
      # Variáveis customizadas para os workflows
      - ML_ACCESS_TOKEN=${ML_ACCESS_TOKEN}
      - ESKILL_API_URL=${ESKILL_API_URL}
      - ESKILL_API_KEY=${ESKILL_API_KEY}
      - GOOGLE_SHEET_ID=${GOOGLE_SHEET_ID}
    volumes:
      - ./data:/home/node/.n8n
      - ./backups:/backups
    healthcheck:
      test: ["CMD", "wget", "--spider", "-q", "http://localhost:5678/healthz"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 30s
```

---

## PASSO 5 — Subir o n8n

```bash
cd /opt/n8n

# Subir em background
docker compose up -d

# Verificar se subiu corretamente
docker compose ps

# Ver logs em tempo real
docker compose logs -f n8n
```

Aguarde aparecer no log:
```
n8n ready on 0.0.0.0, port 5678
```

---

## PASSO 6 — Liberar porta no firewall

```bash
# Se usar UFW
ufw allow 5678/tcp
ufw status

# Se usar iptables
iptables -A INPUT -p tcp --dport 5678 -j ACCEPT
```

---

## PASSO 7 — Acessar o painel

Abra no navegador:
```
http://<IP_DO_SEU_VPS>:5678
```

Login com as credenciais do `.env`:
- Usuário: `admin`
- Senha: `<SENHA_FORTE_AQUI>`

---

## PASSO 8 — Importar o workflow AWA

1. No painel n8n: menu lateral → **Workflows**
2. Botão `+` → **Import from file**
3. Selecione o arquivo `n8n_awa_anuncios.json`
4. O workflow vai aparecer com todos os nodes
5. Clique em **Save** e depois **Activate** (toggle no canto superior direito)

---

## PASSO 9 — Configurar credencial Google Sheets (opcional)

1. Menu lateral → **Credentials** → **Add Credential**
2. Buscar: `Google Sheets OAuth2`
3. Seguir o fluxo de autenticação Google
4. Após autenticar, voltar ao workflow e vincular a credencial no node **Google Sheets - Upsert**

---

## PASSO 10 — Comandos úteis de manutenção

```bash
# Parar o n8n
docker compose -f /opt/n8n/docker-compose.yml down

# Reiniciar
docker compose -f /opt/n8n/docker-compose.yml restart

# Atualizar para versão mais recente
docker compose -f /opt/n8n/docker-compose.yml pull
docker compose -f /opt/n8n/docker-compose.yml up -d

# Backup manual dos workflows
docker exec n8n n8n export:workflow --all --output=/backups/workflows_$(date +%Y%m%d).json

# Ver uso de recursos
docker stats n8n
```

---

## Troubleshooting comum

| Problema | Solução |
|----------|---------|
| Painel não abre no browser | Verificar se porta 5678 está liberada no firewall do Hostinger |
| `Permission denied` na pasta data | `chown -R 1000:1000 /opt/n8n/data` |
| Container reinicia sozinho | `docker compose logs n8n` para ver o erro |
| Workflow não dispara no cron | Verificar timezone — deve ser `America/Sao_Paulo` |
| Erro de conexão com eskill API | Verificar se `ESKILL_API_URL` está acessível pelo VPS |

---

## Validação final

Após tudo configurado, testar o workflow manualmente:

1. Abrir o workflow `AWA - Busca Anúncios Mercado Livre`
2. Clicar em **Execute Workflow** (botão play)
3. Verificar se os nodes executam em sequência sem erro
4. Checar se os dados aparecem na tabela `ml_anuncios_awa` do MySQL do eskill
5. Checar se a planilha Google Sheets foi preenchida

Resultado esperado no node **Sincronizar no Eskill (MySQL)**:
```json
{
  "success": true,
  "inserted": <N>,
  "updated": <N>,
  "total": <N>
}
```
