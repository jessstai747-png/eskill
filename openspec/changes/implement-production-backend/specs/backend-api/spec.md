## ADDED Requirements

### Requirement: REST API consistente
O sistema SHALL expor endpoints RESTful com respostas JSON consistentes e códigos HTTP apropriados.

#### Scenario: Sucesso em leitura
- **WHEN** o cliente solicita um recurso existente
- **THEN** o sistema retorna HTTP 200 com payload JSON válido

#### Scenario: Recurso inexistente
- **WHEN** o cliente solicita um recurso que não existe
- **THEN** o sistema retorna HTTP 404 com erro estruturado

### Requirement: Persistência real de dados
O sistema SHALL persistir dados críticos em banco de dados relacional com schema definido.

#### Scenario: Criação de recurso
- **WHEN** o cliente cria um recurso válido
- **THEN** o sistema persiste no banco e retorna HTTP 201

### Requirement: Validação e segurança
O sistema SHALL validar entradas e aplicar autenticação/autorização nas rotas protegidas.

#### Scenario: Entrada inválida
- **WHEN** o payload não atende às regras
- **THEN** o sistema retorna HTTP 400 com detalhes de validação

#### Scenario: Acesso não autorizado
- **WHEN** o cliente acessa rota protegida sem credenciais válidas
- **THEN** o sistema retorna HTTP 401/403

### Requirement: Integrações externas robustas
O sistema SHALL integrar serviços externos com retry, timeout e logging, sem dados fictícios.

#### Scenario: Falha do serviço externo
- **WHEN** a integração externa falha
- **THEN** o sistema retorna erro consistente e registra logs

### Requirement: Documentação e testes
O sistema SHALL fornecer documentação OpenAPI e testes automatizados.

#### Scenario: Descoberta da API
- **WHEN** o consumidor acessa a documentação
- **THEN** encontra contratos completos de endpoints
