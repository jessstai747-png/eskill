# 🔐 Conexão SSH - Servidor eSkill

## 📋 Informações de Conexão
- **Host:** ftp.eskill.com.br (191.252.83.235)
- **Usuário:** eskill1
- **Senha:** Tr1unf0@Tr1unf0@
- **Porta:** 22

## 🚀 Como Conectar

### Método 1: Script Rápido (Recomendado)
```cmd
connect_ssh.bat
```

### Método 2: Teste de Conexão
```cmd
test_ssh.bat
```

### Método 3: Comando Direto
```cmd
ssh -o MACs=hmac-sha2-512,hmac-sha2-256,hmac-sha1 eskill1@ftp.eskill.com.br
```

## ⚙️ Configuração Permanente

Para não precisar digitar os parâmetros toda vez, copie o conteúdo de `ssh_config_template` para:
```
%USERPROFILE%\.ssh\config
```

Depois disso, basta digitar:
```cmd
ssh eskill
```

## ✅ Status da Conexão

✅ Servidor acessível
✅ Chave SSH configurada
✅ Autenticação funcionando
✅ Algoritmos MAC compatíveis

## 📝 Arquivos Importantes

- `connect_ssh.bat` - Conectar ao servidor
- `test_ssh.bat` - Testar conexão
- `ssh_config_template` - Template de configuração SSH
