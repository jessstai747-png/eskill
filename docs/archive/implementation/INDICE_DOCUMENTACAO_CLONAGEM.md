# 📚 Índice de Documentação - Clonador de Anúncios em Lote

**Versão:** 2.0.0  
**Última atualização:** 31/01/2026

---

## 🎯 Começar Aqui

Se você está começando agora com o módulo de clonagem, siga esta ordem:

1. 📖 **[Guia do Usuário](GUIA_CLONAGEM_LOTE.md)** - Como usar o sistema
2. ✅ **[Checklist de Deploy](CHECKLIST_DEPLOY_CLONAGEM.md)** - Para deploy em produção
3. 🔧 **[Troubleshooting](TROUBLESHOOTING_CLONAGEM.md)** - Quando algo der errado
4. 📊 **[Relatório Executivo](RELATORIO_CLONAGEM_LOTE.md)** - Visão técnica completa

---

## 📑 Documentos Disponíveis

### Para Usuários Finais

#### 📖 [Guia de Uso - Clonagem em Lote](GUIA_CLONAGEM_LOTE.md)
**Público:** Usuários, Operadores, Gerentes  
**Tempo de leitura:** ~15 minutos  
**Conteúdo:**
- Visão geral do sistema
- Como usar passo a passo
- Interface de 3 colunas
- Templates disponíveis
- Exemplos práticos
- Dicas importantes
- FAQ básico

**Quando ler:**
- ✅ Primeira vez usando o sistema
- ✅ Precisa clonar anúncios
- ✅ Dúvidas sobre funcionalidades

---

### Para Equipe Técnica

#### ✅ [Checklist de Deploy](CHECKLIST_DEPLOY_CLONAGEM.md)
**Público:** DevOps, Desenvolvedores, SysAdmin  
**Tempo de leitura:** ~10 minutos  
**Conteúdo:**
- Pré-requisitos de ambiente
- Procedimentos de deploy
- 5 testes pós-deploy
- Configuração de cron
- Monitoramento inicial
- Plano de rollback

**Quando usar:**
- ✅ Antes de fazer deploy
- ✅ Configurando ambiente novo
- ✅ Validando produção

#### 🔧 [Guia de Troubleshooting](TROUBLESHOOTING_CLONAGEM.md)
**Público:** Suporte Técnico, Desenvolvedores  
**Tempo de leitura:** ~20 minutos  
**Conteúdo:**
- 10 problemas comuns resolvidos
- Jobs travados e recovery
- Erros de API (429, 400, 422)
- Performance lenta
- Comandos SQL úteis
- Scripts de diagnóstico

**Quando consultar:**
- ✅ Algo não está funcionando
- ✅ Jobs não processam
- ✅ Erros inesperados
- ✅ Performance ruim

#### 📊 [Relatório Executivo](RELATORIO_CLONAGEM_LOTE.md)
**Público:** Arquitetos, Tech Leads, Gestores  
**Tempo de leitura:** ~25 minutos  
**Conteúdo:**
- Resumo executivo
- Arquitetura completa
- Métricas de qualidade
- Estrutura de código
- Casos de uso
- Roadmap futuro
- SLA e suporte

**Quando ler:**
- ✅ Entender arquitetura
- ✅ Decisões técnicas
- ✅ Planejamento de melhorias
- ✅ Onboarding de novos devs

#### 📝 [Sumário da Sessão](SUMARIO_SESSAO_CLONAGEM.md)
**Público:** Equipe de Desenvolvimento  
**Tempo de leitura:** ~5 minutos  
**Conteúdo:**
- O que foi implementado
- Análises de qualidade
- Documentação criada
- Status final
- Próximos passos

**Quando ler:**
- ✅ Ver resumo do trabalho
- ✅ Status do projeto
- ✅ O que foi entregue

---

## 🛠️ Scripts e Configurações

### Scripts Disponíveis

