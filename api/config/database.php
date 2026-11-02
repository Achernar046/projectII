<?php
/**
 * api/config/database.php
 * Database connection settings
 * Supports local MAMP and Cloud Hosting (Hostinger, Railway, Docker, InfinityFree, VPS)
 */

return [
    'host'     => getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306),
    'dbname'   => getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'db_northwind',
    'username' => getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root',
    'password' => getenv('MYSQLPASSWORD') !== false
                    ? getenv('MYSQLPASSWORD')
                    : (getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'root'),
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // Enforces true native prepared statements
    ],
];
