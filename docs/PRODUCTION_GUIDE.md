# 🚀 Guia de Deploy em Produção - Mercado Livre Manager

Este guia detalha os passos finais para colocar o sistema em produção com segurança e performance.

## 📋 Status Atual (Atualizado: 21/12/2025)

| Componente | Status | Detalhes |
|------------|--------|----------|
| **Código** | ✅ Completo | 39 Controllers, 52 Services, 384 testes passando |
| **Banco de Dados** | ✅ Funcional | 38 tabelas, dados sincronizados |
| **SSL/HTTPS** | ✅ Configurado | Let's Encrypt |
| **Tokens ML** | ✅ Ativos | 2 contas, renovação automática |
| **CRON Jobs** | ✅ Configurados | 6 jobs ativos |
| **Items Sync** | ✅ 430 anúncios | DIVINOESPELHOS + PANTERAMOTOPEÇAS |
| **Orders Sync** | ✅ 50 pedidos | Sincronização a cada 15min |

### CRON Jobs Ativos
```
*/5 min   - scheduler.php        (Scheduler de tarefas)
*/15 min  - poll_orders.php      (Polling de pedidos)
*/4h      - renew_tokens.php     (Renovar tokens ML)
*/6h      - poll_items.php       (Sync anúncios)
1x/dia    - backup_daily.sh      (Backup diário 3am)
1x/hora   - monitor_system.php   (Monitoramento)
```

---

## 🛠️ Passos para Execução (Terminal)

Execute os comandos abaixo na ordem apresentada para finalizar a configuração.

### 1. Configurar Banco de Dados Seguro
O sistema atualmente usa o usuário `root`. É altamente recomendado criar um usuário dedicado.

```bash
./scripts/setup_database_production.sh
```
> **Nota:** Se este script falhar por permissões, certifique-se de que seu usuário `root` tem privilégios de `CREATE USER`. Caso contrário, mantenha as credenciais atuais mas garanta que a senha seja forte.

### 2. Otimizar PHP para Produção
Ajusta configurações do `php.ini` para performance e segurança (desativa exibição de erros, habilita logs, etc).

```bash
sudo ./scripts/configure_php_production.sh
```

### 3. Configurar Backup Automático
Configura o CRON para realizar backups periódicos do banco de dados.

```bash
./scripts/setup_cron_backup.sh
```
> Recomendação: Escolha a opção **1 (Diário às 02:00)**.

### 4. Verificar Permissões de Pastas
Garanta que o servidor web (www-data) tenha acesso às pastas de armazenamento.

```bash
sudo chown -R www-data:www-data storage/
sudo chmod -R 775 storage/
```

---

## 🔍 Verificação Final

Após executar os scripts, acesse a rota de diagnóstico para confirmar que tudo está verde:

```
https://eskill.com.br/diagnostic.php
```

## 🆘 Suporte

Se encontrar problemas durante o deploy:
1. Verifique os logs em `storage/logs/`
2. Consulte `TROUBLESHOOTING.md`
3. Restaure o backup se necessário: `./scripts/restore_backup.sh`
