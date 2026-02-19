# 📦 Clonador de Anúncios em Lote - Guia Rápido

## 🎯 Visão Geral

Sistema avançado para clonar anúncios do Mercado Livre de forma inteligente e em lote, com suporte a múltiplas contas, templates personalizáveis e ações automáticas pós-clone.

---

## 🚀 Como Usar

### 1️⃣ Acessar o Módulo

1. Faça login no sistema
2. No menu lateral, clique em **"Clonagem"** > **"Clonagem em Lote"**
3. Ou acesse diretamente: `/dashboard/catalog/clone/batch`

### 2️⃣ Selecionar Origem dos Anúncios

Você pode clonar anúncios de duas formas:

#### **Opção A: Por Seller ID (Vendedor)**
- Cole o **Seller ID** do vendedor (ex: `123456789`)
- O sistema listará automaticamente todos os anúncios ativos
- Use filtros para refinar (catálogo, marca, busca por título)

#### **Opção B: Por Lista de IDs**
- Cole uma lista de **Item IDs** separados por vírgula ou quebra de linha
- Exemplo: `MLB123456, MLB789012, MLB345678`
- Útil quando você já sabe quais anúncios quer clonar

### 3️⃣ Filtrar e Selecionar Anúncios

**Interface de 3 colunas:**

| **Coluna 1: Filtros** | **Coluna 2: Lista** | **Coluna 3: Selecionados** |
|----------------------|---------------------|---------------------------|
| 📊 Tipo de anúncio | ✅ Checkboxes | 📝 Resumo da seleção |
| 🏷️ Marcas (facets) | 🔍 Busca por título | 🎨 Template escolhido |
| 🔍 Busca rápida | 🖼️ Preview do item | ⚙️ Opções finais |

**Dicas:**
- Clique em uma marca para selecionar **todos os anúncios dessa marca**
- Use "Selecionar todos filtrados" para seleção em massa
- Busque por título para encontrar anúncios específicos

### 4️⃣ Escolher Template de Clonagem

Selecione um template pré-configurado ou crie um personalizado:

#### **Templates Padrão:**

| Template | Descrição | Quando usar |
|----------|-----------|-------------|
| 🔄 **Replicação Exata** | Copia tudo igual | Migração entre contas |
| 📦 **Dropshipping +30%** | Preço original + 30% | Revenda com margem |
| 🎯 **Competitivo AI** | Preço baseado em concorrência | Máxima competitividade |
| 🔍 **SEO Otimizado** | Otimização SEO automática | Melhor ranqueamento |
| 💎 **Premium +15%** | Preço + 15%, SEO + Tech Sheet | Produtos de alta qualidade |

#### **Personalizar Template:**
- Ajuste regras de **preço** (copiar, %, fixo)
- Configure **estoque** (copiar, zero, fixo)
- Adicione **prefixo/sufixo** no título
- Defina **status inicial** (pausado/ativo)
- Escolha **ações pós-clone**

### 5️⃣ Executar Dry-Run (Prévia)

**⚠️ IMPORTANTE: Sempre execute o Dry-Run primeiro!**

1. Clique em **"Executar Prévia"**
2. O sistema validará cada anúncio e mostrará:
   - ✅ **Pode clonar**: Anúncio válido
   - ⚠️ **Avisos**: Alertas (marca ausente, variações)
   - ❌ **Bloqueado**: Problemas críticos (título inválido, sem imagens)
3. Revise os resultados e remova itens problemáticos

### 6️⃣ Iniciar Clonagem em Lote

1. Selecione a **conta de destino** (onde os anúncios serão criados)
2. Confirme as configurações
3. Clique em **"Iniciar Clonagem"**
4. O sistema criará um **job assíncrono**
5. Acompanhe o progresso em tempo real

### 7️⃣ Acompanhar Progresso

**Dashboard de Status:**
- 📊 Total de itens
- ✅ Clonados com sucesso
- ❌ Falhas (com motivo)
- ⏭️ Ignorados (duplicados)
- ⏱️ Tempo estimado

**Atualização automática** a cada 5 segundos.

### 8️⃣ Ações Pós-Clone (Automáticas)

Dependendo do template escolhido, o sistema pode executar automaticamente:

| Ação | Descrição |
|------|-----------|
| 📋 **Tech Sheet** | Gera ficha técnica completa |
| 🔍 **SEO Optimize** | Aplica sugestões do SEO Killer |
| 💰 **Pricing Apply** | Ajusta preço baseado em inteligência |
| ✅ **Activate** | Ativa o anúncio automaticamente |

---

## 🎓 Exemplos de Uso

### Exemplo 1: Migrar Catálogo Entre Contas
1. **Template**: Replicação Exata
2. **Origem**: Sua conta principal (por Item IDs selecionados)
3. **Destino**: Conta secundária
4. **Ações**: Nenhuma (apenas clonar)

### Exemplo 2: Dropshipping de Concorrente
1. **Template**: Dropshipping +30%
2. **Origem**: Seller ID do concorrente
3. **Filtro**: Apenas catálogo + marca específica
4. **Destino**: Sua conta
5. **Ações**: SEO Optimize + Pricing Apply

### Exemplo 3: Lançamento de Linha Premium
1. **Template**: Premium +15%
2. **Origem**: Item IDs selecionados manualmente
3. **Destino**: Conta premium
4. **Ações**: Tech Sheet + SEO Optimize + Activate

---

## ⚠️ Dicas Importantes

### ✅ Boas Práticas
- ✅ Sempre execute **Dry-Run** antes de clonar
- ✅ Revise os anúncios antes de ativar
- ✅ Use templates apropriados para cada caso
- ✅ Monitore o progresso até conclusão
- ✅ Verifique os anúncios clonados no ML

### ❌ Evite
- ❌ Clonar sem validar (pode gerar falhas)
- ❌ Clonar de sellers sem autorização legal
- ❌ Ativar anúncios antes de revisar
- ❌ Ignorar erros sem investigar a causa
- ❌ Clonar itens duplicados

### 🔐 Segurança e Compliance
- O sistema **verifica permissões** antes de clonar
- **Respeita rate limits** da API do ML
- **Deduplica** automaticamente (evita cópias duplicadas)
- **Registra auditoria** completa de todas as operações

---

## 📊 Métricas e Relatórios

Acesse **"Métricas de Clonagem"** no menu para ver:
- Taxa de sucesso por template
- Erros mais comuns
- Performance ao longo do tempo
- Templates mais usados
- Tempo médio de clonagem

---

## 🆘 Troubleshooting

### ❓ "Job travado / não processa"
- Verifique se o cron está rodando
- Execute: `php bin/catalog-clone-worker.php --recover-stuck`

### ❓ "Erro ao buscar anúncios do seller"
- Verifique se o Seller ID está correto
- Seller pode ter bloqueado acesso via API
- Tente com Item IDs diretos

### ❓ "Anúncios clonados aparecem pausados"
- Comportamento normal (segurança)
- Use template com ação "Activate" ou ative manualmente

### ❓ "Falha ao clonar variações"
- Variações complexas podem falhar
- Clone sem variações e adicione manualmente depois

### ❓ "Descrição não foi clonada"
- Verifique se "Clone Description" está ativado no template
- Algumas descrições podem ser bloqueadas pelo ML

---

## 📞 Suporte

- 📧 Email: suporte@eskill.com.br
- 📖 Documentação completa: `/docs/catalog-clone`
- 🐛 Reportar bug: GitHub Issues

---

**Versão:** 2.0.0 (Janeiro 2026)  
**Última atualização:** 31/01/2026
