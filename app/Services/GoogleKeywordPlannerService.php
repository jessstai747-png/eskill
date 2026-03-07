<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Google Keyword Planner API Service
 * 
 * Provides real keyword volume and competition data
 * 
 * @author AI System
 * @version 1.0.0
 */
class GoogleKeywordPlannerService
{
    private Client $client;
    private LoggerInterface $logger;
    private string $apiKey;
    private string $clientId;
    private string $clientSecret;
    private string $developerToken;
    private string $refreshToken;
    private \PDO $db;
    private MercadoLivreClient $mlClient;
    
    // Cache configuration
    private const CACHE_TTL = 86400; // 24 hours
    private const RATE_LIMIT = 10; // requests per minute
    
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
        
        // Load credentials from environment
        $this->apiKey = $_ENV['GOOGLE_ADS_API_KEY'] ?? '';
        $this->clientId = $_ENV['GOOGLE_ADS_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['GOOGLE_ADS_CLIENT_SECRET'] ?? '';
        $this->developerToken = $_ENV['GOOGLE_ADS_DEVELOPER_TOKEN'] ?? '';
        $this->refreshToken = $_ENV['GOOGLE_ADS_REFRESH_TOKEN'] ?? '';
        $this->db = Database::getInstance();
        $this->mlClient = new MercadoLivreClient();
    }
    
    /**
     * Get keyword metrics (volume, competition, CPC)
     *
     * @param string $keyword
     * @param string $countryCode ISO 3166-1 alpha-2 (default: BR)
     * @param string $languageCode ISO 639-1 (default: pt)
     * @return array|null
     */
    public function getKeywordMetrics(string $keyword, string $countryCode = 'BR', string $languageCode = 'pt'): ?array
    {
        try {
            // Check cache first
            $cacheKey = "gkp_keyword_{$keyword}_{$countryCode}_{$languageCode}";
            $cached = $this->getCachedResult($cacheKey);
            
            if ($cached) {
                $this->logger->info("Google Keyword Planner: Cache HIT for keyword: {$keyword}");
                return $cached;
            }
            
            $this->logger->info("Google Keyword Planner: Fetching real data for keyword: {$keyword}");
            
            // Get access token
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                throw new Exception('Failed to obtain Google Ads API access token');
            }
            
            // Build request payload
            $payload = $this->buildKeywordRequest($keyword, $countryCode, $languageCode);
            
            // Make API request
            $response = $this->client->post('https://googleads.googleapis.com/v17/customers:listAccessibleCustomers', [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'developer-token' => $this->developerToken,
                ]
            ]);
            
            $customers = json_decode($response->getBody(), true);
            
            if (empty($customers['resourceNames'])) {
                throw new Exception('No accessible Google Ads accounts found');
            }
            
            // Use first customer account
            $customerId = str_replace('customers/', '', $customers['resourceNames'][0]);
            
