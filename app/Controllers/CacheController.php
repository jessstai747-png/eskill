<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\AdvancedCacheService;
use App\Services\CacheService;
use App\Services\UserService;

class CacheController
{
    private AdvancedCacheService $advancedCache;
    private CacheService $cacheService;
    private UserService $userService;
    private Request $request;
    
    public function __construct(UserService $userService)
    {
        $this->request = new Request();
        $this->cacheService = new CacheService();
        $this->advancedCache = new AdvancedCacheService('file');
        $this->userService = $userService;
    }
    
    /**
     * Exibe dashboard de cache
     */
    public function index(): void
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $pageTitle = 'Cache do Sistema';
        $activePage = 'cache';

        ob_start();
        require_once __DIR__ . '/../Views/dashboard/cache/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }
    
    /**
     * Retorna estatísticas de cache (API)
     */
    public function statistics(): void
    {
        header('Content-Type: application/json');
        
        try {
            $stats = $this->advancedCache->getStats();
            
            // Adicionar informações do diretório de cache
            $cacheDir = __DIR__ . '/../../storage/cache';
            $totalSize = $this->getDirectorySize($cacheDir);
            
            $stats['total_size'] = $this->formatBytes($totalSize);
            $stats['total_size_bytes'] = $totalSize;
            $stats['cache_directory'] = $cacheDir;
            
            echo json_encode($stats);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Lista todos os itens do cache (API)
     */
    public function list(): void
    {
        header('Content-Type: application/json');
        
        try {
            $cacheDir = __DIR__ . '/../../storage/cache';
            $items = $this->listCacheItems($cacheDir);
            
            // Paginação
            $page = $this->request->getInt('page', 1);
            $perPage = $this->request->getInt('per_page', 50);
            $offset = ($page - 1) * $perPage;
            
            $total = count($items);
            $items = array_slice($items, $offset, $perPage);
            
            echo json_encode([
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'items' => $items
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Remove item específico do cache (API)
     */
    public function delete(): void
    {
        header('Content-Type: application/json');
        
        $key = $this->request->post('key');
        
        if (!$key) {
            http_response_code(400);
            echo json_encode(['error' => 'Key parameter is required']);
            return;
        }
        
        try {
            $result = $this->advancedCache->delete($key);
            echo json_encode(['success' => $result]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Busca valor de cache (API)
     */
    public function get(): void
    {
        header('Content-Type: application/json');
        
        $key = $this->request->get('key');
        
        if (!$key) {
            http_response_code(400);
            echo json_encode(['error' => 'Key parameter is required']);
            return;
        }
        
        try {
            $value = $this->advancedCache->get($key);
            echo json_encode([
                'key' => $key,
                'exists' => $value !== null,
                'value' => $value
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Invalida caches por tag (API)
     */
    public function invalidateTags(): void
    {
        header('Content-Type: application/json');
        
        $tags = $this->request->post('tags');
        
        if (!$tags) {
            http_response_code(400);
            echo json_encode(['error' => 'Tags parameter is required']);
            return;
        }
        
        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }
        
        try {
            $removed = $this->advancedCache->invalidateTags($tags);
            echo json_encode([
                'success' => true,
                'removed' => $removed,
                'tags' => $tags
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Limpa o cache (método legado)
     */
    public function clear(): void
    {
        try {
            $this->cacheService->clear();
            $this->advancedCache->clear();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Cache limpo com sucesso'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao limpar cache: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Limpa cache expirado (API)
     */
    public function clearExpired(): void
    {
        header('Content-Type: application/json');
        
        try {
            $removed = $this->advancedCache->clearExpired();
            echo json_encode([
                'success' => true,
                'removed' => $removed,
                'message' => "$removed cache items removed"
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Lista todos os itens do cache recursivamente
     */
    private function listCacheItems(string $dir): array
    {
        $items = [];
        
        if (!is_dir($dir)) {
            return $items;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'cache') {
                $content = @file_get_contents($file->getPathname());
                
                if ($content) {
                    // Tentar descomprimir
                    $decompressed = @gzdecode($content);
                    if ($decompressed !== false) {
                        $content = $decompressed;
                    }
                    
                    $data = @json_decode($content, true);
                    
                    if ($data && isset($data['key'])) {
                        $items[] = [
                            'key' => $data['key'],
                            'expires_at' => $data['expires_at'] ?? null,
                            'tags' => $data['tags'] ?? [],
                            'size' => $file->getSize(),
                            'size_formatted' => $this->formatBytes($file->getSize()),
                            'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                            'is_expired' => isset($data['expires_at']) && $data['expires_at'] < time()
                        ];
                    }
                }
            }
        }
        
        // Ordenar por data de modificação (mais recente primeiro)
        usort($items, function($a, $b) {
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
        
        return $items;
    }
    
    /**
     * Calcula tamanho de diretório recursivamente
     */
    private function getDirectorySize(string $dir): int
    {
        $size = 0;
        
        if (!is_dir($dir)) {
            return $size;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            $size += $file->getSize();
        }
        
        return $size;
    }
    
    /**
     * Formata bytes para formato legível
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
