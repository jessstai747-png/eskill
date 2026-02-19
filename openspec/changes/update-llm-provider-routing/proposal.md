# Change: Roteamento real de LLM com múltiplos providers

## Por quê
O serviço `LLMService` atualmente usa apenas Claude via `ClaudeClient` e entra em modo simulado quando `ANTHROPIC_API_KEY` não existe, mesmo que `OPENAI_API_KEY` ou `GEMINI_API_KEY` estejam configuradas. Isso impede uso real de IA quando outros providers estão disponíveis.

## O que muda
- Usar `AIProviderManager` para selecionar provider disponível (OpenAI/Claude/Gemini) com fallback.
- Ler chaves a partir de `AIConfigService` e propagar para `_ENV` somente em runtime (sem logar segredos).
- Garantir resposta consistente com metadados de provider e modelo.
- Manter fallback simulado apenas quando **nenhum** provider estiver disponível.

## Impacto
- Código afetado: `app/Services/LLMService.php`.
- Segurança: sem logar chaves; apenas resolução de provider.
- Observabilidade: logs passam a indicar provider/modelo usados.
