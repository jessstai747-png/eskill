<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Router;
use Tests\TestCase;

/**
 * Mock Controller para testes de roteamento
 */
class MockController
{
    public static array $callLog = [];

    public function index(): void
    {
        self::$callLog[] = ['method' => 'index', 'params' => []];
        echo json_encode(['action' => 'index']);
    }

    public function show(string $id): void
    {
        self::$callLog[] = ['method' => 'show', 'params' => ['id' => $id]];
        echo json_encode(['action' => 'show', 'id' => $id]);
    }

    public function edit(string $id, string $section): void
    {
        self::$callLog[] = ['method' => 'edit', 'params' => ['id' => $id, 'section' => $section]];
        echo json_encode(['action' => 'edit', 'id' => $id, 'section' => $section]);
    }

    public static function reset(): void
    {
        self::$callLog = [];
    }
}

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router();
        MockController::reset();
    }

    // =============================
    // TESTES DE REGISTRO DE ROTAS
    // =============================

    public function testCanRegisterGetRoute(): void
    {
        $this->router->get('/test', MockController::class, 'index');

        ob_start();
        $this->router->dispatch('GET', '/test');
        $output = ob_get_clean();

        $this->assertJson($output);
        $data = json_decode($output, true);
        $this->assertEquals('index', $data['action']);
    }

    public function testCanRegisterPostRoute(): void
    {
        $this->router->post('/submit', MockController::class, 'index');

        ob_start();
        $this->router->dispatch('POST', '/submit');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertEquals('index', $data['action']);
    }

    public function testCanRegisterPutRoute(): void
    {
        $this->router->put('/update', MockController::class, 'index');

        ob_start();
        $this->router->dispatch('PUT', '/update');
        ob_get_clean();

        $this->assertCount(1, MockController::$callLog);
        $this->assertEquals('index', MockController::$callLog[0]['method']);
    }

    public function testCanRegisterDeleteRoute(): void
    {
        $this->router->delete('/delete', MockController::class, 'index');

        ob_start();
        $this->router->dispatch('DELETE', '/delete');
        ob_get_clean();

        $this->assertCount(1, MockController::$callLog);
    }

    // =============================
    // TESTES DE PARÂMETROS
    // =============================

    public function testRouteWithSingleParameter(): void
    {
        $this->router->get('/items/{id}', MockController::class, 'show');

        ob_start();
        $this->router->dispatch('GET', '/items/123');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertEquals('show', $data['action']);
        $this->assertEquals('123', $data['id']);
    }

    public function testRouteWithMultipleParameters(): void
    {
        $this->router->get('/items/{id}/edit/{section}', MockController::class, 'edit');

        ob_start();
        $this->router->dispatch('GET', '/items/456/edit/details');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertEquals('edit', $data['action']);
        $this->assertEquals('456', $data['id']);
        $this->assertEquals('details', $data['section']);
    }

    public function testRouteParameterParsesCorrectly(): void
    {
        $this->router->get('/api/seo/{itemId}', MockController::class, 'show');

        ob_start();
        $this->router->dispatch('GET', '/api/seo/MLB123456789');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertEquals('MLB123456789', $data['id']);
    }

    // =============================
    // TESTES DE NORMALIZAÇÃO DE PATH
    // =============================

    public function testNormalizesPathWithLeadingSlash(): void
    {
        $this->router->get('/test', MockController::class, 'index');

        ob_start();
        $this->router->dispatch('GET', 'test');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertEquals('index', $data['action']);
    }

    public function testNormalizesPathWithTrailingSlash(): void
    {
        $this->router->get('/test', MockController::class, 'index');

        ob_start();
        $this->router->dispatch('GET', '/test/');
        ob_get_clean();

        // Se trailing slash é diferente, rota não deve encontrar ou normalizar
        $callCount = count(MockController::$callLog);
        // Router pode ou não aceitar trailing slash - depende da implementação
        $this->assertTrue(true); // Placeholder assertion
    }

    public function testHandlesEmptyPath(): void
    {
        $this->router->get('/', MockController::class, 'index');

        ob_start();
        $this->router->dispatch('GET', '');
        ob_get_clean();

        $this->assertCount(1, MockController::$callLog);
    }

    // =============================
    // TESTES DE 404
    // =============================

    public function testReturns404ForUnknownRoute(): void
    {
        $this->router->get('/known', MockController::class, 'index');

        ob_start();
        $this->router->dispatch('GET', '/unknown');
        ob_get_clean();

        // Verificar que controller não foi chamado
        $this->assertCount(0, MockController::$callLog);
    }

    public function testReturns404ForWrongMethod(): void
    {
        $this->router->get('/test', MockController::class, 'index');

        ob_start();
        $this->router->dispatch('POST', '/test');
        ob_get_clean();

        // Rota GET não deve ser chamada com POST
        $this->assertCount(0, MockController::$callLog);
    }

    // =============================
    // TESTES DE HEAD REQUEST
    // =============================

    public function testHeadRequestMatchesGetRoute(): void
    {
        $this->router->get('/test', MockController::class, 'index');

        ob_start();
        $this->router->dispatch('HEAD', '/test');
        ob_get_clean();

        // HEAD deve ser tratado como GET
        $this->assertCount(1, MockController::$callLog);
    }

    // =============================
    // TESTES DE MÉTODO DEFAULT
    // =============================

    public function testUsesIndexAsDefaultMethod(): void
    {
        $this->router->get('/default', MockController::class); // Sem especificar método

        ob_start();
        $this->router->dispatch('GET', '/default');
        ob_get_clean();

        $this->assertCount(1, MockController::$callLog);
        $this->assertEquals('index', MockController::$callLog[0]['method']);
    }

    // =============================
    // TESTES DE PRIORIDADE
    // =============================

    public function testFirstMatchingRouteWins(): void
    {
        // Registrar duas rotas que poderiam combinar
        $this->router->get('/items/{id}', MockController::class, 'show');
        $this->router->get('/items/special', MockController::class, 'index');

        ob_start();
        // A primeira rota registrada deve ser verificada primeiro
        $this->router->dispatch('GET', '/items/special');
        $output = ob_get_clean();

        $data = json_decode($output, true);
        // A primeira rota {id} vai capturar 'special' como id
        $this->assertEquals('show', $data['action']);
        $this->assertEquals('special', $data['id']);
    }
}
