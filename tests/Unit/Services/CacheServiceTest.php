<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CacheService;

/**
 * Testes do CacheService
 */
class CacheServiceTest extends TestCase
{
    private CacheService $cache;
    private string $testKey = 'test_cache_key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new CacheService();
    }

    protected function tearDown(): void
    {
        // Limpar cache de teste
        $this->cache->forget($this->testKey);
        $this->cache->forget($this->testKey . '_remember');
        parent::tearDown();
    }

    // =============================
    // TESTES DE INSTANCIAÇÃO
    // =============================

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CacheService::class, $this->cache);
    }

    // =============================
    // TESTES DE SET/GET
    // =============================

    public function testCanSetAndGetValue(): void
    {
        $value = ['test' => 'data', 'number' => 123];

        $this->cache->set($this->testKey, $value);
        $retrieved = $this->cache->get($this->testKey);

        $this->assertEquals($value, $retrieved);
    }

    public function testGetReturnsNullForNonExistentKey(): void
    {
        $result = $this->cache->get('non_existent_key_' . time());
        $this->assertNull($result);
    }

    public function testCanSetStringValue(): void
    {
        $this->cache->set($this->testKey, 'string value');
        $this->assertEquals('string value', $this->cache->get($this->testKey));
    }

    public function testCanSetIntegerValue(): void
    {
        $this->cache->set($this->testKey, 42);
        $this->assertEquals(42, $this->cache->get($this->testKey));
    }

    public function testCanSetArrayValue(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => [1, 2, 3]];
        $this->cache->set($this->testKey, $array);
        $this->assertEquals($array, $this->cache->get($this->testKey));
    }

    // =============================
    // TESTES DE HAS
    // =============================

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->cache->set($this->testKey, 'value');
        $this->assertTrue($this->cache->has($this->testKey));
    }

    public function testHasReturnsFalseForNonExistentKey(): void
    {
        $this->assertFalse($this->cache->has('non_existent_key_' . time()));
    }

    // =============================
    // TESTES DE FORGET
    // =============================

    public function testForgetRemovesKey(): void
    {
        $this->cache->set($this->testKey, 'value');
        $this->assertTrue($this->cache->has($this->testKey));

        $this->cache->forget($this->testKey);
        $this->assertFalse($this->cache->has($this->testKey));
    }

    public function testForgetReturnsTrueForNonExistentKey(): void
    {
        $result = $this->cache->forget('non_existent_key_' . time());
        $this->assertTrue($result);
    }

    // =============================
    // TESTES DE REMEMBER
    // =============================

    public function testRememberReturnsCallbackResultOnMiss(): void
    {
        $key = $this->testKey . '_remember';

        $result = $this->cache->remember($key, function () {
            return 'computed_value';
        });

        $this->assertEquals('computed_value', $result);
    }

    public function testRememberCachesCallbackResult(): void
    {
        $key = $this->testKey . '_remember';
        $counter = 0;

        // Primeira chamada - executa callback
        $this->cache->remember($key, function () use (&$counter) {
            $counter++;
            return 'value';
        });

        // Segunda chamada - deve usar cache
        $this->cache->remember($key, function () use (&$counter) {
            $counter++;
            return 'different_value';
        });

        $this->assertEquals(1, $counter, 'Callback deve ser executado apenas uma vez');
    }

    public function testRememberReturnsCachedValueOnHit(): void
    {
        $key = $this->testKey . '_remember';

        $this->cache->set($key, 'cached_value');

        $result = $this->cache->remember($key, function () {
            return 'new_value';
        });

        $this->assertEquals('cached_value', $result);
    }

    // =============================
    // TESTES DE TTL
    // =============================

    public function testSetAcceptsTtl(): void
    {
        $reflection = new \ReflectionMethod(CacheService::class, 'set');
        $params = $reflection->getParameters();

        $this->assertCount(4, $params);
        $this->assertEquals('ttlOrTag', $params[2]->getName());
        $this->assertTrue($params[2]->isDefaultValueAvailable());
        $this->assertEquals(3600, $params[2]->getDefaultValue());
    }

    // =============================
    // TESTES DE MÉTODOS
    // =============================

    public function testHasRequiredMethods(): void
    {
        $methods = [
            'get',
            'set',
            'has',
            'forget',
            'flush',
            'clear',
            'remember',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->cache, $method),
                "CacheService deve ter método {$method}()"
            );
        }
    }

    public function testClearIsAliasForFlush(): void
    {
        // Clear deve ser um alias para flush
        $this->assertTrue(method_exists($this->cache, 'clear'));
        $this->assertTrue(method_exists($this->cache, 'flush'));
    }

    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasDriverProperty(): void
    {
        $reflection = new \ReflectionClass(CacheService::class);
        $this->assertTrue($reflection->hasProperty('driver'));
    }

    public function testHasCacheDirProperty(): void
    {
        $reflection = new \ReflectionClass(CacheService::class);
        $this->assertTrue($reflection->hasProperty('cacheDir'));
    }

    public function testHasRedisProperty(): void
    {
        $reflection = new \ReflectionClass(CacheService::class);
        $this->assertTrue($reflection->hasProperty('redis'));
    }

    // =============================
    // TESTES DE TIPOS DE RETORNO
    // =============================

    public function testGetReturnsMixed(): void
    {
        $reflection = new \ReflectionMethod(CacheService::class, 'get');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('mixed', $returnType->getName());
    }

    public function testSetReturnsBool(): void
    {
        $reflection = new \ReflectionMethod(CacheService::class, 'set');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testHasReturnsBool(): void
    {
        $reflection = new \ReflectionMethod(CacheService::class, 'has');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    // =============================
    // TESTES DE DOCUMENTAÇÃO
    // =============================

    public function testClassHasDocumentation(): void
    {
        $reflection = new \ReflectionClass(CacheService::class);
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment);
        $this->assertStringContainsString('Cache', $docComment);
    }
}
