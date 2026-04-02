<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use RuntimeException;

/**
 * AwaSellerIdentificationService
 *
 * Gerencia o enriquecimento jurídico/comercial dos sellers AWA:
 * CNPJ, razão social, fonte do dado, confiança e status de verificação.
 *
 * Regras importantes:
 * - CNPJ não é obrigatório para um seller aparecer no registry;
 * - dado jurídico nunca sobrescreve automaticamente fields de descoberta;
 * - toda alteração manual guarda created_by/notes/fonte.
 */
class AwaSellerIdentificationService
{
    /** Fontes aceitas para registro de identificação */
    public const VALID_SOURCES = [
        'manual',
        'authorized_ml_account',
        'internal_registry',
        'external_registry',
        'website_review',
        'legal_team_validation',
    ];

    /** Status de verificação aceitos */
    public const VALID_STATUSES = [
        'verified',
        'pending',
        'not_available',
        'conflict',
    ];

    private PDO $db;
    private int $accountId;

    public function __construct(int $accountId)
    {
        if ($accountId <= 0) {
            throw new RuntimeException('AwaSellerIdentificationService: account_id inválido.');
        }

        $this->db        = Database::getInstance();
        $this->accountId = $accountId;
    }

    // =========================================================================
    // GETTERS
    // =========================================================================

