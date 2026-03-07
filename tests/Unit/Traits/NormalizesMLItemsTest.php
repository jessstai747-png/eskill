<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Traits\NormalizesMLItems;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Traits\NormalizesMLItems
 */
class NormalizesMLItemsTest extends TestCase
{
    use NormalizesMLItems;

    // ─── extractDescriptionText ───────────────────────────

    public function testExtractDescriptionTextFromString(): void
    {
        $this->assertSame("Hello world", self::extractDescriptionText("Hello world"));
    }

    public function testExtractDescriptionTextFromArrayPlainText(): void
    {
        $desc = ["plain_text" => "Plain version", "text" => "<p>HTML version</p>"];
        $this->assertSame("Plain version", self::extractDescriptionText($desc));
    }

    public function testExtractDescriptionTextFromArrayTextFallback(): void
    {
        $desc = ["text" => "<p>HTML only</p>"];
        $this->assertSame("<p>HTML only</p>", self::extractDescriptionText($desc));
    }

    public function testExtractDescriptionTextFromEmptyArray(): void
    {
        $this->assertSame("", self::extractDescriptionText([]));
    }

    public function testExtractDescriptionTextFromNull(): void
    {
        $this->assertSame("", self::extractDescriptionText(null));
    }

    public function testExtractDescriptionTextFromInteger(): void
    {
        $this->assertSame("", self::extractDescriptionText(123));
    }

    public function testExtractDescriptionTextFromBool(): void
    {
        $this->assertSame("", self::extractDescriptionText(true));
    }

    // ─── extractMLAttribute ──────────────────────────────

    public function testExtractMLAttributeFoundBrand(): void
    {
        $item = [
            "attributes" => [
                ["id" => "COLOR", "value_name" => "Red"],
                ["id" => "BRAND", "value_name" => "AWA"],
            ]
        ];
        $this->assertSame("AWA", self::extractMLAttribute($item, "BRAND"));
    }

    public function testExtractMLAttributeNotFound(): void
    {
        $item = ["attributes" => [["id" => "COLOR", "value_name" => "Blue"]]];
        $this->assertNull(self::extractMLAttribute($item, "BRAND"));
    }

    public function testExtractMLAttributeNoAttributes(): void
    {
        $this->assertNull(self::extractMLAttribute([], "BRAND"));
    }

    public function testExtractMLAttributeNullValueName(): void
    {
        $item = ["attributes" => [["id" => "BRAND"]]];
        $this->assertNull(self::extractMLAttribute($item, "BRAND"));
    }

    public function testExtractMLAttributeMissingId(): void
    {
        $item = ["attributes" => [["value_name" => "Honda"]]];
        $this->assertNull(self::extractMLAttribute($item, "BRAND"));
    }

    public function testExtractMLAttributeModel(): void
    {
        $item = [
            "attributes" => [
                ["id" => "MODEL", "value_name" => "CG 160 Titan"],
            ]
        ];
        $this->assertSame("CG 160 Titan", self::extractMLAttribute($item, "MODEL"));
    }

    // ─── normalizeMLItem ─────────────────────────────────

