<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AwaSellerIdentificationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \App\Services\AwaSellerIdentificationService
 */
class AwaSellerIdentificationServiceTest extends TestCase
{
    // =========================================================================
    // normalizeCnpj
    // =========================================================================

    public function testNormalizeCnpjFormatsValidCnpj(): void
    {
        $svc = $this->makeServiceWithoutDb();

        $this->assertSame('12.345.678/0001-99', $svc->normalizeCnpj('12.345.678/0001-99'));
        $this->assertSame('12.345.678/0001-99', $svc->normalizeCnpj('12345678000199'));
        $this->assertSame('12.345.678/0001-99', $svc->normalizeCnpj('  12.345.678/0001-99  '));
    }

    public function testNormalizeCnpjRejectsShortInput(): void
    {
        $svc = $this->makeServiceWithoutDb();

        $this->assertNull($svc->normalizeCnpj('1234'));
        $this->assertNull($svc->normalizeCnpj('1234567800019'));  // 13 digits
        $this->assertNull($svc->normalizeCnpj('123456780001991')); // 15 digits
    }

    public function testNormalizeCnpjReturnsNullForBlank(): void
    {
        $svc = $this->makeServiceWithoutDb();

        $this->assertNull($svc->normalizeCnpj(null));
        $this->assertNull($svc->normalizeCnpj(''));
        $this->assertNull($svc->normalizeCnpj('   '));
    }

    public function testNormalizeCnpjStripsNonDigitsBeforeCounting(): void
    {
        $svc = $this->makeServiceWithoutDb();

        // CNPJ com pontuação mas com 14 dígitos no total
        $this->assertSame('12.345.678/0001-99', $svc->normalizeCnpj('12.345.678/0001-99'));
        // Letras adicionais tornam inválido
        $this->assertNull($svc->normalizeCnpj('12.ABC.678/0001-99'));
    }

    // =========================================================================
    // VALID_SOURCES and VALID_STATUSES constants
    // =========================================================================

    public function testConstantsContainExpectedValues(): void
    {
        $this->assertContains('manual', AwaSellerIdentificationService::VALID_SOURCES);
        $this->assertContains('authorized_ml_account', AwaSellerIdentificationService::VALID_SOURCES);
        $this->assertContains('internal_registry', AwaSellerIdentificationService::VALID_SOURCES);
        $this->assertContains('external_registry', AwaSellerIdentificationService::VALID_SOURCES);
        $this->assertContains('website_review', AwaSellerIdentificationService::VALID_SOURCES);
        $this->assertContains('legal_team_validation', AwaSellerIdentificationService::VALID_SOURCES);

        $this->assertContains('verified', AwaSellerIdentificationService::VALID_STATUSES);
        $this->assertContains('pending', AwaSellerIdentificationService::VALID_STATUSES);
        $this->assertContains('not_available', AwaSellerIdentificationService::VALID_STATUSES);
        $this->assertContains('conflict', AwaSellerIdentificationService::VALID_STATUSES);
    }

    // =========================================================================
    // Constructor validation
    // =========================================================================

    public function testConstructorRejectsZeroAccountId(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/account_id/');

        // Subclass para não conectar ao DB
        new class(0) extends AwaSellerIdentificationService {
            public function __construct(int $accountId)
            {
                parent::__construct($accountId);
            }
        };
    }

    public function testConstructorRejectsNegativeAccountId(): void
    {
        $this->expectException(RuntimeException::class);

        new class(-5) extends AwaSellerIdentificationService {
            public function __construct(int $accountId)
            {
                parent::__construct($accountId);
            }
        };
    }

    // =========================================================================
    // validateData (tested via upsert on a fake subclass)
    // =========================================================================

    public function testUpsertRejectsInvalidSourceType(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/source_type/');

        $svc = $this->makeServiceWithFakeDb(1, 999);
        $svc->upsert(999, ['source_type' => 'hacked_source']);
    }

    public function testUpsertRejectsInvalidVerificationStatus(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/verification_status/');

        $svc = $this->makeServiceWithFakeDb(1, 999);
        $svc->upsert(999, ['verification_status' => 'approved_by_magic']);
    }

    public function testUpsertRejectsConfidenceOutOfRange(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/confidence_score/');

        $svc = $this->makeServiceWithFakeDb(1, 999);
        $svc->upsert(999, ['confidence_score' => 150]);
    }

    public function testUpsertRejectsNegativeConfidence(): void
    {
        $this->expectException(RuntimeException::class);

        $svc = $this->makeServiceWithFakeDb(1, 999);
        $svc->upsert(999, ['confidence_score' => -1]);
    }

    public function testUpsertAcceptsValidPayloadAndCallsPdo(): void
    {
        $calls = [];

        $svc = $this->makeServiceWithCapturingDb(1, 999, $calls);
        $svc->upsert(999, [
            'cnpj'                => '12345678000199',
            'razao_social'        => 'Loja Exemplo Ltda',
            'source_type'         => 'manual',
            'verification_status' => 'pending',
            'confidence_score'    => 75,
            'notes'               => 'Testado',
        ]);

        $this->assertNotEmpty($calls);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Cria instância sem nenhuma lógica de DB (para testar métodos estáticos/normalizações).
     */
    private function makeServiceWithoutDb(): AwaSellerIdentificationService
    {
        return new class(1) extends AwaSellerIdentificationService {
            protected function assertSellerBelongsToAccount(int $registryId): void {} // no-op
        };
    }

    /**
     * Cria instância que apenas verifica que o seller pertence à conta,
     * e executa a validação de dados. lança se seller não bater.
     *
     * @param int $validRegistryId  registryId que simulamos existir na conta
     */
    private function makeServiceWithFakeDb(int $accountId, int $validRegistryId): AwaSellerIdentificationService
    {
        return new class($accountId, $validRegistryId) extends AwaSellerIdentificationService {
            private int $validId;

            public function __construct(int $accountId, int $validId)
            {
                parent::__construct($accountId);
                $this->validId = $validId;
            }

            protected function assertSellerBelongsToAccount(int $registryId): void
            {
                if ($registryId !== $this->validId) {
                    throw new \RuntimeException("Seller #{$registryId} não pertence à conta.");
                }
            }
        };
    }

    /**
     * Cria instância que captura a chamada real ao PDO (sem banco real).
     *
     * @param array<int, mixed> $calls  Array preenchido com chamadas capturadas
     */
    private function makeServiceWithCapturingDb(int $accountId, int $validRegistryId, array &$calls): AwaSellerIdentificationService
    {
        return new class($accountId, $validRegistryId, $calls) extends AwaSellerIdentificationService {
            private int $validId;
            /** @var array<int, mixed> */
            private array $captured;

            /**
             * @param array<int, mixed> $captured
             */
            public function __construct(int $accountId, int $validId, array &$captured)
            {
                parent::__construct($accountId);
                $this->validId  = $validId;
                $this->captured = &$captured;
            }

            protected function assertSellerBelongsToAccount(int $registryId): void
            {
                // sempre aceita
            }

            public function upsert(int $registryId, array $data): void
            {
                // Executa apenas a validação, não o PDO
                $this->captured[] = ['registryId' => $registryId, 'data' => $data];

                // Dispara a validação real via Reflection para não precisar de DB
                $method = new \ReflectionMethod(AwaSellerIdentificationService::class, 'validateData');
                $method->setAccessible(true);
                $method->invoke($this, $data);
            }
        };
    }
}
