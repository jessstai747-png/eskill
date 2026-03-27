<?php

declare(strict_types=1);

/** @var array $productData */
/** @var array $seoData */

function normalizeExternalUrl(?string $url): string
{
    $trimmed = trim((string)$url);
    if ($trimmed === '') return '';
    if (str_starts_with($trimmed, 'data:') || str_starts_with($trimmed, 'blob:') || str_starts_with($trimmed, '#')) {
        return $trimmed;
    }
    if (str_starts_with($trimmed, '//')) {
        return 'https:' . $trimmed;
    }
    if (str_starts_with($trimmed, 'http://')) {
        return 'https://' . substr($trimmed, strlen('http://'));
    }
    return $trimmed;
}
?>

<div class="product-page" style="margin-top: 20px;">
    <div style="display: flex; gap: 40px; flex-wrap: wrap;">
        <!-- Image Gallery -->
        <div style="flex: 1; min-width: 300px;">
            <?php if (!empty($productData['pictures'])): ?>
                <div style="border: 1px solid #eee; border-radius: 8px; overflow: hidden; background: #fff; padding: 20px; text-align: center;">
                    <img src="<?= htmlspecialchars(normalizeExternalUrl($productData['pictures'][0]['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" 
                         alt="<?= htmlspecialchars($seoData['title']) ?>" 
                         style="max-width: 100%; height: auto;">
                </div>
                <?php if (count($productData['pictures']) > 1): ?>
                    <div style="display: flex; gap: 10px; margin-top: 10px; overflow-x: auto;">
                        <?php foreach (array_slice($productData['pictures'], 1, 4) as $pic): ?>
                            <img src="<?= htmlspecialchars(normalizeExternalUrl($pic['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width: 60px; height: 60px; object-fit: cover; border: 1px solid #eee; border-radius: 4px;">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Product Details -->
        <div style="flex: 1; min-width: 300px;">
            <h1 style="font-size: 1.8rem; margin-bottom: 10px; line-height: 1.3;">
                <?= htmlspecialchars($seoData['title']) ?>
            </h1>

            <div style="font-size: 2rem; font-weight: 300; margin: 20px 0;">
                R$ <?= number_format($seoData['price'], 2, ',', '.') ?>
            </div>

            <a href="<?= htmlspecialchars(normalizeExternalUrl($productData['permalink'] ?? '') ?: '#', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="nofollow noopener" class="btn-buy">
                Comprar no Mercado Livre
            </a>
            
            <div style="margin-top: 30px; font-size: 0.9rem; color: #666;">
                <p>🔒 Compra Garantida via Mercado Livre</p>
                <p>🚛 Envio Imediato</p>
            </div>
        </div>
    </div>

    <!-- Description -->
    <div style="margin-top: 50px; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
        <h2 style="font-size: 1.5rem; border-bottom: 2px solid #ffe600; padding-bottom: 10px; display: inline-block; margin-bottom: 20px;">
            Descrição do Produto
        </h2>
        
        <div style="line-height: 1.8; color: #444;">
            <!-- Render description, converting newlines to <br> if plain text, or keeping structure -->
            <?php 
                $desc = $productData['description'] ?? '';
                // Simple parser for basic formatting if raw text
                echo nl2br(htmlspecialchars($desc)); 
            ?>
        </div>
    </div>
</div>
