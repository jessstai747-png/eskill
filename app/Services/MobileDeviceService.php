<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * MobileDeviceService
 * 
 * Manages mobile device registrations and push tokens
 */
class MobileDeviceService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Register or update a mobile device
     */
    public function registerDevice(int $userId, string $deviceId, string $fcmToken, string $platform, string $appVersion): array
    {
        try {
            // Check if device exists
            $stmt = $this->db->prepare("SELECT id FROM mobile_devices WHERE user_id = ? AND device_id = ?");
            $stmt->execute([$userId, $deviceId]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                // Update existing device
                $updateStmt = $this->db->prepare("
                    UPDATE mobile_devices 
                    SET fcm_token = ?, platform = ?, app_version = ?, last_active = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$fcmToken, $platform, $appVersion, $exists['id']]);
                return ['success' => true, 'action' => 'updated', 'device_id' => $exists['id']];
            } else {
                // Insert new device
                $insertStmt = $this->db->prepare("
                    INSERT INTO mobile_devices (user_id, device_id, fcm_token, platform, app_version)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$userId, $deviceId, $fcmToken, $platform, $appVersion]);
                return ['success' => true, 'action' => 'created', 'device_id' => $this->db->lastInsertId()];
            }
        } catch (\Exception $e) {
            log_error('Erro ao registrar dispositivo móvel', [
                'user_id' => $userId,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Unregister a device
     */
    public function unregisterDevice(int $userId, string $deviceId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM mobile_devices WHERE user_id = ? AND device_id = ?");
            return $stmt->execute([$userId, $deviceId]);
        } catch (\Exception $e) {
            log_error('Erro ao desregistrar dispositivo móvel', [
                'user_id' => $userId,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get user devices
     */
    public function getUserDevices(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM mobile_devices WHERE user_id = ? ORDER BY last_active DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
