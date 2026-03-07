<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MercadoLivre;

use App\Services\MercadoLivre\CategoriesApiException;
use App\Services\MercadoLivre\CategoryChildDTO;
use App\Services\MercadoLivre\CategoryDetailDTO;
use App\Services\MercadoLivre\CategoryNodeDTO;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\MercadoLivre\CategoryNodeDTO
 * @covers \App\Services\MercadoLivre\CategoryChildDTO
 * @covers \App\Services\MercadoLivre\CategoryDetailDTO
 * @covers \App\Services\MercadoLivre\CategoriesApiException
 */
class CategoryDTOsTest extends TestCase
{
    // --- CategoryNodeDTO ---

    public function testNodeDTOFromArrayValid(): void
    {
        $dto = CategoryNodeDTO::fromArray(["id" => "MLB1234", "name" => "Acessorios"]);
        $this->assertSame("MLB1234", $dto->id);
        $this->assertSame("Acessorios", $dto->name);
    }

    public function testNodeDTOTrimsWhitespace(): void
    {
        $dto = CategoryNodeDTO::fromArray(["id" => "  MLB999  ", "name" => "  Bagageiros  "]);
        $this->assertSame("MLB999", $dto->id);
        $this->assertSame("Bagageiros", $dto->name);
    }

    public function testNodeDTOCastsToString(): void
    {
        $dto = CategoryNodeDTO::fromArray(["id" => 12345, "name" => 67890]);
        $this->assertSame("12345", $dto->id);
        $this->assertSame("67890", $dto->name);
    }

    public function testNodeDTOThrowsOnEmptyId(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryNodeDTO::fromArray(["id" => "", "name" => "Valid"]);
    }

    public function testNodeDTOThrowsOnEmptyName(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryNodeDTO::fromArray(["id" => "MLB1", "name" => ""]);
    }

    public function testNodeDTOThrowsOnMissingId(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryNodeDTO::fromArray(["name" => "Valid"]);
    }

    public function testNodeDTOThrowsOnMissingName(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryNodeDTO::fromArray(["id" => "MLB1"]);
    }

    public function testNodeDTOThrowsOnWhitespaceOnlyId(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryNodeDTO::fromArray(["id" => "   ", "name" => "Valid"]);
    }

    public function testNodeDTOConstructorPublicProps(): void
    {
        $dto = new CategoryNodeDTO("MLB1", "Test");
        $this->assertSame("MLB1", $dto->id);
        $this->assertSame("Test", $dto->name);
    }

    // --- CategoryChildDTO ---

    public function testChildDTOFromArrayValid(): void
    {
        $dto = CategoryChildDTO::fromArray([
            "id" => "MLB5678",
            "name" => "Bagageiros",
            "total_items_in_this_category" => 1500,
        ]);
        $this->assertSame("MLB5678", $dto->id);
        $this->assertSame("Bagageiros", $dto->name);
        $this->assertSame(1500, $dto->totalItemsInThisCategory);
    }

    public function testChildDTODefaultTotalItems(): void
    {
        $dto = CategoryChildDTO::fromArray(["id" => "MLB999", "name" => "Retrovisores"]);
        $this->assertSame(0, $dto->totalItemsInThisCategory);
    }

    public function testChildDTOCastsTotalItemsToInt(): void
    {
        $dto = CategoryChildDTO::fromArray([
            "id" => "MLB100",
            "name" => "Capas",
            "total_items_in_this_category" => "42",
        ]);
        $this->assertSame(42, $dto->totalItemsInThisCategory);
    }

    public function testChildDTOTrimsWhitespace(): void
    {
        $dto = CategoryChildDTO::fromArray([
            "id" => "  MLB200  ",
            "name" => "  Protecoes  ",
            "total_items_in_this_category" => 10,
        ]);
        $this->assertSame("MLB200", $dto->id);
        $this->assertSame("Protecoes", $dto->name);
    }

    public function testChildDTOThrowsOnEmptyId(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryChildDTO::fromArray(["id" => "", "name" => "Valid"]);
    }

