<?php
/**
 * api/config/jwt.php
 * JWT (JSON Web Token) Security Settings
 */

$localConfigPath = __DIR__ . '/jwt.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
$secret = $localConfig['secret'] ?? '';
if ($secret === '') {
    $secret = getenv('JWT_SECRET') ?: '';
}
if ($secret === '') {
    $host = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
    if (!in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('Set JWT_SECRET or create api/config/jwt.local.php before using this site publicly.');
    }
    $secret = 'Northwind_BMS_Secret_Key_2026_Classroom_Security!@#$';
}
if (str_starts_with($secret, 'replace-with-')) {
    throw new RuntimeException('Replace the example JWT secret before using this site.');
}

return [
    'secret' => $secret,
    'expiry' => 86400, // 24 hours in seconds
    'issuer' => 'northwind-bms-api',
    'audience' => 'northwind-bms-clients',
];
