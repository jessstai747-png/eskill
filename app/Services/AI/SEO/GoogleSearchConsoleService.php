<?php

declare(strict_types=1);

namespace App\Services\AI\SEO;

use App\Database;
use PDO;

class GoogleSearchConsoleService
{
    private PDO $db;
    private int $accountId;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        
        // Load credentials from env or settings
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
        $this->redirectUri = $_ENV['APP_URL'] . '/api/seo-killer/gsc/callback';
    }

    public function getAuthUrl(): string
    {
        if (empty($this->clientId)) {
            throw new \Exception('Google Client ID not configured');
        }

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => base64_encode(json_encode(['account_id' => $this->accountId]))
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function handleCallback(string $code): array
    {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        
        $postData = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode !== 200 || isset($data['error'])) {
            throw new \Exception('Error fetching token: ' . ($data['error_description'] ?? $response));
        }

        $this->saveToken($data);
        
        return ['success' => true];
    }

    private function saveToken(array $tokenData): void
    {
        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? null; // May not be present if not prompt=consent
        $expiresIn = $tokenData['expires_in'];
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        // Check if exists
        $stmt = $this->db->prepare("SELECT id FROM seo_gsc_auth WHERE account_id = ?");
        $stmt->execute([$this->accountId]);
        $exists = $stmt->fetch();

        if ($exists) {
            $sql = "UPDATE seo_gsc_auth SET access_token = ?, expires_at = ?";
            $params = [$accessToken, $expiresAt];
            
            if ($refreshToken) {
                $sql .= ", refresh_token = ?";
                $params[] = $refreshToken;
            }
            
            $sql .= " WHERE account_id = ?";
            $params[] = $this->accountId;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } else {
            if (!$refreshToken) {
                throw new \Exception('No refresh token provided. Revoke access and try again.');
            }
            
            $stmt = $this->db->prepare("INSERT INTO seo_gsc_auth (account_id, access_token, refresh_token, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$this->accountId, $accessToken, $refreshToken, $expiresAt]);
        }
    }

    public function getStatus(): array
    {
        $data = $this->getAuthRecord();

        if (!$data) {
            return ['connected' => false];
        }

        return [
            'connected' => true,
            'expires_at' => $data['expires_at'],
            'property_id' => $data['property_id']
        ];
    }
    
    /**
     * Get aggregated analytics data for a period.
     * Returns structure ready for dashboard consumption.
     */
    public function getAnalyticsData(string $startDate, string $endDate): array
    {
        $auth = $this->getAuthRecord();

        // No connection configured
        if (!$auth) {
            return [
                'connected' => false,
                'clicks' => 0,
                'impressions' => 0,
                'ctr' => '0%',
                'position' => 0.0,
                'chart' => [
                    'labels' => [],
                    'clicks' => [],
                    'impressions' => [],
                ],
                'queries' => [],
            ];
        }

        // Refresh token if expired or about to expire
        if (!empty($auth['expires_at']) && strtotime($auth['expires_at']) <= time() + 60) {
            try {
                $auth = $this->refreshAccessToken($auth);
            } catch (\Exception $e) {
                log_warning('GSC: erro ao renovar token', [
                    'service' => 'GoogleSearchConsoleService',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // If we still don't have access token or property configured, return empty data
        if (empty($auth['access_token']) || empty($auth['property_id'])) {
            return [
                'connected' => true,
                'clicks' => 0,
                'impressions' => 0,
                'ctr' => '0%',
                'position' => 0.0,
                'chart' => [
                    'labels' => [],
                    'clicks' => [],
                    'impressions' => [],
                ],
                'queries' => [],
            ];
        }

        $accessToken = $auth['access_token'];
        $siteUrl = $auth['property_id'];

        $metrics = [
            'connected' => true,
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => '0%',
            'position' => 0.0,
            'chart' => [
                'labels' => [],
                'clicks' => [],
                'impressions' => [],
            ],
            'queries' => [],
        ];

        // 1) Time series by date
        try {
            $timeseriesResponse = $this->callSearchAnalytics($siteUrl, $accessToken, [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['date'],
            ]);

            $totalClicks = 0;
            $totalImpressions = 0;
            $sumPosition = 0.0;
            $rowCount = 0;

            if (!empty($timeseriesResponse['rows']) && is_array($timeseriesResponse['rows'])) {
                foreach ($timeseriesResponse['rows'] as $row) {
                    $date = $row['keys'][0] ?? null;
                    $clicks = (int) ($row['clicks'] ?? 0);
                    $impressions = (int) ($row['impressions'] ?? 0);
                    $position = (float) ($row['position'] ?? 0.0);

                    if ($date) {
                        $metrics['chart']['labels'][] = $date;
                        $metrics['chart']['clicks'][] = $clicks;
                        $metrics['chart']['impressions'][] = $impressions;
                    }

                    $totalClicks += $clicks;
                    $totalImpressions += $impressions;
                    $sumPosition += $position;
                    $rowCount++;
                }
            }

            $avgPosition = $rowCount > 0 ? round($sumPosition / $rowCount, 1) : 0.0;
            $ctrPercent = $totalImpressions > 0
                ? round(($totalClicks / max($totalImpressions, 1)) * 100, 1) . '%'
                : '0%';

            $metrics['clicks'] = $totalClicks;
            $metrics['impressions'] = $totalImpressions;
            $metrics['position'] = $avgPosition;
            $metrics['ctr'] = $ctrPercent;
        } catch (\Exception $e) {
            log_warning('GSC: erro na análise de séries temporais', [
                'service' => 'GoogleSearchConsoleService',
                'error' => $e->getMessage(),
            ]);
        }

        // 2) Top queries
        try {
            $queriesResponse = $this->callSearchAnalytics($siteUrl, $accessToken, [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => ['query'],
                'rowLimit' => 10,
                'orderBy' => [
                    [
                        'field' => 'clicks',
                        'descending' => true,
                    ],
                ],
            ]);

            if (!empty($queriesResponse['rows']) && is_array($queriesResponse['rows'])) {
                foreach ($queriesResponse['rows'] as $row) {
                    $query = $row['keys'][0] ?? '';
                    $clicks = (int) ($row['clicks'] ?? 0);
                    $impressions = (int) ($row['impressions'] ?? 0);
                    $position = (float) ($row['position'] ?? 0.0);
                    $ctr = ($impressions > 0)
                        ? round(($clicks / max($impressions, 1)) * 100, 1) . '%'
                        : '0%';

                    if ($query !== '') {
                        $metrics['queries'][] = [
                            'query' => $query,
                            'clicks' => $clicks,
                            'impressions' => $impressions,
                            'ctr' => $ctr,
                            'position' => round($position, 1),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            log_warning('GSC: erro na análise de queries', [
                'service' => 'GoogleSearchConsoleService',
                'error' => $e->getMessage(),
            ]);
        }

        return $metrics;
    }

    /**
     * Fetch auth record for this account.
     */
    private function getAuthRecord(): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM seo_gsc_auth WHERE account_id = ?");
        $stmt->execute([$this->accountId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * Refresh access token using the stored refresh token.
     */
    private function refreshAccessToken(array $auth): array
    {
        if (empty($auth['refresh_token'])) {
            throw new \Exception('No refresh token available for Google Search Console.');
        }

        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $auth['refresh_token'],
            'grant_type' => 'refresh_token',
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception('Error refreshing GSC token: ' . $error);
        }

        $data = json_decode($response, true) ?: [];

        if ($httpCode !== 200 || isset($data['error'])) {
            $message = $data['error_description'] ?? $data['error'] ?? $response;
            throw new \Exception('Error refreshing GSC token: ' . $message);
        }

        // Ensure refresh_token is preserved if Google does not return it again
        if (empty($data['refresh_token'])) {
            $data['refresh_token'] = $auth['refresh_token'];
        }

        $this->saveToken($data);

        $updated = $this->getAuthRecord();
        if (!$updated) {
            throw new \Exception('Failed to load updated GSC auth record after refresh.');
        }

        return $updated;
    }

    /**
     * Call Search Console searchAnalytics.query endpoint.
     */
    private function callSearchAnalytics(string $siteUrl, string $accessToken, array $body): array
    {
        $endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode($siteUrl) . '/searchAnalytics/query';

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception('Error calling Search Console API: ' . $error);
        }

        $data = json_decode($response, true) ?: [];

        if ($httpCode >= 400) {
            $message = $data['error']['message'] ?? $response;
            throw new \Exception('Search Console API error (' . $httpCode . '): ' . $message);
        }

        return $data;
    }
}