    /**
     * Retorna a identificação jurídica de um seller.
     * Valida que o seller pertence à conta antes de retornar.
     *
     * @return array<string, mixed>|null
     */
    public function getByRegistryId(int $registryId): ?array
    {
        $this->assertSellerBelongsToAccount($registryId);

        $stmt = $this->db->prepare(
            'SELECT * FROM awa_seller_identification
              WHERE seller_registry_id = :reg_id
              LIMIT 1'
        );
        $stmt->execute(['reg_id' => $registryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Retorna a lista de identificações com status e contagens.
     * Útil para relatórios e dashboards de verificação.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByStatus(string $status, int $page = 1, int $perPage = 50): array
    {
        $this->assertValidStatus($status);

        $perPage = max(1, min(200, $perPage));
        $offset  = max(0, ($page - 1) * $perPage);

        $stmt = $this->db->prepare(
            'SELECT i.*, r.nickname, r.seller_id AS ml_seller_id, r.permalink
               FROM awa_seller_identification i
               JOIN awa_seller_registry r ON r.id = i.seller_registry_id
              WHERE r.account_id = :account_id AND i.verification_status = :status
              ORDER BY i.updated_at DESC
              LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->bindValue(':status',     $status);
        $stmt->bindValue(':limit',      $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset',     $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contagem de sellers por status de identificação para metrics.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $stmt = $this->db->prepare(
            'SELECT i.verification_status, COUNT(*) AS cnt
               FROM awa_seller_identification i
               JOIN awa_seller_registry r ON r.id = i.seller_registry_id
              WHERE r.account_id = :account_id
              GROUP BY i.verification_status'
        );
        $stmt->execute(['account_id' => $this->accountId]);

        $counts = array_fill_keys(self::VALID_STATUSES, 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['verification_status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Conta sellers do registry SEM identificação registrada.
     */
    public function countUnidentified(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM awa_seller_registry r
              WHERE r.account_id = :account_id
                AND NOT EXISTS (
                    SELECT 1 FROM awa_seller_identification i WHERE i.seller_registry_id = r.id
                )'
        );
        $stmt->execute(['account_id' => $this->accountId]);

        return (int) $stmt->fetchColumn();
    }

    // =========================================================================
    // MUTATIONS
    // =========================================================================

    /**
     * Cria ou atualiza a identificação de um seller.
     * UPSERT — garante que há no máximo 1 registro por seller.
     *
     * @param  array<string, mixed> $data   Campos aceitos: cnpj, razao_social, source_type,
     *                                      source_reference, confidence_score,
     *                                      verification_status, notes, created_by
     * @throws RuntimeException se validação falhar
     */
    public function upsert(int $registryId, array $data): void
    {
        $this->assertSellerBelongsToAccount($registryId);
        $this->validateData($data);

        $stmt = $this->db->prepare(
            'INSERT INTO awa_seller_identification
                (seller_registry_id, cnpj, razao_social, source_type, source_reference,
                 confidence_score, verification_status, notes, created_by)
             VALUES
                (:reg_id, :cnpj, :razao_social, :source_type, :source_reference,
                 :confidence_score, :verification_status, :notes, :created_by)
             ON DUPLICATE KEY UPDATE
                cnpj                = VALUES(cnpj),
                razao_social        = VALUES(razao_social),
                source_type         = VALUES(source_type),
                source_reference    = VALUES(source_reference),
                confidence_score    = VALUES(confidence_score),
                verification_status = VALUES(verification_status),
                notes               = VALUES(notes),
                verified_at         = IF(
                    VALUES(verification_status) = \'verified\'
                    AND (verified_at IS NULL OR verification_status != \'verified\'),
                    NOW(),
                    verified_at
                )'
        );

        $stmt->execute([
            'reg_id'              => $registryId,
            'cnpj'                => $this->normalizeCnpj($data['cnpj'] ?? null),
            'razao_social'        => $this->nullableString($data['razao_social'] ?? null),
            'source_type'         => $data['source_type'] ?? 'manual',
            'source_reference'    => $this->nullableString($data['source_reference'] ?? null),
            'confidence_score'    => max(0, min(100, (int) ($data['confidence_score'] ?? 50))),
            'verification_status' => $data['verification_status'] ?? 'pending',
            'notes'               => $this->nullableString($data['notes'] ?? null),
            'created_by'          => $this->nullableString($data['created_by'] ?? null),
        ]);
    }

    /**
     * Marca identificação como verificada.
     * Útil para fluxo de aprovação sem precisar enviar payload completo.
     */
    public function verify(int $registryId, ?string $verifiedBy = null): void
    {
        $this->assertSellerBelongsToAccount($registryId);

        $stmt = $this->db->prepare(
            'UPDATE awa_seller_identification
                SET verification_status = \'verified\',
                    verified_at         = NOW(),
                    notes               = CONCAT(COALESCE(notes, \'\'),
                        IF(notes IS NOT NULL AND notes != \'\', \' | \', \'\'),
                        :note
                    )
              WHERE seller_registry_id = :reg_id'
        );

        $note = 'Verificado por: ' . ($verifiedBy ?? 'sistema') . ' em ' . date('d/m/Y H:i');
        $stmt->execute(['reg_id' => $registryId, 'note' => $note]);
    }

    /**
     * Marca identificação como conflito.
     */
    public function flagConflict(int $registryId, string $reason): void
    {
        $this->assertSellerBelongsToAccount($registryId);

        $stmt = $this->db->prepare(
            'UPDATE awa_seller_identification
                SET verification_status = \'conflict\',
                    notes               = CONCAT(COALESCE(notes, \'\'),
                        IF(notes IS NOT NULL AND notes != \'\', \' | \', \'\'),
                        :note
                    )
              WHERE seller_registry_id = :reg_id'
        );

        $note = 'Conflito registrado: ' . $reason . ' — ' . date('d/m/Y H:i');
        $stmt->execute(['reg_id' => $registryId, 'note' => $note]);
    }

    /**
     * Remove identificação de um seller (soft via status não_available).
     */
    public function markNotAvailable(int $registryId, ?string $reason = null): void
    {
        $this->assertSellerBelongsToAccount($registryId);

        $stmt = $this->db->prepare(
            'UPDATE awa_seller_identification
                SET verification_status = \'not_available\',
                    notes               = CONCAT(COALESCE(notes, \'\'),
                        IF(notes IS NOT NULL AND notes != \'\', \' | \', \'\'),
                        :note
                    )
              WHERE seller_registry_id = :reg_id'
        );

        $note = 'Marcado como não disponível: ' . ($reason ?? 's/d') . ' — ' . date('d/m/Y H:i');
        $stmt->execute(['reg_id' => $registryId, 'note' => $note]);
    }

    // =========================================================================
    // CNPJ UTILS
    // =========================================================================

    /**
     * Normaliza CNPJ para armazenamento (somente dígitos ou null).
     * Não realiza validação de dígito verificador no MVP.
     */
    public function normalizeCnpj(?string $cnpj): ?string
    {
        if ($cnpj === null || $cnpj === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $cnpj);
        if ($digits === null || $digits === '') {
            return null;
        }

        // CNPJ deve ter 14 dígitos
        if (strlen($digits) !== 14) {
            return null;
        }

        // Formata como XX.XXX.XXX/XXXX-XX
        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2)
        );
    }

    // =========================================================================
    // PROTECTED HELPERS — overrideáveis para testes
    // =========================================================================

    /**
     * Garante que o seller pertence à conta ativa.
     *
     * @throws RuntimeException se o seller não existir ou pertencer a outra conta
     */
    protected function assertSellerBelongsToAccount(int $registryId): void
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM awa_seller_registry
              WHERE id = :id AND account_id = :account_id
              LIMIT 1'
        );
        $stmt->execute(['id' => $registryId, 'account_id' => $this->accountId]);

        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException("Seller #{$registryId} não encontrado ou não pertence à conta.");
        }
    }

    /**
     * @param  array<string, mixed> $data
     * @throws RuntimeException
     */
    protected function validateData(array $data): void
    {
        if (isset($data['source_type']) && !in_array($data['source_type'], self::VALID_SOURCES, true)) {
            throw new RuntimeException(
                'source_type inválido. Aceitos: ' . implode(', ', self::VALID_SOURCES)
            );
        }

        if (isset($data['verification_status']) && !in_array($data['verification_status'], self::VALID_STATUSES, true)) {
            throw new RuntimeException(
                'verification_status inválido. Aceitos: ' . implode(', ', self::VALID_STATUSES)
            );
        }

        if (isset($data['confidence_score'])) {
            $score = (int) $data['confidence_score'];
            if ($score < 0 || $score > 100) {
                throw new RuntimeException('confidence_score deve ser entre 0 e 100.');
            }
        }
    }

    protected function assertValidStatus(string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new RuntimeException(
                'Status inválido. Aceitos: ' . implode(', ', self::VALID_STATUSES)
            );
        }
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }
}
