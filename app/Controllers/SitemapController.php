<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;

class SitemapController extends BaseController
{
    /**
     * Gera o sitemap.xml dinâmico (Cached 1h)
     */
    public function index(): void
    {
        $cache = new \App\Services\CacheManagerService();
        $cacheKey = 'sitemap_xml_index';

        // Try cache first
        $cachedEntry = $cache->get($cacheKey, 'sitemap');
        if ($cachedEntry && isset($cachedEntry['content'])) {
            header('Content-Type: application/xml; charset=utf-8');
            echo $cachedEntry['content'];
            return;
        }

        // Generate if miss
        $db = Database::getInstance();

        // Buscar itens ativos que devem aparecer no Google
        // Assumindo que todos os ativos são públicos por padrão ou verificando flag se existir
        $stmt = $db->query("SELECT id, data, updated_at FROM items WHERE status = 'active' ORDER BY updated_at DESC LIMIT 5000");
        $items = $stmt->fetchAll();

        ob_start();
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        $baseUrl = rtrim($_ENV['APP_URL'] ?? 'https://eskill.com.br', '/');

        // Home
        echo '<url>';
        echo "<loc>{$baseUrl}/</loc>";
        echo '<changefreq>daily</changefreq>';
        echo '<priority>1.0</priority>';
        echo '</url>';

        foreach ($items as $item) {
            $data = json_decode($item['data'], true);
            $slug = $this->generateSlug($data['title'] ?? 'produto', $item['id']);
            $date = date('c', strtotime($item['updated_at']));

            echo '<url>';
            echo "<loc>{$baseUrl}/p/{$slug}</loc>";
            echo "<lastmod>{$date}</lastmod>";
            echo '<changefreq>weekly</changefreq>';
            echo '<priority>0.8</priority>';
            echo '</url>';
        }

        echo '</urlset>';

        $xmlContent = ob_get_clean();

        // Store in cache (1 hour)
        $cache->set($cacheKey, ['content' => $xmlContent], 'sitemap');

        header('Content-Type: application/xml; charset=utf-8');
        echo $xmlContent;
    }

    private function generateSlug(string $title, int $id): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        return "{$slug}-{$id}";
    }
}
