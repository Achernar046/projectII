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
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
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
