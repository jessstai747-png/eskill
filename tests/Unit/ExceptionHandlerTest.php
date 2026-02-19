<?php

namespace Tests\Unit;

use App\Core\ExceptionHandler;
use Tests\TestCase;

class ExceptionHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_SERVER['HTTP_ACCEPT']);
        parent::tearDown();
    }

    public function test_wants_json_true_for_api_path(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/items/list';
        unset($_SERVER['HTTP_X_REQUESTED_WITH'], $_SERVER['HTTP_ACCEPT']);

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('wantsJson');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null));
    }

    public function test_wants_json_false_for_html_request_without_ajax_or_json_accept(): void
    {
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('wantsJson');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null));
    }
}
