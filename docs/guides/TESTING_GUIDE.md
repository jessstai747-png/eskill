# 🧪 Sistema de Testes - Mercado Livre Manager

## Visão Geral

Sistema completo de testes com **PHPUnit 10**, incluindo testes unitários e de integração para garantir a qualidade do código.

## Estatísticas

- **52 testes totais**
  - 39 testes unitários
  - 13 testes de integração
- **Cobertura**: Serviços de IA, Logs, Cache, Controllers

## Comando CLI

### Instalação

O comando já está pronto para uso:

```bash
chmod +x bin/test  # Tornar executável (se necessário)
```

### Uso Básico

```bash
# Rodar todos os testes
./bin/test

# Apenas testes unitários
./bin/test --unit

# Apenas testes de integração
./bin/test --integration

# Apenas testes de IA
./bin/test --ai
```

### Opções Avançadas

```bash
# Com relatório de cobertura
./bin/test --coverage

# Filtrar por nome
./bin/test --filter=TitleOptimizer

# Output verboso
./bin/test --verbose

# Combinar opções
./bin/test --ai --verbose
./bin/test --unit --filter=Cache
```

### Ajuda

```bash
./bin/test --help
```

## Estrutura de Testes

```
tests/
├── bootstrap.php              # Bootstrap dos testes
├── Unit/                      # Testes unitários
│   ├── Services/
│   │   └── AI/
│   │       ├── TitleOptimizerTest.php
│   │       ├── DescriptionOptimizerTest.php
│   │       ├── TechSheetOptimizerTest.php
│   │       └── AIProviderManagerTest.php
│   └── ...
└── Integration/               # Testes de integração
    └── LogSystemIntegrationTest.php
```

## Testes Unitários (39)

### Sistema de IA

#### TitleOptimizer (12 testes)
- ✅ Análise retorna score adequado
- ✅ Detecta títulos curtos
- ✅ Detecta títulos longos
- ✅ Detecta keywords faltantes
- ✅ Reconhece bons títulos
- ✅ Valida títulos
- ✅ Rejeita caracteres inválidos
- ✅ Compara versões
- ✅ Calcula scores corretamente

#### DescriptionOptimizer (8 testes)
- ✅ Análise retorna estrutura adequada
- ✅ Detecta descrições muito curtas
- ✅ Detecta estrutura faltante
- ✅ Reconhece descrições bem estruturadas
- ✅ Detecta densidade de keywords
- ✅ Adiciona estrutura (emojis, bullets)
- ✅ Templates por categoria
- ✅ Valida comprimento

#### TechSheetOptimizer (9 testes)
- ✅ Análise de completude
- ✅ Detecta atributos obrigatórios faltantes
- ✅ Calcula percentual correto
- ✅ Infere valores de atributos
- ✅ Valores padrão comuns
- ✅ Validação por categoria
- ✅ Prioridades de atributos
- ✅ Formato de sugestões
- ✅ Atributos vazios

#### AIProviderManager (10 testes)
- ✅ Retorna providers disponíveis
- ✅ Instância correta de provider
- ✅ Provider preferido
- ✅ Fallback quando primário falha
- ✅ Estatísticas de providers
- ✅ Verificação de disponibilidade
- ✅ Provider mais barato
- ✅ Provider mais rápido
- ✅ Estratégia por custo
- ✅ Estratégia por velocidade
- ✅ Estratégia por qualidade

## Testes de Integração (13)

### Sistema de Logs

- ✅ Logs são escritos em arquivo
- ✅ Formato JSON correto
- ✅ Múltiplos níveis registrados
- ✅ Contexto incluído
- ✅ Log de exceções
- ✅ Log de performance
- ✅ Log de auditoria
- ✅ Busca e filtragem
- ✅ Estatísticas
- ✅ Helpers globais funcionam
- ✅ Função measure_time
- ✅ Logs concorrentes

## Rodando os Testes

### Via CLI Tool (Recomendado)

```bash
./bin/test
```

### Via PHPUnit Direto

```bash
# Todos os testes
vendor/bin/phpunit

# Apenas unitários
vendor/bin/phpunit tests/Unit/

# Apenas integração
vendor/bin/phpunit tests/Integration/

# Com testdox (output bonito)
vendor/bin/phpunit --testdox

# Específico
vendor/bin/phpunit tests/Unit/Services/AI/TitleOptimizerTest.php
```

### Com Cobertura

```bash
# Via CLI
./bin/test --coverage

# Via PHPUnit
vendor/bin/phpunit --coverage-html storage/coverage

# Abrir relatório
open storage/coverage/index.html
```

## Escrevendo Novos Testes

### Teste Unitário

```php
<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\MinhaService;

class MinhaServiceTest extends TestCase
{
    private MinhaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MinhaService();
    }

    public function testAlgumaFuncionalidade()
    {
        $result = $this->service->metodo('param');
        
        $this->assertTrue($result);
        $this->assertEquals('esperado', $result);
        $this->assertArrayHasKey('chave', $result);
    }
}
```

### Teste de Integração

```php
<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class MinhaIntegrationTest extends TestCase
{
    public function testFluxoCompleto()
    {
        // Setup
        $service1 = new Service1();
        $service2 = new Service2();
        
        // Executar
        $result = $service1->processar($data);
        $final = $service2->finalizar($result);
        
        // Verificar
        $this->assertNotNull($final);
    }
}
```

## Melhores Práticas

1. **Organize por tipo**: Unitários em `Unit/`, Integração em `Integration/`
2. **Use setUp/tearDown**: Para inicialização e limpeza
3. **Nomes descritivos**: `testMetodoDeveRetornarValorEsperado()`
4. **Um assert por conceito**: Facilita identificar falhas
5. **Mock dependências**: Use mocks para isolar testes unitários
6. **Limpe após testes**: Remova arquivos temporários

## Integração Contínua

### GitHub Actions (Exemplo)

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install dependencies
        run: composer install
        
      - name: Run tests
        run: ./bin/test
```

## Troubleshooting

### Erro: "bin/test: Permission denied"

```bash
chmod +x bin/test
```

### Erro: "Class not found"

```bash
composer dump-autoload
```

### Erro: "network_disabled" em testes

Por padrão, em `APP_ENV=testing` as chamadas externas são bloqueadas para manter a suíte determinística.

Para habilitar testes que dependem de rede, execute com:

```bash
ML_ALLOW_NETWORK=true ./bin/test --integration
```

### Testes lentos

```bash
# Use filtros para rodar apenas o necessário
./bin/test --unit --filter=NomeDoTeste
```

### Falhas em testes de integração

- Verifique se todos os serviços necessários estão disponíveis
- Confirme permissões de escrita em `storage/`
- Limpe cache antes de rodar: `rm -rf storage/cache/*`

## Recursos

- [PHPUnit Documentation](https://phpunit.de/)
- [PHP Testing Best Practices](https://phptherightway.com/#testing)
- [TDD with PHP](https://www.php-fig.org/)

---

**Desenvolvido para:** Mercado Livre Manager  
**Versão:** 1.0.0  
**Data:** 25/12/2025