            // Generate keyword ideas
            $ideasResponse = $this->client->post(
                "https://googleads.googleapis.com/v17/customers/{$customerId}:generateKeywordIdeas",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$accessToken}",
                        'developer-token' => $this->developerToken,
                        'login-customer-id' => $customerId,
                    ],
                    'json' => $payload
                ]
            );
            
            $ideas = json_decode($ideasResponse->getBody(), true);
            
            // Process results
            $metrics = $this->processKeywordIdeas($ideas, $keyword);
            
            // Cache the result
            $this->cacheResult($cacheKey, $metrics);
            
            $this->logger->info("Google Keyword Planner: Successfully fetched metrics for: {$keyword}", [
                'volume' => $metrics['volume'],
                'competition' => $metrics['competition'],
                'cpc' => $metrics['avg_cpc']
            ]);
            
            return $metrics;
            
        } catch (Exception $e) {
            $this->logger->error("Google Keyword Planner API error for keyword '{$keyword}': " . $e->getMessage());
            
            return $this->getFallbackMetrics($keyword);
        }
    }
    
    /**
     * Get multiple keywords metrics in batch
     *
     * @param array $keywords
     * @param string $countryCode
     * @param string $languageCode
     * @return array
     */
    public function getBatchKeywordMetrics(array $keywords, string $countryCode = 'BR', string $languageCode = 'pt'): array
    {
        $results = [];
        
        foreach ($keywords as $keyword) {
            $metrics = $this->getKeywordMetrics($keyword, $countryCode, $languageCode);
            if ($metrics) {
                $results[$keyword] = $metrics;
            }
        }
        
        return $results;
    }
    
    /**
     * Get keyword competition level mapping
     *
     * @param string $competitionEnum
     * @return string
     */
    private function mapCompetitionLevel(string $competitionEnum): string
    {
        $mapping = [
            'LOW' => 'baixa',
            'MEDIUM' => 'média',
            'HIGH' => 'alta'
        ];
        
        return $mapping[strtoupper($competitionEnum)] ?? 'desconhecida';
    }
    
    /**
     * Get access token using refresh token
     *
     * @return string|null
     */
    private function getAccessToken(): ?string
    {
        try {
            $response = $this->client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type' => 'refresh_token'
                ]
            ]);
            
            $tokenData = json_decode($response->getBody(), true);
            return $tokenData['access_token'] ?? null;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to refresh Google Ads API token: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Build keyword request payload
     *
     * @param string $keyword
     * @param string $countryCode
     * @param string $languageCode
     * @return array
     */
    private function buildKeywordRequest(string $keyword, string $countryCode, string $languageCode): array
    {
        return [
            'keywordPlanIdeaService' => 'customers/-',
            'pageSize' => 1,
            'keywordAnnotation' => ['KEYWORD_CONCEPT'],
            'keywordAndUrlSeed' => [
                'url' => '',
                'keywords' => [$keyword]
            ],
            'geoTargetConstants' => [$this->getGeoTargetConstant($countryCode)],
            'language' => $this->getLanguageConstant($languageCode)
        ];
    }
    
    /**
     * Get geo target constant for country
     *
     * @param string $countryCode
     * @return string
     */
    private function getGeoTargetConstant(string $countryCode): string
    {
        $countries = [
            'BR' => 'geoTargetConstants/2014', // Brazil
            'US' => 'geoTargetConstants/2840', // United States
            'AR' => 'geoTargetConstants/2023', // Argentina
            'CL' => 'geoTargetConstants/2108', // Chile
            'MX' => 'geoTargetConstants/2180'  // Mexico
        ];
        
        return $countries[strtoupper($countryCode)] ?? 'geoTargetConstants/2014'; // Default to Brazil
    }
    
    /**
     * Get language constant
     *
     * @param string $languageCode
     * @return string
     */
    private function getLanguageConstant(string $languageCode): string
    {
        $languages = [
            'pt' => 'languageConstants/1022', // Portuguese
            'en' => 'languageConstants/1000', // English
            'es' => 'languageConstants/1002'  // Spanish
        ];
        
        return $languages[strtolower($languageCode)] ?? 'languageConstants/1022'; // Default to Portuguese
    }
    
    /**
     * Process keyword ideas response
     *
     * @param array $response
     * @param string $originalKeyword
     * @return array|null
     */
    private function processKeywordIdeas(array $response, string $originalKeyword): ?array
    {
        if (empty($response['results'])) {
            return null;
        }
        
        // Find exact match or closest match
        $bestMatch = null;
        $lowestDistance = PHP_INT_MAX;
        
        foreach ($response['results'] as $idea) {
            $keywordText = $idea['keyword']['text'] ?? '';
            if (mb_strtolower($keywordText) === mb_strtolower($originalKeyword)) {
                $bestMatch = $idea;
                break;
            }
            
            // Calculate similarity distance
            $distance = levenshtein(mb_strtolower($keywordText), mb_strtolower($originalKeyword));
            if ($distance < $lowestDistance) {
                $lowestDistance = $distance;
                $bestMatch = $idea;
            }
        }
        
        if (!$bestMatch) {
            return null;
        }
        
        $metrics = $bestMatch['keywordIdeaMetrics'] ?? [];
        
        return [
            'keyword' => $originalKeyword,
            'volume' => (int)($metrics['avgMonthlySearches'] ?? 0),
            'competition' => $this->mapCompetitionLevel($metrics['competition'] ?? 'UNKNOWN'),
            'competition_index' => $this->mapCompetitionIndex($metrics['competition'] ?? 'UNKNOWN'),
            'avg_cpc' => round(($metrics['lowTopOfPageBidMicros'] ?? 0) / 1000000, 2),
            'match_type' => $bestMatch['keyword']['matchType'] ?? 'EXACT',
            'source' => 'google_keyword_planner'
        ];
    }
    
    /**
     * Map competition enum to numeric index
     *
     * @param string $competition
     * @return int
     */
    private function mapCompetitionIndex(string $competition): int
    {
        $mapping = [
            'LOW' => 33,
            'MEDIUM' => 66,
            'HIGH' => 90
        ];
        
        return $mapping[strtoupper($competition)] ?? 50;
    }
    
    /**
     * Get fallback metrics when API fails
     *
     * @param string $keyword
     * @return array
     */
    private function getFallbackMetrics(string $keyword): array
    {
        // 1) Dados locais de market_keywords (populados por rotinas reais)
        $stmt = $this->db->prepare("
            SELECT search_volume, competition_level, avg_price 
            FROM market_keywords 
            WHERE keyword = :keyword 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        $stmt->execute(['keyword' => $keyword]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $competitionIndex = (int)($row['competition_level'] ?? 0);
            return [
                'keyword' => $keyword,
                'volume' => (int)($row['search_volume'] ?? 0),
                'competition' => $this->mapCompetitionLevelFromIndex($competitionIndex),
                'competition_index' => $competitionIndex,
                'avg_cpc' => $row['avg_price'] ? round(((float)$row['avg_price']) * 0.05, 2) : 0.0,
                'match_type' => 'EXACT',
                'source' => 'market_keywords_db'
            ];
        }

        // 2) Snapshot direto da busca do Mercado Livre (dados públicos)
        $search = $this->mlClient->searchItems(['q' => $keyword, 'limit' => 50]);
        $results = $search['results'] ?? [];
        $totalResults = (int)($search['paging']['total'] ?? count($results));

        if (!empty($results)) {
            $prices = [];
            $sellerIds = [];
            foreach ($results as $item) {
                if (!empty($item['price'])) {
                    $prices[] = (float)$item['price'];
                }
                $sellerId = $item['seller']['id'] ?? $item['seller_id'] ?? null;
                if ($sellerId) {
                    $sellerIds[] = (string)$sellerId;
                }
            }

            $distinctSellers = count(array_unique($sellerIds));
            $competitionIndex = $this->deriveCompetitionIndex($distinctSellers, $totalResults);
            $avgPrice = !empty($prices) ? array_sum($prices) / count($prices) : 0;

            return [
                'keyword' => $keyword,
                'volume' => max($totalResults, count($results)),
                'competition' => $this->mapCompetitionLevelFromIndex($competitionIndex),
                'competition_index' => $competitionIndex,
                'avg_cpc' => $avgPrice > 0 ? round($avgPrice * 0.04, 2) : 0.0,
                'match_type' => 'EXACT',
                'source' => 'ml_search_snapshot'
            ];
        }

        return [
            'keyword' => $keyword,
            'volume' => 0,
            'competition' => 'desconhecida',
            'competition_index' => 0,
            'avg_cpc' => 0.0,
            'match_type' => 'EXACT',
            'source' => 'unavailable'
        ];
    }

    private function deriveCompetitionIndex(int $sellerCount, int $totalResults): int
    {
        $sellerScore = min(100, $sellerCount * 4); // 25 sellers ≈ 100
        $volumeScore = $totalResults > 0 ? min(100, ($totalResults / 500) * 100) : 0;
        return (int)round(max($sellerScore, $volumeScore));
    }

    private function mapCompetitionLevelFromIndex(int $index): string
    {
        if ($index >= 80) return 'alta';
        if ($index >= 60) return 'média';
        if ($index >= 40) return 'média';
        if ($index >= 20) return 'baixa';
        return 'muito_baixa';
    }
    
    /**
     * Get cached result
     *
     * @param string $key
     * @return array|null
     */
    private function getCachedResult(string $key): ?array
    {
        // Simple file-based cache for now
        $cacheFile = STORAGE_PATH . "/cache/{$key}.json";
        
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            $expiry = $cacheData['expires'] ?? 0;
            
            if (time() < $expiry) {
                return $cacheData['data'];
            }
        }
        
        return null;
    }
    
    /**
     * Cache result
     *
     * @param string $key
     * @param array $data
     * @return void
     */
    private function cacheResult(string $key, array $data): void
    {
        $cacheDir = STORAGE_PATH . '/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = "{$cacheDir}/{$key}.json";
        $cacheData = [
            'data' => $data,
            'expires' => time() + self::CACHE_TTL,
            'created' => time()
        ];
        
        file_put_contents($cacheFile, json_encode($cacheData));
    }
    
    /**
     * Check if service is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && 
               !empty($this->clientId) && 
               !empty($this->clientSecret) && 
               !empty($this->developerToken) && 
               !empty($this->refreshToken);
    }
    
    /**
     * Get service health status
     *
     * @return array
     */
    public function getHealthStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'required_env_vars' => [
                'GOOGLE_ADS_API_KEY' => !empty($this->apiKey),
                'GOOGLE_ADS_CLIENT_ID' => !empty($this->clientId),
                'GOOGLE_ADS_CLIENT_SECRET' => !empty($this->clientSecret),
                'GOOGLE_ADS_DEVELOPER_TOKEN' => !empty($this->developerToken),
                'GOOGLE_ADS_REFRESH_TOKEN' => !empty($this->refreshToken)
            ],
            'cache_enabled' => true,
            'rate_limit' => self::RATE_LIMIT . ' requests/minute'
        ];
    }
}
