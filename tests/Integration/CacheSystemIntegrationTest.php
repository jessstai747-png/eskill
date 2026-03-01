<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\AdvancedCacheService;

class CacheSystemIntegrationTest extends TestCase
{
    private AdvancedCacheService $cache;
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Usar diretório dentro do workspace para testes (sandbox tem /tmp read-only)
        $workspaceStorage = dirname(__DIR__, 2) . '/storage/test-cache';
        $this->cacheDir = $workspaceStorage . '/cache_test_' . uniqid();
        mkdir($this->cacheDir, 0777, true);

        $this->cache = new AdvancedCacheService('file', [
            'path' => $this->cacheDir
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Limpar diretório de testes
        if (is_dir($this->cacheDir)) {
            $this->removeDirectory($this->cacheDir);
        }
    }

    /**
     * Testa cache básico (set/get)
     */
    public function testBasicCaching()
    {
        $key = 'test_key';
        $value = ['data' => 'test value', 'number' => 123];

        // Set
        $result = $this->cache->set($key, $value);
        $this->assertTrue($result);

        // Get
        $cached = $this->cache->get($key);
        $this->assertEquals($value, $cached);
    }

    /**
     * Testa cache com TTL
     */
    public function testCacheWithTTL()
    {
        $key = 'expiring_key';
        $value = 'temporary data';

        // Set com TTL de 1 segundo
        $this->cache->set($key, $value, 1);

        // Deve existir imediatamente
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals($value, $this->cache->get($key));

        // Esperar expirar
        sleep(2);

        // Não deve mais existir
        $this->assertFalse($this->cache->has($key));
        $this->assertNull($this->cache->get($key));
    }

    /**
     * Testa cache com tags
     */
    public function testCacheWithTags()
    {
        // Adicionar vários itens com tags
        $this->cache->set('user:1', ['name' => 'John'], 3600, ['users', 'premium']);
        $this->cache->set('user:2', ['name' => 'Jane'], 3600, ['users', 'free']);
        $this->cache->set('product:1', ['name' => 'Widget'], 3600, ['products']);

        // Todos devem existir
        $this->assertTrue($this->cache->has('user:1'));
        $this->assertTrue($this->cache->has('user:2'));
        $this->assertTrue($this->cache->has('product:1'));

        // Invalidar tag 'users'
        $removed = $this->cache->invalidateTags(['users']);
        $this->assertGreaterThanOrEqual(2, $removed);

        // Usuários não devem mais existir
        $this->assertFalse($this->cache->has('user:1'));
        $this->assertFalse($this->cache->has('user:2'));

        // Produto ainda deve existir
        $this->assertTrue($this->cache->has('product:1'));
    }

    /**
     * Testa o método remember
     */
    public function testRememberMethod()
    {
        $key = 'expensive_operation';
        $callCount = 0;

        $callback = function () use (&$callCount) {
            $callCount++;
            return 'computed value';
        };

        // Primeira chamada - deve executar callback
        $result1 = $this->cache->remember($key, 3600, $callback);
        $this->assertEquals('computed value', $result1);
        $this->assertEquals(1, $callCount);

        // Segunda chamada - deve usar cache
        $result2 = $this->cache->remember($key, 3600, $callback);
        $this->assertEquals('computed value', $result2);
        $this->assertEquals(1, $callCount); // Não deve ter chamado novamente
    }

    /**
     * Testa limpeza de cache expirado
     */
    public function testClearExpired()
    {
        // Adicionar item que expira rápido
        $this->cache->set('temp1', 'value1', 1);

        // Adicionar item que não expira
        $this->cache->set('perm1', 'value2', 3600);

        // Esperar expirar
        sleep(2);

        // Limpar expirados
        $removed = $this->cache->clearExpired();
        $this->assertGreaterThanOrEqual(1, $removed);

        // Item temporário não deve existir
        $this->assertFalse($this->cache->has('temp1'));

        // Item permanente deve existir
        $this->assertTrue($this->cache->has('perm1'));
    }

    /**
     * Testa estatísticas de cache
     */
    public function testCacheStatistics()
    {
        // Gerar alguns hits e misses
        $this->cache->set('key1', 'value1');
        $this->cache->get('key1'); // hit
        $this->cache->get('key1'); // hit
        $this->cache->get('key2'); // miss

        $stats = $this->cache->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);

        $this->assertEquals(2, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
    }

    /**
     * Testa limpar todo o cache
     */
    public function testClearAllCache()
    {
        // Adicionar vários itens
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        // Todos devem existir
        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertTrue($this->cache->has('key3'));

        // Limpar tudo
        $this->cache->clear();

        // Nenhum deve existir
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    /**
     * Testa delete de item específico
     */
    public function testDeleteSpecificItem()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        // Deletar apenas key1
        $result = $this->cache->delete('key1');
        $this->assertTrue($result);

        // key1 não deve existir
        $this->assertFalse($this->cache->has('key1'));

        // key2 ainda deve existir
        $this->assertTrue($this->cache->has('key2'));
    }

    /**
     * Testa cache de diferentes tipos de dados
     */
    public function testCacheDifferentDataTypes()
    {
        // String
        $this->cache->set('string', 'text');
        $this->assertEquals('text', $this->cache->get('string'));

        // Integer
        $this->cache->set('integer', 42);
        $this->assertEquals(42, $this->cache->get('integer'));

        // Float
        $this->cache->set('float', 3.14);
        $this->assertEquals(3.14, $this->cache->get('float'));

        // Boolean
        $this->cache->set('bool', true);
        $this->assertTrue($this->cache->get('bool'));

        // Array
        $this->cache->set('array', ['a' => 1, 'b' => 2]);
        $this->assertEquals(['a' => 1, 'b' => 2], $this->cache->get('array'));

        // Object (as array)
        $obj = (object)['prop' => 'value'];
        $this->cache->set('object', $obj);
        $this->assertEquals($obj, $this->cache->get('object'));
    }

    /**
     * Testa cache com múltiplas tags
     */
    public function testCacheWithMultipleTags()
    {
        $this->cache->set('item1', 'value1', 3600, ['tag1', 'tag2', 'tag3']);
        $this->cache->set('item2', 'value2', 3600, ['tag2', 'tag4']);
        $this->cache->set('item3', 'value3', 3600, ['tag3']);

        // Invalidar tag2
        $this->cache->invalidateTags(['tag2']);

        // item1 e item2 não devem existir (têm tag2)
        $this->assertFalse($this->cache->has('item1'));
        $this->assertFalse($this->cache->has('item2'));

        // item3 ainda deve existir (não tem tag2)
        $this->assertTrue($this->cache->has('item3'));
    }

    /**
     * Testa cache com valores null
     */
    public function testCacheWithNullValues()
    {
        // Null é um valor válido
        $this->cache->set('null_key', null);

        // Deve existir
        $this->assertTrue($this->cache->has('null_key'));

        // Deve retornar null
        $this->assertNull($this->cache->get('null_key'));
    }

    /**
     * Testa cache com chaves especiais
     */
    public function testCacheWithSpecialKeys()
    {
        // Chaves com caracteres especiais
        $specialKeys = [
            'user:123:profile',
            'cache/nested/key',
            'key_with_underscores',
            'key-with-dashes',
            'key.with.dots',
        ];

        foreach ($specialKeys as $key) {
            $this->cache->set($key, "value for $key");
            $this->assertTrue($this->cache->has($key));
            $this->assertEquals("value for $key", $this->cache->get($key));
        }
    }

    /**
     * Testa sobrescrita de cache existente
     */
    public function testOverwriteExistingCache()
    {
        $key = 'overwrite_key';

        // Primeiro valor
        $this->cache->set($key, 'first value');
        $this->assertEquals('first value', $this->cache->get($key));

        // Sobrescrever
        $this->cache->set($key, 'second value');
        $this->assertEquals('second value', $this->cache->get($key));
    }

    /**
     * Testa cache com valores grandes
     */
    public function testCacheWithLargeValues()
    {
        // Criar array grande
        $largeArray = array_fill(0, 1000, 'data');

        $this->cache->set('large_data', $largeArray);

        $retrieved = $this->cache->get('large_data');
        $this->assertCount(1000, $retrieved);
        $this->assertEquals($largeArray, $retrieved);
    }

    /**
     * Remove diretório recursivamente
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
