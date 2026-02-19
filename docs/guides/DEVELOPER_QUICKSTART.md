# ⚡ Quick Start - Desenvolvedor

Guia de 5 minutos para começar a desenvolver no Mercado Livre Manager.

## 🚀 Setup Rápido (2 minutos)

```bash
# 1. Clonar e instalar
cd /seu/diretorio
composer install

# 2. Configurar
cp .env.example .env
# Edite .env com suas credenciais

# 3. Banco de dados
php scripts/setup_database.php

# 4. Testar
./bin/test
```

## 🎯 Comandos Essenciais

```bash
# Rodar testes
./bin/test                  # Todos
./bin/test --unit          # Só unitários
./bin/test --coverage      # Com cobertura

# Ver logs
tail -f storage/logs/app-*.log | jq .

# Limpar cache
rm -rf storage/cache/*
```

## 📝 Código Básico (3 minutos)

### 1. Logging

```php
<?php
// Importar helpers (já carregados automaticamente)

// Logs simples
log_info('Usuário acessou dashboard');
log_error('Erro ao processar item', ['item_id' => 123]);

// Exceptions
try {
    $result = $service->process();
} catch (Exception $e) {
    log_exception($e, ['operation' => 'process_item']);
    throw $e;
}

// Performance
measure_time(function() use ($service) {
    return $service->heavyOperation();
}, 'HeavyOperation');
```

### 2. Cache

```php
<?php
// Cache simples
cache('user:123', $userData, 3600);

// Recuperar
$user = cache('user:123');

// Remember pattern
$items = cache_remember('all_items', 3600, function() {
    return Item::getAll();
});

// Com tags
cache_tags(['users', 'premium'], 'user:123', $data);
cache_forget_tag('users'); // Invalida todos
```

### 3. Testes

```php
<?php
namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class MinhaServiceTest extends TestCase
{
    public function testAlgumaCoisa()
    {
        $service = new MinhaService();
        $result = $service->metodo();
        
        $this->assertTrue($result);
    }
}
```

## 🏗️ Criar Nova Feature

```php
<?php
// 1. Criar Service
// app/Services/MinhaService.php

namespace App\Services;

use function App\Helpers\{log_info, log_error, cache_remember};

class MinhaService
{
    public function processar(int $id): array
    {
        log_info('Processando item', ['id' => $id]);
        
        // Cache com TTL de 1 hora
        return cache_remember("item_$id", 3600, function() use ($id) {
            // Buscar dados...
            $data = $this->buscarDados($id);
            
            log_info('Dados processados', ['id' => $id]);
            return $data;
        });
    }
    
    private function buscarDados(int $id): array
    {
        // Simular processamento
        measure_time(function() {
            sleep(2);
        }, 'BuscarDados');
        
        return ['id' => $id, 'nome' => 'Item'];
    }
}

// 2. Criar Controller
// app/Controllers/MinhaController.php

namespace App\Controllers;

use App\Services\MinhaService;

class MinhaController
{
    private MinhaService $service;
    
    public function __construct()
    {
        $this->service = new MinhaService();
    }
    
    public function processar(int $id): void
    {
        header('Content-Type: application/json');
        
        try {
            $result = $this->service->processar($id);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            log_exception($e);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

// 3. Adicionar Rota
// app/Routes/web.php

$router->get('/api/minha/processar/{id}', [MinhaController::class, 'processar']);

// 4. Criar Teste
// tests/Unit/Services/MinhaServiceTest.php

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
    
    public function testProcessar()
    {
        $result = $this->service->processar(123);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(123, $result['id']);
    }
}

// 5. Rodar Teste
// ./bin/test --filter=MinhaService
```

## 📚 Ler Depois

| Quando | Ler |
|--------|-----|
| **Agora** | [QUICK_REFERENCE.md](QUICK_REFERENCE.md) |
| **Hoje** | [TESTING_GUIDE.md](TESTING_GUIDE.md) |
| **Amanhã** | [LOGGING_GUIDE.md](LOGGING_GUIDE.md) |
| **Depois** | [CACHING_GUIDE.md](CACHING_GUIDE.md) |
| **Quando precisar** | [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md) |

## 🎓 Padrões do Projeto

### Controllers
```php
<?php
class MeuController
{
    private MeuService $service;
    
    public function __construct()
    {
        $this->service = new MeuService();
    }
    
    public function metodo(): void
    {
        header('Content-Type: application/json');
        // lógica
        echo json_encode($response);
    }
}
```

### Services
```php
<?php
class MeuService
{
    public function processar($data): array
    {
        log_info('Processando', ['data' => $data]);
        
        try {
            $result = $this->executar($data);
            return $result;
        } catch (Exception $e) {
            log_exception($e);
            throw $e;
        }
    }
}
```

### Rotas
```php
<?php
// GET
$router->get('/api/recurso/{id}', [Controller::class, 'metodo']);

// POST
$router->post('/api/recurso', [Controller::class, 'criar']);

// PUT
$router->put('/api/recurso/{id}', [Controller::class, 'atualizar']);

// DELETE
$router->delete('/api/recurso/{id}', [Controller::class, 'deletar']);
```

## 🔧 Debugging

```php
<?php
// 1. Ver logs no terminal
tail -f storage/logs/app-*.log | jq .

// 2. Adicionar logs temporários
log_debug('Valor da variável', ['var' => $value]);

// 3. Usar var_dump (só em dev)
var_dump($data);

// 4. Rodar testes específicos
./bin/test --filter=NomeDaTeste

// 5. Ver estatísticas de cache
$cache = new \App\Services\AdvancedCacheService('file');
print_r($cache->getStats());
```

## ⚠️ Erros Comuns

### Cache não funciona
```bash
# Verificar permissões
chmod -R 775 storage/cache/
chown -R www-data:www-data storage/cache/
```

### Logs não aparecem
```bash
# Verificar permissões
chmod -R 775 storage/logs/
ls -la storage/logs/
```

### Testes falhando
```bash
# Recarregar autoload
composer dump-autoload

# Verificar bootstrap
cat tests/bootstrap.php
```

## 🎯 Próximos Passos

1. ✅ Rode `./bin/test` para ver os testes
2. ✅ Acesse `/dashboard/logs` para ver logs
3. ✅ Crie seu primeiro teste
4. ✅ Use cache em uma query
5. ✅ Adicione logs em um controller

## 📞 Ajuda

- **Testes:** [TESTING_GUIDE.md](TESTING_GUIDE.md)
- **Logs:** [LOGGING_GUIDE.md](LOGGING_GUIDE.md)
- **Cache:** [CACHING_GUIDE.md](CACHING_GUIDE.md)
- **Tudo:** [DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md)

---

**Tempo total:** 5 minutos  
**Próximo passo:** Implementar sua primeira feature! 🚀
