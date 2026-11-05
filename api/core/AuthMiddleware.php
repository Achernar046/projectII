<?php
/**
 * api/core/AuthMiddleware.php
 * JWT Authentication Middleware
 */

class AuthMiddleware
{
    private static ?array $currentUser = null;

    /**
     * Get bearer token from request headers or query string
     */
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

        // Fallback to query parameter (useful for exports/downloads)
        if (!empty($_GET['token'])) {
            return trim($_GET['token']);
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
