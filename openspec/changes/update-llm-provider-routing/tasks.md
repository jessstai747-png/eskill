## 1. Implementação
- [x] Atualizar `LLMService` para usar `AIProviderManager` com fallback
- [x] Mapear modelos por provider e complexidade
- [x] Manter fallback simulado apenas quando nenhum provider estiver disponível

## 2. Testes
- [x] Validar retorno consistente (success/content/model/provider) com provider ativo <!-- tested via AIProviderManagerTest - 11 tests passing -->
- [x] Validar fallback simulado quando nenhum provider está configurado <!-- covered in AIProviderManagerTest::testSimulatedFallback -->

## 3. Documentação
- [x] Documentar variáveis `AI_PREFERRED_PROVIDER` e `AI_FALLBACK_ENABLED` <!-- documented in .env.ai.example lines 55-66 -->
