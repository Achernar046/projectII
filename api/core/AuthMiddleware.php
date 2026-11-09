<?php
/**
 * api/core/AuthMiddleware.php
 * JWT Authentication Middleware
 */

class AuthMiddleware
{
    private static ?array $currentUser = null;

    /** Get bearer tokens only from the Authorization header. */
    public static function getBearerToken(): ?string
    {
        $headers = null;
        foreach ([
            'Authorization',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
            'REDIRECT_REDIRECT_HTTP_AUTHORIZATION',
            'REDIRECT_AUTHORIZATION',
        ] as $serverKey) {
            if (!empty($_SERVER[$serverKey])) {
                $headers = trim((string)$_SERVER[$serverKey]);
                break;
            }
        }

        if ($headers === null) {
            foreach (['getallheaders', 'apache_request_headers'] as $headerFunction) {
                if (!function_exists($headerFunction)) {
                    continue;
                }

                foreach ($headerFunction() ?: [] as $name => $value) {
                    if (strcasecmp((string)$name, 'Authorization') === 0) {
                        $headers = trim((string)$value);
                        break 2;
                    }
                }
            }
        }

        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/i', $headers, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Enforce authentication for protected routes
     */
    public static function authenticate(): array
    {
        $token = self::getBearerToken();

        if (!$token) {
            Response::unauthorized('กรุณาเข้าสู่ระบบ (ไม่พบ Bearer Token)');
        }

        $payload = JWT::decode($token);
        if (!$payload) {
            Response::unauthorized('โทเคนไม่ถูกต้องหรือหมดอายุการใช้งานแล้ว กรุณาเข้าสู่ระบบใหม่');
        }

        if (TokenRevocationStore::isRevoked($token)) {
            Response::unauthorized('Token นี้ถูกยกเลิกแล้ว กรุณาเข้าสู่ระบบใหม่');
        }

        $userId = (int)($payload['user_id'] ?? 0);
        $authVersion = (string)($payload['auth_version'] ?? '');
        if ($userId <= 0 || $authVersion === '') {
            Response::unauthorized('Token ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่');
        }

        $stmt = Database::getConnection()->prepare('SELECT password_hash FROM tb_users WHERE i_user_id = :user_id LIMIT 1');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $storedHash = $stmt->fetchColumn();
        if (!is_string($storedHash) || $storedHash === ''
            || !hash_equals($authVersion, substr(hash('sha256', $storedHash), 0, 32))) {
            Response::unauthorized('ข้อมูลเข้าสู่ระบบเปลี่ยนแปลงแล้ว กรุณาเข้าสู่ระบบใหม่');
        }

        unset($payload['auth_version']);
        self::$currentUser = $payload;
        return $payload;
    }

    /**
     * Get current authenticated user payload
     */
    public static function user(): ?array
    {
        return self::$currentUser;
    }
}
