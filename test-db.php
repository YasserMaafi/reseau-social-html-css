<?php
$dsn = 'pgsql:host=localhost;port=5432;dbname=learning_platform';
try {
    $pdo = new PDO($dsn, 'postgres', 'postgres');
    echo "✓ Database connection successful!\n";
    
    // Test query
    $result = $pdo->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch();
    echo "✓ Users in database: " . $row['count'] . "\n";
} catch (PDOException $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