#### 🔍 [bin/clone-diagnostics.sh](../bin/clone-diagnostics.sh)
**Descrição:** Script automatizado de diagnóstico completo  
**Uso:**
```bash
bash bin/clone-diagnostics.sh
```
**Saída:**
- ✅ Status de ambiente
- ✅ Verificação de arquivos
- ✅ Permissões
- ✅ Banco de dados
- ✅ Jobs ativos
- ✅ Logs recentes
- ✅ Teste de workers

**Quando executar:**
- ✅ Após deploy
- ✅ Troubleshooting
- ✅ Health check periódico

#### 🤖 [bin/catalog-clone-worker.php](../bin/catalog-clone-worker.php)
**Descrição:** Worker principal de processamento de jobs  
**Uso:**
```bash
# Processar jobs pendentes (uma vez)
php bin/catalog-clone-worker.php --once

# Modo verbose (debug)
php bin/catalog-clone-worker.php --once --verbose

# Processar job específico
php bin/catalog-clone-worker.php --job=abc123

# Recuperar jobs travados
php bin/catalog-clone-worker.php --recover-stuck
```

#### 🎯 [bin/clone-post-actions-worker.php](../bin/clone-post-actions-worker.php)
**Descrição:** Worker de ações pós-clone (Tech Sheet, SEO, etc)  
**Uso:**
```bash
# Processar ações pendentes
php bin/clone-post-actions-worker.php --once

# Processar item específico
php bin/clone-post-actions-worker.php --item=MLB123456
```

#### 🧹 [bin/cleanup-clone-data.php](../bin/cleanup-clone-data.php)
**Descrição:** Limpar dados antigos de jobs completados  
**Uso:**
```bash
# Remover dados com mais de 30 dias
php bin/cleanup-clone-data.php --days=30

# Dry-run (simulação)
php bin/cleanup-clone-data.php --dry-run --days=30

# Com logs detalhados
php bin/cleanup-clone-data.php --verbose
```

#### 📊 [bin/generate-clone-metrics-report.php](../bin/generate-clone-metrics-report.php)
**Descrição:** Gerar relatório de métricas diárias/semanais  
**Uso:**
```bash
# Relatório últimos 7 dias
php bin/generate-clone-metrics-report.php --period=7

# Formato JSON
php bin/generate-clone-metrics-report.php --format=json

# Enviar por email (futuro)
php bin/generate-clone-metrics-report.php --email=admin@example.com
```

#### 💚 [bin/clone-health-monitor.php](../bin/clone-health-monitor.php)
**Descrição:** Monitorar saúde do sistema e gerar alertas  
**Uso:**
```bash
# Verificar saúde
php bin/clone-health-monitor.php

# Com alertas
php bin/clone-health-monitor.php --alert

# Modo verbose
php bin/clone-health-monitor.php --verbose
```

**Quando executar:**
- ✅ A cada 5 minutos (via cron)
- ✅ Troubleshooting de problemas
- ✅ Health check manual

### Configurações

#### ⏰ [crontab.catalog-clone.example](../crontab.catalog-clone.example)
**Descrição:** Exemplo de configuração de crontab para produção  
**Conteúdo:**
- Worker principal (1 min)
- Post-actions (2 min)
- Recovery (15 min)
- Health check (5 min)
- Cleanup (diário)
- Métricas (diário)

**Como usar:**
```bash
# Visualizar conteúdo
cat crontab.catalog-clone.example

# Editar crontab
crontab -e

# Adicionar as linhas do exemplo (ajustando paths)
```

---

## 🎓 Guias por Cenário

### Cenário 1: Primeiro Uso
1. Ler: [Guia do Usuário](GUIA_CLONAGEM_LOTE.md)
2. Acessar: `/dashboard/catalog/clone/batch`
3. Seguir: Tutorial passo a passo
4. Se problemas: [Troubleshooting](TROUBLESHOOTING_CLONAGEM.md)

