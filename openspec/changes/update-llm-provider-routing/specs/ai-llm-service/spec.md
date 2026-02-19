## ADDED Requirements

### Requirement: Seleção de provider com fallback
O sistema SHALL selecionar um provider de IA disponível e utilizar fallback quando o provider preferido falhar.

#### Scenario: Provider preferido disponível
- **WHEN** `AI_PREFERRED_PROVIDER` estiver configurado e a respectiva chave estiver disponível
- **THEN** o sistema SHALL usar esse provider para a geração

#### Scenario: Provider preferido indisponível
- **WHEN** o provider preferido não estiver disponível
- **THEN** o sistema SHALL selecionar automaticamente outro provider disponível

### Requirement: Resposta consistente do LLMService
O sistema SHALL retornar resposta consistente contendo `success`, `content`, `model` e `provider`.

#### Scenario: Geração bem-sucedida
- **WHEN** uma geração é concluída com sucesso
- **THEN** a resposta SHALL incluir `provider` e `model` válidos

### Requirement: Fallback simulado controlado
O sistema SHALL usar resposta simulada apenas quando nenhum provider estiver configurado.

#### Scenario: Sem providers configurados
- **WHEN** nenhuma chave de IA estiver disponível
- **THEN** o sistema SHALL retornar resposta simulada com indicação explícita de simulação
