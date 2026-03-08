<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Monolog\Logger;

/**
 * MercadoPagoAccountService — Análise de saúde financeira via Mercado Pago API v2
 *
 * Endpoints cobertos:
 *  - GET /v1/account/balance        → saldo disponível / bloqueado / pendente
 *  - GET /v1/collections/search     → recebimentos (vendas processadas)
 *  - GET /v1/chargebacks/search     → chargebacks (contestações)
 *  - GET /v1/disputes/search        → disputas abertas
 *  - GET /v1/money_requests/search  → solicitações de dinheiro
 *  - GET /v1/account/movements      → movimentações recentes
 *
 * Retorna indicadores de risco financeiro para o Raio X de conta.
 */
class MercadoPagoAccountService
{
    private const BASE_URL = 'https://api.mercadopago.com';
    private const TIMEOUT  = 15;

    private Client $http;
    private ?string $accessToken;
    private ?Logger $logger;

    // Limiares de risco
    private const CHARGEBACK_RISK_HIGH   = 5;   // >5 chargebacks → alto risco
    private const CHARGEBACK_RISK_MEDIUM = 2;   // >2 chargebacks → médio risco
    private const DISPUTE_RISK_HIGH      = 10;  // >10% de disputes sobre total → alto risco
    private const BLOCKED_RATIO_HIGH     = 0.20; // >20% do saldo bloqueado → alerta

