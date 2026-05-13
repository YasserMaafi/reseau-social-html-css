<?php
/**
 * Database Configuration
 */

$config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: 5432,
    'dbname' => getenv('DB_NAME') ?: 'learning_platform',
    'user' => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: 'postgres',
];

$dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";

try {
    global $pdo;
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}
