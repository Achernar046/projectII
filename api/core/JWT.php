<?php
/**
 * api/core/JWT.php
 * Pure PHP JSON Web Token (JWT) Implementation
 * Adheres to RFC 7519 without external composer packages
 */

class JWT
{
    /**
     * Base64Url encode a string
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64Url decode a string
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Generate a signed JWT
     *
     * @param array $payload Custom claims to include in the token
     * @return string Signed JWT token
     */
    public static function encode(array $payload): string
    {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/../config/jwt.php';
        }

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $issuedAt = time();
        $defaultClaims = [
            'iss' => $config['issuer'],
            'aud' => $config['audience'],
            'iat' => $issuedAt,
            'exp' => $issuedAt + (int)$config['expiry'],
        ];

        $finalPayload = array_merge($defaultClaims, $payload);

        $encodedHeader  = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $encodedPayload = self::base64UrlEncode(json_encode($finalPayload, JSON_UNESCAPED_UNICODE));

        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $config['secret'], true);
        $encodedSignature = self::base64UrlEncode($signature);

        return "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";
    }

    /**
     * Decode and verify a JWT token
     *
     * @param string $token
     * @return array|null Returns payload array if valid, null if invalid or expired
     */
    public static function decode(string $token): ?array
    {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/../config/jwt.php';
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        // Verify Signature
        $expectedSignature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $config['secret'], true);
        $providedSignature = self::base64UrlDecode($encodedSignature);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null; // Signature mismatch
        }

        // Decode Payload
        $payloadJson = self::base64UrlDecode($encodedPayload);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            return null;
        }

        // Check Expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Expired
        }

        return $payload;
    }
}
