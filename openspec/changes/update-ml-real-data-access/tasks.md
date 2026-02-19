## 1. Implementation
- [x] 1.1 Identificar quais métodos do `MercadoLivreClient` são “públicos” vs “autenticados”
- [x] 1.2 Remover retornos de dados fictícios (fallback fake) dos métodos públicos; usar chamadas reais do ML
- [x] 1.3 Implementar controle de rede por ambiente: em `APP_ENV=testing` bloquear rede por padrão (opt-in via env)
- [x] 1.4 Padronizar payloads de erro para casos sem token / rede bloqueada
- [x] 1.5 Ajustar serviços consumidores para lidar com erro (e.g., retornar mensagem clara / pular testes)

## 2. Tests
- [x] 2.1 Adicionar testes unitários para “network disabled” em ambiente de testing
- [x] 2.2 Garantir que testes que dependem de rede sejam `skipped` quando opt-in não estiver ativo

## 3. Documentation
- [x] 3.1 Documentar variáveis de ambiente novas (ex.: `ML_ALLOW_NETWORK=true`) e comportamento API-first