    public function testChildDTOThrowsOnEmptyName(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryChildDTO::fromArray(["id" => "MLB1", "name" => ""]);
    }

    public function testChildDTOThrowsOnEmptyPayload(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryChildDTO::fromArray([]);
    }

    public function testChildDTOConstructorPublicProps(): void
    {
        $dto = new CategoryChildDTO("MLB1", "Test", 99);
        $this->assertSame("MLB1", $dto->id);
        $this->assertSame("Test", $dto->name);
        $this->assertSame(99, $dto->totalItemsInThisCategory);
    }

    // --- CategoryDetailDTO ---

    public function testDetailDTOFromArrayComplete(): void
    {
        $dto = CategoryDetailDTO::fromArray([
            "id" => "MLB9999",
            "name" => "Acessorios para Motos",
            "picture" => "https://example.com/cat.jpg",
            "permalink" => "https://mercadolivre.com.br/cat",
            "total_items_in_this_category" => 5000,
            "path_from_root" => [
                ["id" => "MLB1", "name" => "Raiz"],
                ["id" => "MLB2", "name" => "Veiculos"],
            ],
            "children_categories" => [
                ["id" => "MLB10", "name" => "Bagageiros", "total_items_in_this_category" => 200],
                ["id" => "MLB11", "name" => "Baus", "total_items_in_this_category" => 150],
            ],
        ]);

        $this->assertSame("MLB9999", $dto->id);
        $this->assertSame("Acessorios para Motos", $dto->name);
        $this->assertSame("https://example.com/cat.jpg", $dto->picture);
        $this->assertSame("https://mercadolivre.com.br/cat", $dto->permalink);
        $this->assertSame(5000, $dto->totalItemsInThisCategory);

        $this->assertCount(2, $dto->pathFromRoot);
        $this->assertInstanceOf(CategoryNodeDTO::class, $dto->pathFromRoot[0]);
        $this->assertSame("MLB1", $dto->pathFromRoot[0]->id);
        $this->assertSame("Raiz", $dto->pathFromRoot[0]->name);
        $this->assertSame("MLB2", $dto->pathFromRoot[1]->id);

        $this->assertCount(2, $dto->childrenCategories);
        $this->assertInstanceOf(CategoryChildDTO::class, $dto->childrenCategories[0]);
        $this->assertSame("MLB10", $dto->childrenCategories[0]->id);
        $this->assertSame(200, $dto->childrenCategories[0]->totalItemsInThisCategory);
    }

    public function testDetailDTOMinimalPayload(): void
    {
        $dto = CategoryDetailDTO::fromArray(["id" => "MLB1", "name" => "Root"]);

        $this->assertSame("MLB1", $dto->id);
        $this->assertSame("Root", $dto->name);
        $this->assertNull($dto->picture);
        $this->assertNull($dto->permalink);
        $this->assertSame(0, $dto->totalItemsInThisCategory);
        $this->assertEmpty($dto->pathFromRoot);
        $this->assertEmpty($dto->childrenCategories);
    }

    public function testDetailDTOTrimsWhitespace(): void
    {
        $dto = CategoryDetailDTO::fromArray(["id" => "  MLB42  ", "name" => "  Retrovisor  "]);
        $this->assertSame("MLB42", $dto->id);
        $this->assertSame("Retrovisor", $dto->name);
    }

    public function testDetailDTOSkipsNonArrayPathEntries(): void
    {
        $dto = CategoryDetailDTO::fromArray([
            "id" => "MLB1",
            "name" => "Test",
            "path_from_root" => [
                ["id" => "MLB2", "name" => "Valid"],
                "invalid_string_entry",
                null,
                42,
            ],
        ]);
        $this->assertCount(1, $dto->pathFromRoot);
        $this->assertSame("MLB2", $dto->pathFromRoot[0]->id);
    }

