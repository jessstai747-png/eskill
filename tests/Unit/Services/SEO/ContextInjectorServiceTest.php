<?php

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\ContextInjectorService;

class ContextInjectorServiceTest extends TestCase
{
    private ContextInjectorService $service;

    protected function setUp(): void
    {
        $this->service = new ContextInjectorService();
    }

    public function testInject(): void
    {
        $text = "Este é um bauleto.";
        $contexts = ['profissional'];
        $result = $this->service->inject($text, $contexts);

        $this->assertIsString($result);
    }

    public function testDetectApplicableContexts(): void
    {
        $item = ['title' => 'Bauleto para delivery'];
        $result = $this->service->detectApplicableContexts($item);

        $this->assertIsArray($result);
        $this->assertContains('profissional', $result);
    }
}
