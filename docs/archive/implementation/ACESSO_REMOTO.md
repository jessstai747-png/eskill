# 🔐 Acesso Remoto SSH - Servidor eSkill

## 📋 Credenciais do Servidor

| Campo | Valor |
|-------|-------|
| **URL Principal** | `ftp.eskill.com.br` |
| **IP** | `191.252.83.235` |
| **Usuário** | `eskill1` |
| **Senha** | `Tr1unf0@Tr1unf0@` |
| **Porta** | `22` |

---

## ⚡ Conexão Rápida

### Opção 1: Usando o Script
Execute o script de conexão:
```cmd
connect_ssh.bat
```

### Opção 2: Terminal
```cmd
ssh -o MACs=hmac-sha2-512,hmac-sha2-256,hmac-sha1 eskill1@ftp.eskill.com.br
```

### Opção 3: Após Configurar SSH Config
Depois de copiar o template para `%USERPROFILE%\.ssh\config`:
```cmd
ssh eskill
```

---

## 🧪 Testar Conexão

Execute o script de teste:
```cmd
test_ssh.bat
```

Este script irá:
1. ✅ Verificar conectividade com o servidor
2. ✅ Verificar se a chave SSH existe
3. ✅ Testar a autenticação SSH
4. ✅ Exibir informações do servidor (hostname, uptime)

---

## 🚀 Configurar Acesso Permanente

Para não precisar digitar parâmetros toda vez:

1. Copie o conteúdo do arquivo `ssh_config_template`
2. Cole em: `%USERPROFILE%\.ssh\config`
3. Pronto! Agora basta usar: `ssh eskill`

### O que está configurado:
- ✅ Algoritmos MAC compatíveis com o servidor
- ✅ KeepAlive automático (60s)
- ✅ Timeout de conexão (10s)
- ✅ Chave SSH automática (se existir)

> **Nota:** Você precisará digitar a senha ao conectar.

---

## 🔌 Conectar ao Servidor

### Usando o script:

```cmd
connect_ssh.bat
```

### Ou diretamente:

```cmd
ssh -o MACs=hmac-sha2-512,hmac-sha2-256,hmac-sha1 eskill1@ftp.eskill.com.br
```

### Após configurar SSH config:

```cmd
ssh eskill
```

---

## 🛠️ Configuração Manual (Se necessário)

### 1. Gerar chave SSH

```powershell
ssh-keygen -t rsa -b 4096 -f "$env:USERPROFILE\.ssh\id_rsa_eskill" -C "eskill-server" -N '""'
```

### 2. Copiar chave para o servidor

```powershell
type "$env:USERPROFILE\.ssh\id_rsa_eskill.pub" | ssh -o MACs=hmac-sha2-512,hmac-sha2-256,hmac-sha1 eskill1@ftp.eskill.com.br "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys"
```

### 3. Configurar SSH config

Copie o conteúdo de `ssh_config_template` para `%USERPROFILE%\.ssh\config` ou adicione manualmente:

```
Host eskill
    HostName ftp.eskill.com.br
    User eskill1
    Port 22
    IdentityFile ~/.ssh/id_rsa_eskill
    MACs hmac-sha2-512,hmac-sha2-256,hmac-sha1
    ServerAliveInterval 60
    ServerAliveCountMax 3
    ConnectTimeout 10
```

---

## 📁 Arquivos de Configuração

| Arquivo | Caminho |
|---------|---------|
| Chave Privada | `~/.ssh/id_rsa_eskill` |
| Chave Pública | `~/.ssh/id_rsa_eskill.pub` |
| Config SSH | `~/.ssh/config` |
| Info Salva | `~/.ssh/eskill_ssh_info.txt` |

---

## 🔧 Troubleshooting

### Conexão ainda pede senha

1. Verifique se a chave pública está no servidor:
   ```bash
   ssh eskill1@ftp.eskill.com.br "cat ~/.ssh/authorized_keys"
   ```

2. Verifique permissões no servidor:
   ```bash
   ssh eskill1@ftp.eskill.com.br "chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys"
   ```

3. Teste conexão em modo verbose:
   ```powershell
   ssh -v eskill
   ```

### Erro de permissão na chave privada

```powershell
icacls "$env:USERPROFILE\.ssh\id_rsa_eskill" /inheritance:r /grant:r "$env:USERNAME:R"
```

### Reconfigurar do zero

```powershell
Remove-Item "$env:USERPROFILE\.ssh\id_rsa_eskill*" -Force
# Gere nova chave e configure manualmente conforme instruções acima
```

---

## 📚 Comandos Úteis

| Comando | Descrição |
|---------|-----------|
| `ssh eskill` | Conectar ao servidor |
| `ssh eskill "comando"` | Executar comando remoto |
| `scp arquivo eskill:~/` | Copiar arquivo para servidor |
| `scp eskill:~/arquivo .` | Baixar arquivo do servidor |
| `sftp eskill` | Abrir SFTP interativo |

### Exemplos:

```powershell
# Ver espaço em disco
ssh eskill "df -h"

# Ver processos
ssh eskill "ps aux"

# Copiar arquivo para o servidor
scp arquivo.txt eskill:~/

# Copiar pasta inteira
scp -r pasta/ eskill:~/

# Baixar logs
scp eskill:~/logs/*.log ./logs-backup/
```

---

## ✅ Status

✅ **Servidor:** ftp.eskill.com.br (191.252.83.235)
✅ **Usuário:** eskill1
✅ **Conexão:** Funcionando
✅ **Chave SSH:** Configurada
✅ **Scripts:** connect_ssh.bat, test_ssh.bat

### Arquivos Importantes:
- `connect_ssh.bat` - Conectar ao servidor
- `test_ssh.bat` - Testar conexão
- `ssh_config_template` - Template de configuração
- `SSH_README.md` - Documentação completa

---

*Última atualização: 16 de Dezembro de 2025*
