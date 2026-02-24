<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use Tests\TestCase;
use App\Helpers\MercadoLivreHelper;

/**
 * @covers \App\Helpers\MercadoLivreHelper
 */
class MercadoLivreHelperTest extends TestCase
{
    // =============================
    // formatItemId()
    // =============================

    public function testFormatItemIdInsertsDash(): void
    {
        $this->assertSame('MLB-1234567890', MercadoLivreHelper::formatItemId('MLB1234567890'));
    }

    public function testFormatItemIdPreservesAlreadyFormatted(): void
    {
        $this->assertSame('MLB-1234567890', MercadoLivreHelper::formatItemId('MLB-1234567890'));
    }

    public function testFormatItemIdHandlesOtherSites(): void
    {
        $this->assertSame('MLA-5678', MercadoLivreHelper::formatItemId('MLA5678'));
        $this->assertSame('MLU-99', MercadoLivreHelper::formatItemId('MLU99'));
        $this->assertSame('MCO-12345', MercadoLivreHelper::formatItemId('MCO12345'));
    }

    public function testFormatItemIdHandlesUnrecognizedFormat(): void
    {
        $this->assertSame('invalid-id', MercadoLivreHelper::formatItemId('invalid-id'));
        $this->assertSame('123456', MercadoLivreHelper::formatItemId('123456'));
        $this->assertSame('', MercadoLivreHelper::formatItemId(''));
    }

    public function testFormatItemIdTrimsWhitespace(): void
    {
        $this->assertSame('MLB-1234567890', MercadoLivreHelper::formatItemId('  MLB1234567890  '));
    }

    // =============================
    // itemUrl()
    // =============================

    public function testItemUrlBuildsCorrectUrl(): void
    {
        $this->assertSame(
            'https://produto.mercadolivre.com.br/MLB-1234567890',
            MercadoLivreHelper::itemUrl('MLB1234567890')
        );
    }

    public function testItemUrlWithAlreadyFormattedId(): void
    {
        $this->assertSame(
            'https://produto.mercadolivre.com.br/MLB-1234567890',
            MercadoLivreHelper::itemUrl('MLB-1234567890')
        );
    }

    public function testItemUrlWithOtherSite(): void
    {
        $this->assertSame(
            'https://produto.mercadolivre.com.br/MLA-5678',
            MercadoLivreHelper::itemUrl('MLA5678')
        );
    }

    // =============================
    // extractItemId()
    // =============================

    public function testExtractItemIdFromProdutoUrl(): void
    {
        $this->assertSame(
            'MLB-1234567890',
            MercadoLivreHelper::extractItemId('https://produto.mercadolivre.com.br/MLB-1234567890-titulo-do-anuncio')
        );
    }

    public function testExtractItemIdFromLegacyUrl(): void
    {
        $this->assertSame(
            'MLB-1234567890',
            MercadoLivreHelper::extractItemId('https://www.mercadolivre.com.br/p/MLB1234567890')
        );
    }

    public function testExtractItemIdFromInvalidUrl(): void
    {
        $this->assertNull(MercadoLivreHelper::extractItemId('https://www.google.com'));
        $this->assertNull(MercadoLivreHelper::extractItemId(''));
    }
}
