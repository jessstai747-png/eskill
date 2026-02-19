# 💾 Sistema de Backup Automatizado

## 🎯 Visão Geral

Sistema completo de backup para o Mercado Livre Manager, incluindo backup automático, restauração e monitoramento.

## 📦 Componentes

### Scripts Principais

| Script | Função |
|--------|--------|
| `backup_system.sh` | Execução do backup completo |
| `restore_backup.sh` | Restauração interativa ou via CLI |
| `setup_cron_backup.sh` | Configuração de backup automático |

### O Que É Backupeado

✅ **Banco de Dados**
- Dump completo com estrutura e dados
- Procedures e triggers incluídos
- Compressão opcional

✅ **Arquivos Críticos**
- `.env` (configurações)
- `app/` (código da aplicação)
- `config/` (configurações)
- `database/migrations/` (migrações)
- `public/` (assets públicos)
- `storage/logs/` (logs da aplicação)

✅ **Logs do Sistema**
- Logs da aplicação
- Logs do PHP (se configurado)
- Histórico de backup

## 🚀 Uso Rápido

### Backup Manual
```bash
# Backup completo
./scripts/backup_system.sh

# Verificar último backup
ls -la /backup/mercadolivre/
```

### Configurar Backup Automático
```bash
# Configuração interativa
./scripts/setup_cron_backup.sh

# Ver configurações atuais
crontab -l
```

### Restaurar Backup
```bash
# Menu interativo
./scripts/restore_backup.sh

# Restaurar banco específico
./scripts/restore_backup.sh db /backup/mercadolivre/database/db_20241217_080000.sql.gz

# Listar backups disponíveis
./scripts/restore_backup.sh list
```

## ⚙️ Configuração

### Variáveis de Ambiente (.env)

```env
# Configurações de Backup
BACKUP_PATH=/backup/mercadolivre
BACKUP_RETENTION_DAYS=30
BACKUP_COMPRESS=true

# Notificações (opcional)
TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id
```

### Estrutura de Diretórios

```
/backup/mercadolivre/
├── database/           # Backups do banco
│   ├── db_20241217_080000.sql.gz
│   └── db_20241216_080000.sql.gz
├── files/             # Backups de arquivos
│   ├── files_20241217_080000.tar.gz
│   └── files_20241216_080000.tar.gz
└── logs/              # Logs de backup e relatórios
    ├── backup_report_20241217_080000.txt
    └── logs_20241217.tar.gz
```

## 🔧 Configuração Detalhada

### 1. Preparar Sistema

```bash
# Criar diretórios de backup
sudo mkdir -p /backup/mercadolivre
sudo chown $(whoami):$(whoami) /backup/mercadolivre

# Instalar dependências (se necessário)
sudo apt install gzip tar mysql-client
```

### 2. Configurar Backup Automático

```bash
# Executar configurador
./scripts/setup_cron_backup.sh

# Opções disponíveis:
# 1. Diário às 02:00
# 2. Diário às 03:30
# 3. Duas vezes ao dia
# 4. Semanal
# 5. Personalizado
```

### 3. Testar Configuração

```bash
# Backup manual para teste
./scripts/backup_system.sh

# Verificar arquivos criados
ls -la /backup/mercadolivre/

# Verificar integridade
./scripts/restore_backup.sh verify /backup/mercadolivre/database/latest_backup.sql.gz
```

## 📊 Monitoramento

### Logs de Backup

```bash
# Ver log em tempo real
tail -f /var/log/ml_backup.log

# Ver último relatório
cat /backup/mercadolivre/logs/backup_report_*.txt | tail -1
```

### Verificar Status do CRON

```bash
# Ver configuração atual
crontab -l

# Status do serviço
sudo systemctl status cron

# Log do sistema
sudo tail -f /var/log/syslog | grep CRON
```

## 🔄 Processo de Restauração

### Restauração Automática de Banco

```bash
./scripts/restore_backup.sh db /caminho/do/backup.sql.gz
```

### Restauração Manual de Arquivos

