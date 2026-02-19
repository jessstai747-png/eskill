# ✅ Credenciais do Mercado Livre Configuradas

## Status: ✅ Configurado

### Credenciais Configuradas

- **App ID:** `757032559637450`
- **Client Secret:** `Qq7AwTHymrP9m8L2CWAj0m2m1frhlL0m`
- **Redirect URI:** `http://localhost/eskill/public/auth/callback`

---

## 📋 Próximos Passos

### 1. Verificar URL de Callback no Portal ML

Certifique-se de que a URL de callback está configurada no portal do Mercado Livre Developers:

1. Acesse: [https://developers.mercadolivre.com.br](https://developers.mercadolivre.com.br)
2. Faça login com sua conta
3. Vá em "Minhas Aplicações"
4. Selecione a aplicação com ID `757032559637450`
5. Verifique/Configure a URL de callback:
   ```
   http://localhost/eskill/public/auth/callback
   ```

**Importante:** Se estiver usando um domínio diferente em produção, atualize a URL no portal ML e no arquivo `.env`.

---

### 2. Testar Vinculação de Conta

1. Acesse o sistema:
   ```
   http://localhost/eskill/public/
   ```

2. Faça login ou registre-se

3. Clique em "Vincular Conta ML" ou acesse:
   ```
   http://localhost/eskill/public/auth/authorize
   ```

4. Você será redirecionado para o Mercado Livre para autorizar o acesso

5. Após autorizar, você será redirecionado de volta ao sistema

---

### 3. Verificar Configuração

Execute o script de verificação:

```bash
php scripts/verify_ml_config.php
```

---

## 🔒 Segurança

⚠️ **IMPORTANTE:**

- Mantenha o arquivo `.env` seguro e nunca o compartilhe
- Não commite o arquivo `.env` no Git
- Em produção, use variáveis de ambiente do servidor
- Rotacione as credenciais periodicamente

---

## 📝 Configuração no .env

As seguintes variáveis foram configuradas no arquivo `.env`:

```env
ML_APP_ID=757032559637450
ML_CLIENT_SECRET=Qq7AwTHymrP9m8L2CWAj0m2m1frhlL0m
ML_REDIRECT_URI=http://localhost/eskill/public/auth/callback
```

---

## ✅ Status

- ✅ Credenciais configuradas no `.env`
- ✅ Sistema pronto para vincular contas ML
- ⚠️ Verificar URL de callback no portal ML

---

**Última atualização:** Credenciais configuradas com sucesso!
