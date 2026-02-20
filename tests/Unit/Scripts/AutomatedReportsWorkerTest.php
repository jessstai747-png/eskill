<?php

declare(strict_types=1);

namespace Tests\Unit\Scripts;

use Tests\TestCase;

/**
 * Validates CLI contract for automated reports worker script.
 */
class AutomatedReportsWorkerTest extends TestCase
{
    public function testAutomatedReportsWorkerRejectsInvalidReportType(): void
    {
        $script = escapeshellarg(__DIR__ . '/../../../bin/automated-reports-worker.php');
        $cmd = "php {$script} invalid_type 2>&1";

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        $text = implode("\n", $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Tipo inválido', $text);
    }

    public function testWeeklyReportScriptExistsAndHasStrictTypes(): void
    {
        $path = __DIR__ . '/../../../bin/weekly-report.php';
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('declare(strict_types=1);', $content);
    }
}
