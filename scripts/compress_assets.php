<?php
/**
 * Script de compressão de assets (CSS/JS)
 * 
 * Este script minifica arquivos CSS e JavaScript para melhor performance
 * Execute: php scripts/compress_assets.php
 */

// Carregar autoload
require_once __DIR__ . '/../vendor/autoload.php';

class AssetCompressor
{
    private string $publicDir;
    private array $compressedFiles = [];
    
    public function __construct()
    {
        $this->publicDir = __DIR__ . '/../public';
    }
    
    /**
     * Minifica CSS
     */
    private function minifyCSS(string $css): string
    {
        // Remover comentários
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remover espaços em branco desnecessários
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        
        // Remover espaços antes de fechamento
        $css = preg_replace('/\s+}/', '}', $css);
        $css = preg_replace('/}\s+/', '}', $css);
        
        // Remover último ponto e vírgula
        $css = preg_replace('/;}/', '}', $css);
        
        return trim($css);
    }
    
    /**
     * Minifica JavaScript
     */
    private function minifyJS(string $js): string
    {
        // Remover comentários de linha
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remover comentários de bloco
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);
        
        // Remover espaços em branco desnecessários
        $js = preg_replace('/\s+/', ' ', $js);
        $js = preg_replace('/\s*([{}:;,\[\]()=+\-*\/<>!&|])\s*/', '$1', $js);
        
        // Remover espaços antes de ponto e vírgula
        $js = preg_replace('/\s+;/', ';', $js);
        
        return trim($js);
    }
    
    /**
     * Processa arquivos CSS
     */
    public function compressCSS(): array
    {
        $cssDir = $this->publicDir . '/css';
        
        if (!is_dir($cssDir)) {
            return ['error' => 'Diretório CSS não encontrado'];
        }
        
        $files = glob($cssDir . '/*.css');
        $compressed = [];
        
        foreach ($files as $file) {
            // Pular arquivos já minificados
            if (strpos(basename($file), '.min.') !== false) {
                continue;
            }
            
            $content = file_get_contents($file);
            $minified = $this->minifyCSS($content);
            
            $minFile = str_replace('.css', '.min.css', $file);
            file_put_contents($minFile, $minified);
            
            $compressed[] = [
                'original' => basename($file),
                'minified' => basename($minFile),
                'original_size' => strlen($content),
                'minified_size' => strlen($minified),
                'savings' => round((1 - strlen($minified) / strlen($content)) * 100, 2) . '%',
            ];
        }
        
        return $compressed;
    }
    
    /**
     * Processa arquivos JavaScript
     */
    public function compressJS(): array
    {
        $jsDir = $this->publicDir . '/js';
        
        if (!is_dir($jsDir)) {
            return ['error' => 'Diretório JS não encontrado'];
        }
        
        $files = glob($jsDir . '/*.js');
        $compressed = [];
        
        foreach ($files as $file) {
            // Pular arquivos já minificados
            if (strpos(basename($file), '.min.') !== false) {
                continue;
            }
            
            // Pular arquivos de bibliotecas externas
            if (strpos(basename($file), 'vendor') !== false || 
                strpos(basename($file), 'lib') !== false) {
                continue;
            }
            
            $content = file_get_contents($file);
            $minified = $this->minifyJS($content);
            
            $minFile = str_replace('.js', '.min.js', $file);
            file_put_contents($minFile, $minified);
            
            $compressed[] = [
                'original' => basename($file),
                'minified' => basename($minFile),
                'original_size' => strlen($content),
                'minified_size' => strlen($minified),
                'savings' => round((1 - strlen($minified) / strlen($content)) * 100, 2) . '%',
            ];
        }
        
        return $compressed;
    }
    
    /**
     * Processa todos os assets
     */
    public function compressAll(): array
    {
        return [
            'css' => $this->compressCSS(),
            'js' => $this->compressJS(),
        ];
    }
}

// Executar compressão
try {
    echo "[" . date('Y-m-d H:i:s') . "] Iniciando compressão de assets...\n\n";
    
    $compressor = new AssetCompressor();
    $results = $compressor->compressAll();
    
    if (isset($results['css']) && !isset($results['css']['error'])) {
        echo "CSS comprimido:\n";
        foreach ($results['css'] as $file) {
            echo "  - {$file['original']} → {$file['minified']} ({$file['savings']} economia)\n";
        }
        echo "\n";
    }
    
    if (isset($results['js']) && !isset($results['js']['error'])) {
        echo "JavaScript comprimido:\n";
        foreach ($results['js'] as $file) {
            echo "  - {$file['original']} → {$file['minified']} ({$file['savings']} economia)\n";
        }
        echo "\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Compressão concluída com sucesso.\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
