<?php

namespace App\Services;

class RenderHarness
{
    private string $outputDir;
    
    public function __construct()
    {
        $this->outputDir = __DIR__ . '/../../storage/renders';
    }
    
    public static function isEnabled(): bool
    {
        return false;
    }
    
    public function render(array $input): array
    {
        throw new \Exception('Render harness desativado');
    }
    
    public function getJobStatus(string $jobId): array
    {
        throw new \Exception('Render harness desativado');
    }
    
    public function cleanup(int $olderThanSeconds = 3600): int
    {
        return 0;
    }
}
