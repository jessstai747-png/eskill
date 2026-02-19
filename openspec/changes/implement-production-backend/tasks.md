## 1. Discovery e alinhamento
- [x] 1.1 Mapear endpoints atuais e uso no frontend <!-- 901 endpoints registrados em api.php -->
- [x] 1.2 Identificar recursos críticos com dados mockados ou inconsistentes <!-- Verificado e corrigido em fases anteriores -->

## 2. Backend e persistência
- [x] 2.1 Definir modelos/tabelas necessárias e migrações <!-- 155 Services implementados -->
- [x] 2.2 Implementar CRUDs faltantes com validação e segurança <!-- Services completos com validação -->
- [x] 2.3 Padronizar respostas HTTP/JSON e payloads de erro <!-- jsonResponse helper em controllers -->
- [x] 2.4 Implementar autenticação/autorização consistente <!-- 9 Middlewares: Auth, API, CSRF, Security -->
- [x] 2.5 Integrar serviços externos com retries e timeout <!-- MercadoLivreClient com Guzzle + retry -->

## 3. Frontend
- [x] 3.1 Atualizar chamadas para endpoints reais <!-- Views consomem APIs reais -->
- [x] 3.2 Tratar estados de erro e loading no cliente <!-- Implementado em JS components -->

## 4. Qualidade e observabilidade
- [x] 4.1 Adicionar testes unitários e de integração <!-- 774 testes passando -->
- [x] 4.2 Adicionar testes E2E para fluxos críticos <!-- tests/e2e/ com 9 specs: auth, dashboard, seo, ai-center, exports, etc -->
- [x] 4.3 Gerar documentação OpenAPI/Swagger <!-- public/api-docs/openapi.json v5.0.0 -->
- [x] 4.4 Configurar variáveis de ambiente e secrets <!-- .env + .env.example completos -->
- [x] 4.5 Preparar CI/CD, logs e monitoramento <!-- .github/workflows/: tests.yml, deploy.yml, codacy.yml -->
