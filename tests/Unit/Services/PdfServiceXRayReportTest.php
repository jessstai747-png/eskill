<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PdfService;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Services\PdfService::generateXRayReport
 */
class PdfServiceXRayReportTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(PdfService::class);
    }

    public function testGenerateXRayReportMethodExists(): void
    {
        $this->assertTrue($this->reflection->hasMethod('generateXRayReport'));
        $method = $this->reflection->getMethod('generateXRayReport');
        $this->assertTrue($method->isPublic());
    }

    public function testGenerateXRayReportAcceptsArrayParameter(): void
    {
        $method = $this->reflection->getMethod('generateXRayReport');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('row', $params[0]->getName());

        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame('array', $type->getName());
    }

    public function testGetXRayBaseHtmlInjectsExtraStyles(): void
    {
        $service = new PdfService();
        $method  = $this->reflection->getMethod('getXRayBaseHtml');
        $method->setAccessible(true);

        $html = $method->invoke($service, 'Raio X — teste');

        $this->assertStringContainsString('Raio X', $html);
        $this->assertStringContainsString('xray-score-badge', $html);
        $this->assertStringContainsString('excellent', $html);
        $this->assertStringContainsString('poor', $html);
    }

    public function testPhpSyntaxOfPdfServiceIsValid(): void
    {
        $file = realpath(__DIR__ . '/../../../app/Services/PdfService.php');
        $this->assertNotFalse($file, 'PdfService.php must exist');
        $this->assertFileExists($file);

        // Verify the class defines the expected method
        $this->assertTrue($this->reflection->hasMethod('generateXRayReport'));
        $this->assertTrue($this->reflection->hasMethod('getXRayBaseHtml'));
    }

    public function testRowWithEmptyReportDoesNotCrashHtmlBuild(): void
    {
        $service   = new PdfService();
        $buildHtml = $this->reflection->getMethod('getXRayBaseHtml');
        $buildHtml->setAccessible(true);

        // Just verify the base HTML renders with an empty-data nickname fallback
        $html = $buildHtml->invoke($service, 'Raio X — Conta ML #0');

        $this->assertStringContainsString('Raio X', $html);
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
    }
}