    public function __construct(?string $accessToken = null, ?Logger $logger = null)
    {
        $this->accessToken = $accessToken ?? $this->loadTokenFromDb();
        $this->logger = $logger;

        $this->http = new Client([
            'base_uri' => self::BASE_URL,
            'timeout'  => self::TIMEOUT,
            'headers'  => [
                'Authorization' => 'Bearer ' . ($this->accessToken ?? ''),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'X-Idempotency-Key' => uniqid('xray_', true),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PUBLIC: análise financeira completa
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Análise financeira completa da conta Mercado Pago.
     *
     * @return array{
     *   success: bool,
     *   configured: bool,
     *   balance: array,
     *   chargebacks: array,
     *   disputes: array,
     *   financial_health: array,
     *   risk_flags: array<string>,
     *   risk_level: string,
     *   error?: string
     * }
     */
    public function getFinancialHealth(): array
    {
        if (empty($this->accessToken)) {
            return [
                'success'          => true,
                'configured'       => false,
                'balance'          => $this->emptyBalance(),
                'chargebacks'      => $this->emptyChargebacks(),
                'disputes'         => $this->emptyDisputes(),
                'financial_health' => $this->emptyFinancialHealth(),
                'risk_flags'       => [],
                'risk_level'       => 'UNKNOWN',
                'message'          => 'Mercado Pago não configurado. Conecte sua conta MP nas configurações.',
            ];
        }

        try {
            // Paralelo: buscar dados independentes
            $balance    = $this->fetchBalance();
            $chargebacks = $this->fetchChargebacks(30);
            $disputes   = $this->fetchDisputes();
            $movements  = $this->fetchRecentMovements(10);

            // Calcular indicadores
            $financialHealth = $this->calculateFinancialHealth($balance, $chargebacks, $disputes, $movements);
            $riskFlags       = $this->identifyRiskFlags($balance, $chargebacks, $disputes, $financialHealth);
            $riskLevel       = $this->calculateRiskLevel($riskFlags);

            return [
                'success'          => true,
                'configured'       => true,
                'balance'          => $balance,
                'chargebacks'      => $chargebacks,
                'disputes'         => $disputes,
                'recent_movements' => $movements,
                'financial_health' => $financialHealth,
                'risk_flags'       => $riskFlags,
                'risk_level'       => $riskLevel,
            ];
        } catch (\Throwable $e) {
            $this->log('error', 'MP financial health error', ['error' => $e->getMessage()]);
            return [
                'success'    => false,
                'configured' => true,
                'error'      => 'Erro ao consultar Mercado Pago: ' . $e->getMessage(),
                'balance'    => $this->emptyBalance(),
                'chargebacks' => $this->emptyChargebacks(),
                'disputes'   => $this->emptyDisputes(),
                'financial_health' => $this->emptyFinancialHealth(),
                'risk_flags' => [],
                'risk_level' => 'UNKNOWN',
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE: endpoints MP API
    // ─────────────────────────────────────────────────────────────────────

    /** GET /v1/account/balance */
    private function fetchBalance(): array
    {
        try {
            $res  = $this->http->get('/v1/account/balance');
            $data = json_decode($res->getBody()->getContents(), true) ?? [];

            $available = (float) ($data['available_balance'] ?? 0);
            $blocked   = (float) ($data['blocked_balance'] ?? 0);
            $pending   = (float) ($data['in_process_balance'] ?? 0);
            $total     = $available + $blocked + $pending;
            $blockedRatio = $total > 0 ? round($blocked / $total, 4) : 0.0;

            return [
                'available'      => $available,
                'blocked'        => $blocked,
                'pending'        => $pending,
                'total'          => $total,
                'blocked_ratio'  => $blockedRatio,
                'currency'       => $data['currency_id'] ?? 'BRL',
            ];
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            $this->log('warning', 'MP balance fetch failed', ['status' => $status]);
            return array_merge($this->emptyBalance(), ['fetch_error' => "HTTP {$status}"]);
        }
    }

    /** GET /v1/chargebacks/search */
    private function fetchChargebacks(int $days = 30): array
    {
        try {
            $from = date('Y-m-d', strtotime("-{$days} days")) . 'T00:00:00.000-03:00';
            $res  = $this->http->get('/v1/chargebacks/search', [
                'query' => [
                    'date_created.from' => $from,
                    'limit'             => 100,
                    'offset'            => 0,
                ],
            ]);
            $data   = json_decode($res->getBody()->getContents(), true) ?? [];
            $items  = $data['results'] ?? [];
            $total  = (int) ($data['paging']['total'] ?? count($items));

            // Classificar por status
            $byStatus = [];
            $totalAmount = 0.0;
            foreach ($items as $cb) {
                $st = $cb['status'] ?? 'unknown';
                $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
                $totalAmount += (float) ($cb['amount'] ?? 0);
            }

            return [
                'total'          => $total,
                'period_days'    => $days,
                'total_amount'   => round($totalAmount, 2),
                'by_status'      => $byStatus,
                'open'           => ($byStatus['in_review'] ?? 0) + ($byStatus['opened'] ?? 0),
                'won'            => $byStatus['resolved_seller'] ?? 0,
                'lost'           => $byStatus['resolved_buyer'] ?? 0,
                'risk'           => $this->chargebackRisk($total),
            ];
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            // 403 = sem permissão (conta não configurada para chargebacks)
            if ($status === 403 || $status === 404) {
                return array_merge($this->emptyChargebacks(), ['available' => false]);
            }
            return array_merge($this->emptyChargebacks(), ['fetch_error' => "HTTP {$status}"]);
        }
    }

    /** GET /v1/disputes/search — disputas de mediação */
    private function fetchDisputes(): array
    {
        try {
            $res  = $this->http->get('/v1/disputes/search', [
                'query' => [
                    'status' => 'opened',
                    'limit'  => 50,
                ],
            ]);
            $data  = json_decode($res->getBody()->getContents(), true) ?? [];
            $items = $data['results'] ?? [];
            $total = (int) ($data['paging']['total'] ?? count($items));

            // Categorias de disputa
            $reasons = [];
            foreach ($items as $d) {
                $reason = $d['reason'] ?? 'unknown';
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
            }

            return [
                'open_total'  => $total,
                'by_reason'   => $reasons,
                'top_reason'  => $total > 0
                    ? array_search(max($reasons), $reasons, true)
                    : null,
            ];
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            if ($status === 403 || $status === 404) {
                return array_merge($this->emptyDisputes(), ['available' => false]);
            }
            return array_merge($this->emptyDisputes(), ['fetch_error' => "HTTP {$status}"]);
        }
    }

    /** GET /v1/account/movements/search — movimentações recentes */
    private function fetchRecentMovements(int $limit = 10): array
    {
        try {
            $res  = $this->http->get('/v1/account/movements/search', [
                'query' => ['limit' => $limit, 'offset' => 0],
            ]);
            $data  = json_decode($res->getBody()->getContents(), true) ?? [];
            $items = $data['results'] ?? [];

            return array_map(fn(array $m): array => [
                'type'         => $m['type'] ?? '',
                'amount'       => (float) ($m['amount'] ?? 0),
                'currency'     => $m['currency_id'] ?? 'BRL',
                'date'         => $m['date'] ?? '',
                'description'  => $m['description'] ?? '',
            ], array_slice($items, 0, $limit));
        } catch (\Throwable) {
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE: cálculos de saúde financeira
    // ─────────────────────────────────────────────────────────────────────

    private function calculateFinancialHealth(
        array $balance,
        array $chargebacks,
        array $disputes,
        array $movements
    ): array {
        $score = 100;
        $indicators = [];

        // Penalidade por chargebacks
        $cbTotal = $chargebacks['total'] ?? 0;
        if ($cbTotal >= self::CHARGEBACK_RISK_HIGH) {
            $score -= 30;
            $indicators[] = "Alto volume de chargebacks ({$cbTotal}) nos últimos 30 dias";
        } elseif ($cbTotal >= self::CHARGEBACK_RISK_MEDIUM) {
            $score -= 15;
            $indicators[] = "Volume médio de chargebacks ({$cbTotal}) nos últimos 30 dias";
        }

        // Penalidade por saldo bloqueado
        $blockedRatio = $balance['blocked_ratio'] ?? 0.0;
        if ($blockedRatio >= self::BLOCKED_RATIO_HIGH) {
            $score -= 25;
            $indicators[] = sprintf(
                'Saldo bloqueado elevado: %.0f%% do total',
                $blockedRatio * 100
            );
        } elseif ($blockedRatio >= 0.10) {
            $score -= 10;
            $indicators[] = sprintf('Saldo bloqueado: %.0f%% do total', $blockedRatio * 100);
        }

        // Penalidade por disputas abertas
        $openDisputes = $disputes['open_total'] ?? 0;
        if ($openDisputes >= 5) {
            $score -= 20;
            $indicators[] = "Muitas disputas abertas ({$openDisputes})";
        } elseif ($openDisputes >= 2) {
            $score -= 10;
            $indicators[] = "Disputas abertas ({$openDisputes})";
        }

        $score = max(0, min(100, $score));

        return [
            'score'               => $score,
            'grade'               => $this->gradeFromScore($score),
            'negative_indicators' => $indicators,
            'positive_indicators' => $this->buildPositiveIndicators($balance, $chargebacks, $disputes),
        ];
    }

    /** @return array<string> */
    private function identifyRiskFlags(
        array $balance,
        array $chargebacks,
        array $disputes,
        array $financialHealth
    ): array {
        $flags = [];

        if (($balance['blocked_ratio'] ?? 0) >= self::BLOCKED_RATIO_HIGH) {
            $flags[] = 'SALDO_BLOQUEADO_ALTO';
        }
        if (($chargebacks['total'] ?? 0) >= self::CHARGEBACK_RISK_HIGH) {
            $flags[] = 'CHARGEBACKS_CRITICOS';
        } elseif (($chargebacks['total'] ?? 0) >= self::CHARGEBACK_RISK_MEDIUM) {
            $flags[] = 'CHARGEBACKS_ELEVADOS';
        }
        if (($disputes['open_total'] ?? 0) >= 5) {
            $flags[] = 'DISPUTAS_ABERTAS_ALTO';
        }
        if (($chargebacks['lost'] ?? 0) >= 3) {
            $flags[] = 'CHARGEBACKS_PERDIDOS';
        }
        if (($financialHealth['score'] ?? 100) < 50) {
            $flags[] = 'SAUDE_FINANCEIRA_CRITICA';
        }

        return $flags;
    }

    private function calculateRiskLevel(array $riskFlags): string
    {
        $critical = ['CHARGEBACKS_CRITICOS', 'SAUDE_FINANCEIRA_CRITICA'];
        $high     = ['SALDO_BLOQUEADO_ALTO', 'DISPUTAS_ABERTAS_ALTO', 'CHARGEBACKS_PERDIDOS'];

        foreach ($critical as $flag) {
            if (in_array($flag, $riskFlags, true)) {
                return 'CRITICO';
            }
        }
        foreach ($high as $flag) {
            if (in_array($flag, $riskFlags, true)) {
                return 'ALTO';
            }
        }

        return empty($riskFlags) ? 'BAIXO' : 'MEDIO';
    }

    private function chargebackRisk(int $total): string
    {
        if ($total >= self::CHARGEBACK_RISK_HIGH) {
            return 'HIGH';
        }
        if ($total >= self::CHARGEBACK_RISK_MEDIUM) {
            return 'MEDIUM';
        }

        return 'LOW';
    }

    private function gradeFromScore(int $score): string
    {
        return match(true) {
            $score >= 90 => 'A',
            $score >= 75 => 'B',
            $score >= 60 => 'C',
            $score >= 40 => 'D',
            default      => 'F',
        };
    }

    /** @return array<string> */
    private function buildPositiveIndicators(array $balance, array $chargebacks, array $disputes): array
    {
        $pos = [];

        if (($chargebacks['total'] ?? 0) === 0) {
            $pos[] = 'Nenhum chargeback nos últimos 30 dias';
        }
        if (($disputes['open_total'] ?? 0) === 0) {
            $pos[] = 'Nenhuma disputa aberta';
        }
        if (($balance['blocked_ratio'] ?? 1.0) < 0.05) {
            $pos[] = 'Saldo financeiro saudável — menos de 5% bloqueado';
        }

        return $pos;
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE: empty structs
    // ─────────────────────────────────────────────────────────────────────

    private function emptyBalance(): array
    {
        return [
            'available'     => 0.0,
            'blocked'       => 0.0,
            'pending'       => 0.0,
            'total'         => 0.0,
            'blocked_ratio' => 0.0,
            'currency'      => 'BRL',
        ];
    }

    private function emptyChargebacks(): array
    {
        return [
            'total'       => 0,
            'period_days' => 30,
            'total_amount'=> 0.0,
            'by_status'   => [],
            'open'        => 0,
            'won'         => 0,
            'lost'        => 0,
            'risk'        => 'LOW',
            'available'   => true,
        ];
    }

    private function emptyDisputes(): array
    {
        return [
            'open_total' => 0,
            'by_reason'  => [],
            'top_reason' => null,
            'available'  => true,
        ];
    }

    private function emptyFinancialHealth(): array
    {
        return [
            'score'               => 0,
            'grade'               => 'F',
            'negative_indicators' => [],
            'positive_indicators' => [],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE: helpers
    // ─────────────────────────────────────────────────────────────────────

    private function loadTokenFromDb(): ?string
    {
        try {
            $db   = \App\Database::getInstance();
            $stmt = $db->prepare(
                "SELECT setting_value FROM ean_settings WHERE setting_key = 'mp_access_token' LIMIT 1"
            );
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row || empty($row['setting_value'])) {
                return null;
            }

            // Tentar descriptografar
            try {
                $enc = new EncryptionService();
                return $enc->decrypt($row['setting_value']);
            } catch (\Throwable) {
                return $row['setting_value'];
            }
        } catch (\Throwable) {
            return null;
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->{$level}($message, $context);
        }
    }
}