### Cenário 2: Deploy Novo Ambiente
1. Ler: [Checklist de Deploy](CHECKLIST_DEPLOY_CLONAGEM.md)
2. Executar: Pré-requisitos
3. Aplicar: Migrations
4. Configurar: Crontab
5. Validar: `bash bin/clone-diagnostics.sh`
6. Testar: Seguir testes pós-deploy

### Cenário 3: Problema em Produção
1. Identificar: Qual o sintoma?
2. Consultar: [Troubleshooting](TROUBLESHOOTING_CLONAGEM.md)
3. Executar: Diagnóstico automatizado
4. Aplicar: Solução documentada
5. Se não resolvido: Contatar suporte

### Cenário 4: Novo Desenvolvedor
1. Ler: [Relatório Executivo](RELATORIO_CLONAGEM_LOTE.md)
2. Entender: Arquitetura e componentes
3. Revisar: Código dos Services
4. Executar: Testes unitários
5. Praticar: Ambiente de dev

### Cenário 5: Planejamento de Melhorias
1. Revisar: [Relatório Executivo - Roadmap](RELATORIO_CLONAGEM_LOTE.md#-roadmap-futuro-fase-7)
2. Analisar: Métricas de uso
3. Coletar: Feedback de usuários
4. Priorizar: Features vs complexidade
5. Planejar: Sprint de implementação

---

## 📊 Roadmap de Documentação

### ✅ Completo
- [x] Guia do Usuário
- [x] Troubleshooting
- [x] Relatório Executivo
- [x] Checklist de Deploy
- [x] Sumário de Sessão
- [x] Índice de Documentação

### 🚧 Futuro (Opcionais)
- [ ] Vídeo tutorial de uso
- [ ] API Reference completa
- [ ] Diagramas de sequência
- [ ] Changelog detalhado
- [ ] FAQ expandido

---

## 🔗 Links Úteis

### Código Fonte
- [CatalogCloneService](../app/Services/CatalogCloneService.php) - Core logic (2252 linhas)
- [CloneTemplateService](../app/Services/CloneTemplateService.php) - Templates (427 linhas)
- [ClonePostActionsService](../app/Services/ClonePostActionsService.php) - Pós-ações (512 linhas)
- [CatalogCloneController](../app/Controllers/CatalogCloneController.php) - API endpoints

### Testes
- [CloneTemplateServiceTest](../tests/Unit/Services/CloneTemplateServiceTest.php) - 12 testes
- [CloneMetricsServiceTest](../tests/Unit/Services/CloneMetricsServiceTest.php) - 8 testes
- [ClonePostActionsServiceTest](../tests/Unit/Services/ClonePostActionsServiceTest.php) - 9 testes

### Migrations
- [2026_01_30_create_catalog_clone_batch_tables.sql](../database/migrations/2026_01_30_create_catalog_clone_batch_tables.sql)
- [2026_01_30_create_clone_templates_tables.sql](../database/migrations/2026_01_30_create_clone_templates_tables.sql)

### Roadmap
- [Roadmap Completo](../tests/_doc_implementacao/Roadmap_Clonador_Anuncios_Em_Lote.md)

---

## 📞 Suporte

### Contatos
- **Email:** suporte@eskill.com.br
- **Documentação:** `/docs/`
- **Issues:** GitHub

### Horário de Atendimento
- Segunda a Sexta: 9h às 18h (horário de Brasília)
- Sábados, Domingos e Feriados: Suporte emergencial apenas

### SLA
- 🔴 Crítico: < 4 horas
- 🟡 Alto: < 24 horas
- 🟢 Normal: < 72 horas

---

## 📝 Contribuindo com a Documentação

Encontrou algo errado ou quer sugerir melhorias?

1. Identifique o documento
2. Descreva o problema/sugestão
3. Envie para: documentacao@eskill.com.br
4. Ou crie uma issue no GitHub

---

**Última atualização:** 31/01/2026  
**Versão do índice:** 1.0.0  
**Mantenedor:** Equipe de Desenvolvimento eskill.com.br
