<?php
declare(strict_types=1);

namespace App\Services;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;

/**
 * GeoIP Service
 * Resolve informações de geolocalização de IPs
 */
class GeoIPService
{
    private ?Reader $reader = null;
    private bool $enabled = false;

    public function __construct()
    {
        $dbPath = __DIR__ . '/../../storage/geoip/GeoLite2-City.mmdb';
        
        if (file_exists($dbPath)) {
            try {
                $this->reader = new Reader($dbPath);
                $this->enabled = true;
            } catch (\Exception $e) {
                log_error('GeoIP: Falha ao carregar banco de dados', [
                    'service' => 'GeoIPService',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Verifica se o serviço está ativo
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Resolve informações de localização de um IP
     * 
     * @param string $ip Endereço IP
     * @return array|null Array com country_code, country_name, city, latitude, longitude ou null
     */
    public function lookup(string $ip): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        // IPs locais não tem geolocalização
        if ($this->isPrivateIP($ip)) {
            return [
                'country_code' => 'XX',
                'country_name' => 'Private Network',
                'city' => 'Local',
                'latitude' => null,
                'longitude' => null
            ];
        }

        try {
            $record = $this->reader->city($ip);
            
            return [
                'country_code' => $record->country->isoCode ?? null,
                'country_name' => $record->country->name ?? null,
                'city' => $record->city->name ?? null,
                'latitude' => $record->location->latitude ?? null,
                'longitude' => $record->location->longitude ?? null
            ];
        } catch (AddressNotFoundException $e) {
            // IP não encontrado no banco de dados
            return null;
        } catch (\Exception $e) {
            log_warning('GeoIP: Erro no lookup', [
                'service' => 'GeoIPService',
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verifica se é um IP privado/local
     */
    private function isPrivateIP(string $ip): bool
    {
        // Remover porta se presente
        $ip = explode(':', $ip)[0];

        // IPv4 privados
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            
            return (
                // 10.0.0.0 - 10.255.255.255
                ($long >= ip2long('10.0.0.0') && $long <= ip2long('10.255.255.255')) ||
                // 172.16.0.0 - 172.31.255.255
                ($long >= ip2long('172.16.0.0') && $long <= ip2long('172.31.255.255')) ||
                // 192.168.0.0 - 192.168.255.255
                ($long >= ip2long('192.168.0.0') && $long <= ip2long('192.168.255.255')) ||
                // 127.0.0.0 - 127.255.255.255 (localhost)
                ($long >= ip2long('127.0.0.0') && $long <= ip2long('127.255.255.255'))
            );
        }

        // IPv6 locais
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return (
                strpos($ip, 'fe80:') === 0 || // Link-local
                strpos($ip, 'fc00:') === 0 || // Unique local
                strpos($ip, 'fd00:') === 0 || // Unique local
                $ip === '::1'                  // Localhost
            );
        }

        return false;
    }

    /**
     * Formata localização para exibição
     */
    public function formatLocation(array $geoData): string
    {
        $parts = [];
        
        if (!empty($geoData['city'])) {
            $parts[] = $geoData['city'];
        }
        
        if (!empty($geoData['country_name'])) {
            $parts[] = $geoData['country_name'];
        } elseif (!empty($geoData['country_code'])) {
            $parts[] = $geoData['country_code'];
        }
        
        return !empty($parts) ? implode(', ', $parts) : 'Unknown';
    }

    /**
     * Retorna emoji da bandeira do país
     */
    public function getCountryFlag(string $countryCode): string
    {
        if (strlen($countryCode) !== 2) {
            return '🌍';
        }

        $countryCode = strtoupper($countryCode);
        
        // Converter código do país em emoji de bandeira
        // A = U+1F1E6, Z = U+1F1FF
        $flag = mb_chr(0x1F1E6 + ord($countryCode[0]) - ord('A')) .
                mb_chr(0x1F1E6 + ord($countryCode[1]) - ord('A'));
        
        return $flag;
    }

    /**
     * Retorna estatísticas por país
     */
    public function getCountryStats(\PDO $db, ?string $since = null): array
    {
        $sql = "
            SELECT 
                country_code,
                country_name,
                COUNT(*) as count,
                COUNT(DISTINCT ip_address) as unique_ips
            FROM auth_failure_log
            WHERE country_code IS NOT NULL
        ";
        
        if ($since) {
            $sql .= " AND detected_at >= :since";
        }
        
        $sql .= " GROUP BY country_code, country_name ORDER BY count DESC LIMIT 20";
        
        $stmt = $db->prepare($sql);
        
        if ($since) {
            $stmt->bindValue(':since', $since);
        }
        
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retorna mapa de calor (top cidades)
     */
    public function getHeatmapData(\PDO $db, int $limit = 50): array
    {
        $limitSql = max(1, min((int)$limit, 500));

        $stmt = $db->prepare("
            SELECT 
                city,
                country_name,
                latitude,
                longitude,
                COUNT(*) as count
            FROM auth_failure_log
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            GROUP BY city, country_name, latitude, longitude
            ORDER BY count DESC
            LIMIT {$limitSql}
        ");
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Limpar recursos
     */
    public function __destruct()
    {
        if ($this->reader) {
            // Reader não precisa de close explícito
            $this->reader = null;
        }
    }
}
