<?php

namespace App\Services;

use App\Database;
use Exception;

class ShopeeService
{
    private $db;
    private $partnerId;
    private $partnerKey;
    private $redirectUri;
    private $baseUrl = 'https://partner.shopeemobile.com/api/v2'; // Production

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->partnerId = (int)($_ENV['SHOPEE_PARTNER_ID'] ?? 0);
        $this->partnerKey = $_ENV['SHOPEE_PARTNER_KEY'] ?? '';
        $this->redirectUri = ($_ENV['APP_URL'] ?? 'https://eskill.com.br') . '/shopee/callback';
    }

    public function getAuthUrl(): string
    {
        $path = '/shop/auth_partner';
        $timestamp = time();
        $baseString = sprintf("%s%s%s", $this->partnerId, $path, $timestamp);
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey);
        
        return sprintf(
            "%s%s?partner_id=%s&timestamp=%s&sign=%s&redirect=%s",
            $this->baseUrl,
            $path,
            $this->partnerId,
            $timestamp,
            $sign,
            urlencode($this->redirectUri)
        );
    }

    public function saveAuth($shopId, $code): bool
    {
        // 1. Exchange code for token
        $path = '/auth/token/get';
        $body = ['code' => $code, 'shop_id' => (int)$shopId, 'partner_id' => $this->partnerId];
        
        try {
            $response = $this->callPublicApi($path, $body);
            
            if (!isset($response['access_token'])) {
                log_error('Shopee auth error', ['service' => 'ShopeeService', 'response' => $response]);
                return false;
            }

            // 2. Save token
            $sql = "INSERT INTO shopee_auth (shop_id, access_token, refresh_token, token_expiry) 
                    VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
                    ON DUPLICATE KEY UPDATE 
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    token_expiry = DATE_ADD(NOW(), INTERVAL ? SECOND)";
            
            $expiry = $response['expire_in']; 
            
            return $this->db->prepare($sql)->execute([
                $shopId, 
                $response['access_token'], 
                $response['refresh_token'], 
                $expiry,
                $expiry
            ]);

        } catch (Exception $e) {
            log_error('Shopee save auth exception', ['service' => 'ShopeeService', 'error' => $e->getMessage()]);
            return false;
        }
    }

    public function getItems($shopId = null): array
    {
        // If shopId not provided, get first active shop
        if (!$shopId) {
            $shop = $this->db->query("SELECT shop_id FROM shopee_auth LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            if (!$shop) return [];
            $shopId = $shop['shop_id'];
        }

        try {
            // Fetch validation from API
            $response = $this->callShopApi('/product/get_item_list', ['page_size' => 20, 'offset' => 0, 'item_status' => 'NORMAL'], $shopId);
            
            $items = [];
            if (isset($response['response']['item_list'])) {
                $itemList = $response['response']['item_list'];
                
                // Need to fetch details for these items
                $itemIds = array_column($itemList, 'item_id');
                if (!empty($itemIds)) {
                    $details = $this->callShopApi('/product/get_item_base_info', ['item_id_list' => $itemIds], $shopId);
                    if (isset($details['response']['item_list'])) {
                        $items = $details['response']['item_list'];
                    }
                }
            }
            
            return $items;

        } catch (Exception $e) {
            log_error('Shopee get items exception', ['service' => 'ShopeeService', 'error' => $e->getMessage()]);
            return [];
        }
    }

    private function callPublicApi($path, $body): array
    {
        $timestamp = time();
        $baseString = sprintf("%s%s%s", $this->partnerId, $path, $timestamp);
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey);
        
        $url = sprintf("%s%s?partner_id=%s&timestamp=%s&sign=%s", $this->baseUrl, $path, $this->partnerId, $timestamp, $sign);
        
        return $this->curlRequest($url, $body);
    }

    private function callShopApi($path, $body, $shopId): array
    {
        $accessToken = $this->getAccessToken($shopId);
        if (!$accessToken) throw new Exception("Token not found for shop $shopId");

        $timestamp = time();
        // V2 Shop API Sign: partner_id + path + timestamp + access_token + shop_id
        $baseString = sprintf("%s%s%s%s%s", $this->partnerId, $path, $timestamp, $accessToken, $shopId);
        $sign = hash_hmac('sha256', $baseString, $this->partnerKey);
        
        $url = sprintf("%s%s?partner_id=%s&timestamp=%s&access_token=%s&shop_id=%s&sign=%s", 
            $this->baseUrl, $path, $this->partnerId, $timestamp, $accessToken, $shopId, $sign);
            
        return $this->curlRequest($url, $body);
    }

    private function getAccessToken($shopId)
    {
        $stmt = $this->db->prepare("SELECT access_token, refresh_token, token_expiry FROM shopee_auth WHERE shop_id = ?");
        $stmt->execute([$shopId]);
        $auth = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$auth) return null;

        // Check expiry (give 5 min buffer)
        if (strtotime($auth['token_expiry']) < time() + 300) {
            return $this->refreshToken($shopId, $auth['refresh_token']);
        }

        return $auth['access_token'];
    }

    private function refreshToken($shopId, $refreshToken)
    {
        $path = '/auth/access_token/get';
        $body = ['refresh_token' => $refreshToken, 'shop_id' => (int)$shopId, 'partner_id' => $this->partnerId];
        
        $response = $this->callPublicApi($path, $body);
        
        if (isset($response['access_token'])) {
             $expiry = $response['expire_in'];
             $sql = "UPDATE shopee_auth SET access_token = ?, refresh_token = ?, token_expiry = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE shop_id = ?";
             $this->db->prepare($sql)->execute([
                 $response['access_token'],
                 $response['refresh_token'],
                 $expiry,
                 $shopId
             ]);
             return $response['access_token'];
        }
        
        return null;
    }

    private function curlRequest($url, $body): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 400) {
            throw new Exception("Shopee API Error ($httpCode): $result");
        }
        
        return json_decode($result, true) ?: [];
    }
}
