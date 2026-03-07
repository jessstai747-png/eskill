<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\MercadoLivreHelper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Helpers\MercadoLivreHelper
 */
class MercadoLivreHelperTest extends TestCase
{
    public function testFormatItemIdInsertsDash(): void
    {
        $this->assertSame("MLB-1234567890", MercadoLivreHelper::formatItemId("MLB1234567890"));
    }

    public function testFormatItemIdAlreadyFormatted(): void
    {
        $this->assertSame("MLB-1234", MercadoLivreHelper::formatItemId("MLB-1234"));
    }

    public function testFormatItemIdDifferentSitePrefixes(): void
    {
        $this->assertSame("MLA-5678", MercadoLivreHelper::formatItemId("MLA5678"));
        $this->assertSame("MLU-9999", MercadoLivreHelper::formatItemId("MLU9999"));
        $this->assertSame("MLC-1001", MercadoLivreHelper::formatItemId("MLC1001"));
    }

    public function testFormatItemIdTrimsWhitespace(): void
    {
        $this->assertSame("MLB-123", MercadoLivreHelper::formatItemId("  MLB123  "));
    }

    public function testFormatItemIdUnrecognizedReturnsAsIs(): void
    {
        $this->assertSame("invalid", MercadoLivreHelper::formatItemId("invalid"));
        $this->assertSame("123", MercadoLivreHelper::formatItemId("123"));
        $this->assertSame("ML1234", MercadoLivreHelper::formatItemId("ML1234"));
    }

    public function testFormatItemIdEmpty(): void
    {
        $this->assertSame("", MercadoLivreHelper::formatItemId(""));
    }

    public function testFormatItemIdLongNumber(): void
    {
        $long = str_repeat("9", 20);
        $this->assertSame("MLB-" . $long, MercadoLivreHelper::formatItemId("MLB" . $long));
    }

    public function testItemUrlBuildsCorrectUrl(): void
    {
        $this->assertSame(
            "https://produto.mercadolivre.com.br/MLB-1234567890",
            MercadoLivreHelper::itemUrl("MLB1234567890")
        );
    }

    public function testItemUrlWithDashAlready(): void
    {
        $this->assertSame(
            "https://produto.mercadolivre.com.br/MLB-999",
            MercadoLivreHelper::itemUrl("MLB-999")
        );
    }

    public function testItemUrlDifferentSites(): void
    {
        $this->assertSame(
            "https://produto.mercadolivre.com.br/MLA-5678",
            MercadoLivreHelper::itemUrl("MLA5678")
        );
    }

    public function testExtractItemIdFromProductUrl(): void
    {
        $url = "https://produto.mercadolivre.com.br/MLB-1234567890-titulo-do-anuncio";
        $this->assertSame("MLB-1234567890", MercadoLivreHelper::extractItemId($url));
    }

    public function testExtractItemIdFromCatalogUrl(): void
    {
        $url = "https://www.mercadolivre.com.br/p/MLB1234567890";
        $this->assertSame("MLB-1234567890", MercadoLivreHelper::extractItemId($url));
    }

    public function testExtractItemIdWithDash(): void
    {
        $url = "https://produto.mercadolivre.com.br/MLB-999-some-title";
        $this->assertSame("MLB-999", MercadoLivreHelper::extractItemId($url));
    }

    public function testExtractItemIdFromMLA(): void
    {
        $url = "https://articulo.mercadolibre.com.ar/MLA-12345-title";
        $this->assertSame("MLA-12345", MercadoLivreHelper::extractItemId($url));
    }

    public function testExtractItemIdReturnsNullOnNoMatch(): void
    {
        $this->assertNull(MercadoLivreHelper::extractItemId("https://www.google.com"));
        $this->assertNull(MercadoLivreHelper::extractItemId("no url here"));
        $this->assertNull(MercadoLivreHelper::extractItemId(""));
    }

    public function testExtractItemIdFormatsWithDash(): void
    {
        $url = "https://produto.mercadolivre.com.br/MLB1234567890";
        $this->assertSame("MLB-1234567890", MercadoLivreHelper::extractItemId($url));
    }

    public function testExtractItemIdFromPlainId(): void
    {
        $this->assertSame("MLB-42", MercadoLivreHelper::extractItemId("MLB42"));
    }

    public function testRoundTripIdFormatting(): void
    {
        $original = "MLB1234567890";
        $formatted = MercadoLivreHelper::formatItemId($original);
        $url = MercadoLivreHelper::itemUrl($original);
        $extracted = MercadoLivreHelper::extractItemId($url);
        $this->assertSame($formatted, $extracted);
    }
}