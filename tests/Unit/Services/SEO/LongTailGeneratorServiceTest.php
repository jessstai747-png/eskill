<?php

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\LongTailGeneratorService;

class LongTailGeneratorServiceTest extends TestCase
{
    private LongTailGeneratorService $service;

    protected function setUp(): void
    {
        $this->service = new LongTailGeneratorService();
    }

    public function testGenerate(): void
    {
        $title = "Bauleto 41L";
        $categoryId = "MLB3530";
        $result = $this->service->generate($title, $categoryId);

        $this->assertIsArray($result);
    }
}
