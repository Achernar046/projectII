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
    private static function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
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
        $headerJson = self::base64UrlDecode($encodedHeader);
        $payloadJson = self::base64UrlDecode($encodedPayload);
        $providedSignature = self::base64UrlDecode($encodedSignature);
        if ($headerJson === null || $payloadJson === null || $providedSignature === null) {
            return null;
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? null) !== 'JWT' || !is_array($payload)) {
            return null;
        }

        // Verify signature and required issuer, audience, and time claims.
        $expectedSignature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $config['secret'], true);

        if (strlen($providedSignature) !== 32 || !hash_equals($expectedSignature, $providedSignature)) {
            return null; // Signature mismatch
        }

        if (($payload['iss'] ?? null) !== $config['issuer']
            || ($payload['aud'] ?? null) !== $config['audience']
            || !isset($payload['iat'], $payload['exp'])
            || !is_numeric($payload['iat'])
            || !is_numeric($payload['exp'])) {
            return null;
        }

        $now = time();
        if ((int)$payload['iat'] > $now + 60 || (int)$payload['exp'] <= $now) {
            return null; // Expired
        }
        if (isset($payload['nbf']) && (!is_numeric($payload['nbf']) || (int)$payload['nbf'] > $now)) {
            return null;
        }

        return $payload;
    }
}