```bash
# Extrair em diretório temporário primeiro
mkdir /tmp/restore_preview
cd /tmp/restore_preview
tar -xzf /backup/mercadolivre/files/backup.tar.gz

# Verificar conteúdo antes de restaurar
ls -la

# Restaurar se estiver correto
./scripts/restore_backup.sh files /backup/mercadolivre/files/backup.tar.gz
```

## 🚨 Segurança e Boas Práticas

### Segurança dos Backups

✅ **Permissões Seguras**
```bash
# Apenas o proprietário pode ler
chmod 600 /backup/mercadolivre/database/*.sql*
chmod 600 /backup/mercadolivre/files/*.tar*
```

✅ **Backup Off-site**
```bash
# Sincronizar com servidor remoto
rsync -avz /backup/mercadolivre/ user@remote-server:/backups/ml/

# Configurar no CRON após backup local
0 4 * * * rsync -avz /backup/mercadolivre/ backup@remote:/backups/
```

✅ **Criptografia** (Para dados sensíveis)
```bash
# Criptografar backup antes de armazenar
gpg --symmetric --cipher-algo AES256 backup.sql
```

### Testagem Regular

```bash
# Script de teste mensal (adicionar ao CRON)
#!/bin/bash
# Teste de restauração em ambiente isolado
# 0 0 1 * * /scripts/test_restore.sh
```

## 📋 Troubleshooting

### Problemas Comuns

**1. Erro de Permissão**
```bash
# Verificar proprietário
ls -la /backup/mercadolivre/

# Corrigir se necessário
sudo chown -R $(whoami):$(whoami) /backup/mercadolivre/
```

**2. Espaço Insuficiente**
```bash
# Verificar espaço
df -h /backup/

# Limpar backups antigos manualmente
find /backup/mercadolivre/ -name "*.sql*" -mtime +30 -delete
```

**3. Backup Não Executa**
```bash
# Verificar CRON
crontab -l

# Verificar se cron está rodando
sudo systemctl status cron

# Testar comando manual
/home/user/scripts/backup_system.sh
```

**4. Falha na Conexão do Banco**
```bash
# Testar conexão manual
mysql -h$DB_HOST -u$DB_USER -p$DB_PASS $DB_NAME

# Verificar .env
grep "DB_" .env
```

## 📈 Métricas e Relatórios

### Relatório de Backup Automático

Cada backup gera um relatório em `/backup/mercadolivre/logs/backup_report_TIMESTAMP.txt` contendo:

- Configurações utilizadas
- Tempo de execução
- Arquivos criados
- Espaço utilizado
- Status de cada etapa

### Monitoramento com Telegram

Configure notificações para receber status dos backups:

```env
TELEGRAM_ENABLED=true
TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
TELEGRAM_CHAT_ID=123456789
```

## 🔄 Migração e Sincronização

### Migrar para Novo Servidor

```bash
# 1. Backup completo no servidor antigo
./scripts/backup_system.sh

# 2. Transferir backups
scp -r /backup/mercadolivre/ user@new-server:/backup/

# 3. Restaurar no servidor novo
./scripts/restore_backup.sh db /backup/mercadolivre/database/latest.sql.gz
./scripts/restore_backup.sh files /backup/mercadolivre/files/latest.tar.gz
```

### Sincronização entre Ambientes

```bash
# Copiar backup de produção para desenvolvimento (cuidado com dados sensíveis!)
./scripts/restore_backup.sh db /backup/production/database/latest.sql.gz
```

---

## 🎯 Checklist de Implementação

- [ ] Scripts de backup executáveis
- [ ] Diretório de backup criado com permissões corretas
- [ ] Variáveis configuradas no .env
- [ ] CRON configurado para execução automática
- [ ] Teste de backup manual realizado
- [ ] Teste de restauração realizado
- [ ] Monitoramento configurado (logs/notificações)
- [ ] Documentação da equipe atualizada
- [ ] Backup off-site configurado (produção)

**Status:** ✅ Sistema de backup completo e pronto para produção!