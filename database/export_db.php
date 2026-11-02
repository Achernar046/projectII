<?php
/**
 * database/export_db.php
 * Export db_northwind with schema, auto_increment, data, and users table
 */

require_once __DIR__ . '/../api/config/database.php';
$config = require __DIR__ . '/../api/config/database.php';

$pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4", $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

$sql = "-- ========================================================\n";
$sql .= "-- Northwind BMS Complete Database Dump\n";
$sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Compatible with MySQL 5.7+, MariaDB 10+, MySQL 8+\n";
$sql .= "-- ========================================================\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
$sql .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
$sql .= "SET NAMES utf8mb4;\n\n";

foreach ($tables as $table) {
    $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
    $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
    $sql .= $create['Create Table'] . ";\n\n";

    $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        $cols = array_keys($rows[0]);
        $colNames = implode('`, `', $cols);
        $sql .= "INSERT INTO `{$table}` (`{$colNames}`) VALUES\n";
        $rowSqls = [];
        foreach ($rows as $row) {
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote($v);
            }, array_values($row));
            $rowSqls[] = "(" . implode(', ', $vals) . ")";
        }
        $sql .= implode(",\n", $rowSqls) . ";\n\n";
    }
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

$outPath = __DIR__ . '/db_northwind_complete.sql';
file_put_contents($outPath, $sql);
echo "Successfully generated: {$outPath} (" . strlen($sql) . " bytes)\n";
