# 📑 Auth Failure Monitor - Índice de Documentação

Navegação rápida para todos os recursos do Auth Failure Monitor.

## 🚀 Começar Agora

**[QUICK_START_AUTH_MONITOR.md](QUICK_START_AUTH_MONITOR.md)** - Guia de início rápido (5 minutos)
- Setup em 5 passos
- Comandos essenciais
- Troubleshooting rápido
- Checklist de produção

## 📚 Documentação Completa

**[docs/AUTH_MONITOR_README.md](docs/AUTH_MONITOR_README.md)** - Documentação técnica completa
- Funcionalidades detalhadas
- Referência de configuração
- Estrutura do banco de dados
- Padrões de detecção
- Exemplos de uso
- Boas práticas
- Integração com firewall

## 📋 Informações Técnicas

**[AUTH_MONITOR_IMPLEMENTATION.md](AUTH_MONITOR_IMPLEMENTATION.md)** - Resumo da implementação
- Funcionalidades implementadas
- Estrutura do código
- Classes e métodos
- Destaques técnicos
- Status da implementação

## 💻 Código-Fonte

**[bin/monitor-auth-failures.php](bin/monitor-auth-failures.php)** - Script principal (832 linhas)
- Interface CLI completa
- Análise de logs
- Bloqueio de IPs
- Sistema de alertas
- Relatórios JSON

**[bin/test-auth-monitor.php](bin/test-auth-monitor.php)** - Script de teste
- Cria logs simulados
- Executa monitor em dry-run
- Valida funcionamento

## ⚙️ Configuração

**[.env.auth-monitor.example](.env.auth-monitor.example)** - Exemplo de configuração
- Todas as variáveis disponíveis
- Valores padrão
- Comentários explicativos

**[crontab.auth-monitor.example](crontab.auth-monitor.example)** - Exemplos de cron
- Diferentes frequências
- Configuração de rotação de logs
- Instruções de instalação

## 📊 Uso Rápido

### Comandos Principais

```bash
# Ver ajuda
php bin/monitor-auth-failures.php --help

# Testar sem fazer alterações
php bin/monitor-auth-failures.php --dry-run --verbose

# Executar em produção
php bin/monitor-auth-failures.php

# Executar teste completo
php bin/test-auth-monitor.php
```

### Consultas SQL Úteis

```sql
-- Ver IPs bloqueados
SELECT * FROM auth_blocked_ips WHERE expires_at > NOW();

-- Ver últimas falhas
SELECT ip_address, COUNT(*) as total 
FROM auth_failure_log 
WHERE detected_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY ip_address 
ORDER BY total DESC;
```

## 🔗 Links Externos

- [PHPMailer](https://github.com/PHPMailer/PHPMailer) - Biblioteca de e-mail
- [PHP dotenv](https://github.com/vlucas/phpdotenv) - Gerenciamento de variáveis de ambiente
- [PDO Documentation](https://www.php.net/manual/en/book.pdo.php) - Documentação do PDO

## 📞 Suporte

### Verificar Logs
```bash
tail -f /var/log/auth-monitor.log
```

### Verificar Cron
```bash
crontab -l
grep CRON /var/log/syslog
```

### Validar Sintaxe
```bash
php -l bin/monitor-auth-failures.php
```

## ✅ Checklist de Implementação

- [x] Script principal criado (832 linhas)
- [x] Interface CLI completa
- [x] Análise de logs (texto + JSON)
- [x] Banco de dados PDO
- [x] Sistema de bloqueio
- [x] Alertas por e-mail
- [x] Relatórios JSON
- [x] Script de teste
- [x] Documentação completa
- [x] Exemplos de configuração
- [x] Guia de início rápido

## 🎯 Próximos Passos

1. ✅ Leia o [Quick Start](QUICK_START_AUTH_MONITOR.md)
2. ✅ Configure o `.env`
3. ✅ Execute o teste
4. ✅ Configure o cron
5. ✅ Monitore os logs

---

**Versão**: 1.0.0  
**Data**: 25/01/2026  
**Status**: ✅ Pronto para Produção
