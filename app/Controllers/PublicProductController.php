<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;

class PublicProductController extends BaseController
{
    /**
     * Exibe a página pública do produto (SEO Friendly)
     * URL: /p/{slug}
     */
    public function show(string $slug): void
    {
        // CACHE LAYER: Handled by CacheMiddleware in index.php
        
        // 1. Tentar extrair o ID do slug (padrão: nome-do-produto-ID)
        // Ex: fone-bluetooth-rosa-123
        $parts = explode('-', $slug);
        $id = end($parts);

        if (!is_numeric($id)) {
            $this->notFound();
            return;
        }

        // 2. Buscar produto no banco (somente itens ativos)
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM items WHERE id = :id AND status = 'active'");
        $stmt->execute(['id' => $id]);
        $item = $stmt->fetch();

        if (!$item) {
            $this->notFound();
            return;
        }

        $productData = json_decode($item['data'], true);

        // 3. Preparar dados para SEO
        $seoData = [
            'title' => $productData['title'] ?? 'Produto',
            'description' => $productData['plain_text_description'] ?? substr(strip_tags($productData['description'] ?? ''), 0, 160),
            'image' => $productData['pictures'][0]['url'] ?? '',
            'price' => $productData['price'] ?? 0,
            'currency' => 'BRL',
            'availability' => ($productData['available_quantity'] ?? 0) > 0 ? 'InStock' : 'OutOfStock',
            'url' => "https://" . ($_ENV['APP_DOMAIN'] ?? 'eskill.com.br') . "/p/{$slug}"
        ];

        // 4. Renderizar View Pública
        $pageTitle = $seoData['title'];
        
        ob_start();
        require __DIR__ . '/../Views/public/product.php';
        $content = ob_get_clean();
        
        ob_start();
        require __DIR__ . '/../Views/layouts/public.php';
        $finalHtml = ob_get_clean();

        // Cache saved via Middleware
        
        echo $finalHtml;
    }

    private function notFound(): void
    {
        http_response_code(404);
        require __DIR__ . '/../Views/errors/404.php';
        exit;
    }
}