    public function testNormalizeMLItemFullPayload(): void
    {
        $mlItem = [
            "id" => "MLB123456789",
            "title" => "Bagageiro CG 160 Titan",
            "description" => ["plain_text" => "Desc test", "text" => "<p>desc</p>"],
            "category_id" => "MLB1234",
            "price" => 199.90,
            "original_price" => 249.90,
            "currency_id" => "BRL",
            "available_quantity" => 50,
            "sold_quantity" => 120,
            "pictures" => [
                ["id" => "img1", "url" => "http://img.com/1.jpg", "secure_url" => "https://img.com/1.jpg"],
            ],
            "attributes" => [
                ["id" => "BRAND", "name" => "Marca", "value_name" => "AWA"],
                ["id" => "MODEL", "name" => "Modelo", "value_name" => "CG 160"],
            ],
            "shipping" => ["free_shipping" => true],
            "status" => "active",
            "permalink" => "https://www.mercadolivre.com.br/item",
            "health" => 0.9,
        ];

        $result = $this->normalizeMLItem($mlItem);

        $this->assertSame("MLB123456789", $result["id"]);
        $this->assertSame("Bagageiro CG 160 Titan", $result["title"]);
        $this->assertSame("Desc test", $result["description"]);
        $this->assertIsArray($result["description_data"]);
        $this->assertSame("MLB1234", $result["category_id"]);
        $this->assertSame("AWA", $result["brand"]);
        $this->assertSame("CG 160", $result["model"]);
        $this->assertSame(199.90, $result["price"]);
        $this->assertSame(249.90, $result["original_price"]);
        $this->assertSame("BRL", $result["currency_id"]);
        $this->assertSame(50, $result["available_quantity"]);
        $this->assertSame(120, $result["sold_quantity"]);
        $this->assertCount(1, $result["images"]);
        $this->assertSame("http://img.com/1.jpg", $result["images"][0]["url"]);
        $this->assertCount(2, $result["attributes"]);
        $this->assertSame("BRAND", $result["attributes"][0]["id"]);
        $this->assertSame("AWA", $result["attributes"][0]["value"]);
        $this->assertTrue($result["free_shipping"]);
        $this->assertSame("active", $result["status"]);
        $this->assertSame("https://www.mercadolivre.com.br/item", $result["permalink"]);
        $this->assertSame(0.9, $result["health"]);
    }

    public function testNormalizeMLItemMinimalPayload(): void
    {
        $result = $this->normalizeMLItem([]);

        $this->assertSame("", $result["id"]);
        $this->assertSame("", $result["title"]);
        $this->assertSame("", $result["description"]);
        $this->assertSame([], $result["description_data"]);
        $this->assertSame("", $result["brand"]);
        $this->assertSame("", $result["model"]);
        $this->assertSame(0.0, $result["price"]);
        $this->assertSame(0, $result["available_quantity"]);
        $this->assertSame(0, $result["sold_quantity"]);
        $this->assertSame([], $result["images"]);
        $this->assertSame([], $result["attributes"]);
        $this->assertFalse($result["free_shipping"]);
        $this->assertSame("unknown", $result["status"]);
        $this->assertNull($result["health"]);
    }

    public function testNormalizeMLItemDescriptionStringPreserved(): void
    {
        $result = $this->normalizeMLItem([
            "description" => "Plain string desc"
        ]);
        $this->assertSame("Plain string desc", $result["description"]);
        $this->assertSame([], $result["description_data"]);
    }

    public function testNormalizeMLItemOriginalPriceFallsBackToPrice(): void
    {
        $result = $this->normalizeMLItem(["price" => 100.0]);
        $this->assertSame(100.0, $result["original_price"]);
    }

    public function testNormalizeMLItemImageUsesSecureUrlFallback(): void
    {
        $result = $this->normalizeMLItem([
            "pictures" => [
                ["secure_url" => "https://secure.img/1.jpg", "id" => "img1"],
            ]
        ]);
        $this->assertSame("https://secure.img/1.jpg", $result["images"][0]["url"]);
    }

    public function testNormalizeMLItemAttributesMapped(): void
    {
        $result = $this->normalizeMLItem([
            "attributes" => [
                ["id" => "EAN", "name" => "EAN", "value_name" => "7890001234567"],
            ]
        ]);
        $this->assertSame("EAN", $result["attributes"][0]["id"]);
        $this->assertSame("EAN", $result["attributes"][0]["name"]);
        $this->assertSame("7890001234567", $result["attributes"][0]["value"]);
    }

    public function testNormalizeMLItemShippingMissing(): void
    {
        $result = $this->normalizeMLItem([]);
        $this->assertFalse($result["free_shipping"]);
    }
}