    public function testDetailDTOSkipsNonArrayChildEntries(): void
    {
        $dto = CategoryDetailDTO::fromArray([
            "id" => "MLB1",
            "name" => "Test",
            "children_categories" => [
                ["id" => "MLB10", "name" => "Valid", "total_items_in_this_category" => 5],
                "bad_entry",
                null,
            ],
        ]);
        $this->assertCount(1, $dto->childrenCategories);
        $this->assertSame("MLB10", $dto->childrenCategories[0]->id);
    }

    public function testDetailDTOThrowsOnEmptyId(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryDetailDTO::fromArray(["id" => "", "name" => "Valid"]);
    }

    public function testDetailDTOThrowsOnEmptyName(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryDetailDTO::fromArray(["id" => "MLB1", "name" => ""]);
    }

    public function testDetailDTOThrowsOnEmptyPayload(): void
    {
        $this->expectException(CategoriesApiException::class);
        CategoryDetailDTO::fromArray([]);
    }

    public function testDetailDTOCastsNumericPicture(): void
    {
        $dto = CategoryDetailDTO::fromArray(["id" => "MLB1", "name" => "Test", "picture" => 12345]);
        $this->assertSame("12345", $dto->picture);
    }

    public function testDetailDTONullPictureWhenNotSet(): void
    {
        $dto = CategoryDetailDTO::fromArray(["id" => "MLB1", "name" => "Test"]);
        $this->assertNull($dto->picture);
        $this->assertNull($dto->permalink);
    }

    public function testDetailDTOConstructorPublicProps(): void
    {
        $path = [new CategoryNodeDTO("MLB1", "Root")];
        $children = [new CategoryChildDTO("MLB10", "Child", 50)];
        $dto = new CategoryDetailDTO("MLB99", "Name", "pic.jpg", "http://link", 100, $path, $children);

        $this->assertSame("MLB99", $dto->id);
        $this->assertSame("Name", $dto->name);
        $this->assertSame("pic.jpg", $dto->picture);
        $this->assertSame("http://link", $dto->permalink);
        $this->assertSame(100, $dto->totalItemsInThisCategory);
        $this->assertCount(1, $dto->pathFromRoot);
        $this->assertCount(1, $dto->childrenCategories);
    }

    // --- CategoriesApiException ---

    public function testExceptionBasicProperties(): void
    {
        $ex = new CategoriesApiException("Test error", 400, "bad_request", ["field" => "id"]);
        $this->assertSame("Test error", $ex->getMessage());
        $this->assertSame(400, $ex->getStatusCode());
        $this->assertSame("bad_request", $ex->getApiErrorCode());
        $this->assertSame(["field" => "id"], $ex->getDetails());
        $this->assertSame(400, $ex->getCode());
    }

    public function testExceptionDefaults(): void
    {
        $ex = new CategoriesApiException("Minimal", 500);
        $this->assertSame("unknown_error", $ex->getApiErrorCode());
        $this->assertSame([], $ex->getDetails());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException("root cause");
        $ex = new CategoriesApiException("Wrapped", 502, "upstream_error", [], $previous);
        $this->assertSame($previous, $ex->getPrevious());
        $this->assertInstanceOf(\RuntimeException::class, $ex);
    }

    public function testExceptionIsRuntimeException(): void
    {
        $ex = new CategoriesApiException("Test", 503);
        $this->assertInstanceOf(\RuntimeException::class, $ex);
        $this->assertInstanceOf(\Throwable::class, $ex);
    }

    public function testExceptionStatusCodeVariations(): void
    {
        $codes = [400, 401, 403, 404, 429, 500, 502, 503];
        foreach ($codes as $code) {
            $ex = new CategoriesApiException("Error {$code}", $code);
            $this->assertSame($code, $ex->getStatusCode());
        }
    }

    public function testExceptionWithComplexDetails(): void
    {
        $details = [
            "payload" => ["id" => "", "name" => "test"],
            "errors" => ["id is required", "invalid format"],
            "nested" => ["a" => ["b" => "c"]],
        ];
        $ex = new CategoriesApiException("Complex", 422, "validation_error", $details);
        $this->assertSame($details, $ex->getDetails());
    }
}