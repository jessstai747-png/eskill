<?php

namespace App\Controllers;

/**
 * AssetController
 *
 * Serve assets estáticos via Router quando eles não estão disponíveis no webroot.
 * Útil para deploys onde apenas /public é exposto e o dashboard referencia assets
 * que estão dentro de /app.
 */
class AssetController
{
    private function serveFile(string $absolutePath, string $contentType): void
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Asset não encontrado';
            return;
        }

        $mtime = @filemtime($absolutePath) ?: time();
        $lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';

        // Cache simples com validação
        header('Content-Type: ' . $contentType);
        header('Cache-Control: public, max-age=3600');
        header('Last-Modified: ' . $lastModified);

        if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if ($ifModifiedSince !== false && $ifModifiedSince >= $mtime) {
                http_response_code(304);
                return;
            }
        }

        // Evita qualquer output buffering inesperado quebrar assets
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }

        readfile($absolutePath);
    }

    public function seoKillerCss(): void
    {
        $path = __DIR__ . '/../../public/assets/css/seo-killer.css';
        $this->serveFile($path, 'text/css; charset=utf-8');
    }

    public function seoKillerUtilsJs(): void
    {
        $path = __DIR__ . '/../../public/assets/js/seo-killer-utils.js';
        $this->serveFile($path, 'application/javascript; charset=utf-8');
    }

    public function seoKillerJs(): void
    {
        $path = __DIR__ . '/../../public/assets/js/seo-killer.js';
        $this->serveFile($path, 'application/javascript; charset=utf-8');
    }
}
