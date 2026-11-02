<?php
/**
 * api/core/Database.php
 * Object-Oriented PDO Database Manager (Singleton Pattern)
 */

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO database connection instance
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/database.php';

            // Support connection string if provided (e.g. Railway DATABASE_URL)
            $dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
            if ($dbUrl) {
                $parts = parse_url($dbUrl);
                $host = $parts['host'] ?? '127.0.0.1';
                $port = $parts['port'] ?? 3306;
                $user = $parts['user'] ?? 'root';
                $pass = $parts['pass'] ?? '';
                $name = isset($parts['path']) ? ltrim($parts['path'], '/') : 'db_northwind';
            } else {
                $host = $config['host'];
                $port = $config['port'];
                $user = $config['username'];
                $pass = $config['password'];
                $name = $config['dbname'];
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$config['charset']}";

            try {
                self::$instance = new PDO($dsn, $user, $pass, $config['options']);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error'   => 'Database Connection Error',
                    'message' => $e->getMessage()
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        return self::$instance;
    }

    /**
     * Prevent direct cloning and instantiation
     */
    private function __construct() {}
    private function __clone() {}
}
