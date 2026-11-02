<?php
/**
 * api/config/jwt.php
 * JWT (JSON Web Token) Security Settings
 */

return [
    'secret' => getenv('JWT_SECRET') ?: 'Northwind_BMS_Secret_Key_2026_Classroom_Security!@#$',
    'expiry' => 86400, // 24 hours in seconds
    'issuer' => 'northwind-bms-api',
    'audience' => 'northwind-bms-clients',
];
