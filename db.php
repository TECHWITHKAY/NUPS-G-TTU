<?php
/**
 * Database Connection (MySQL)
 */

require_once 'config.php';

$conn = null;

try {
    // MySQL Connection
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    $conn = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

// Initialize Database Tables if they don't exist
function initializeDatabase($conn) {
    // Check if users table exists
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        $sql = file_get_contents(__DIR__ . '/database.sql');
        $conn->exec($sql);
    }
}

// Helper function to check if a table exists
function tableExists($conn, $tableName) {
    $stmt = $conn->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);
    return $stmt->rowCount() > 0;
}

// Helper function to check if a column exists in a table
function columnExists($conn, $tableName, $columnName) {
    $stmt = $conn->prepare("SHOW COLUMNS FROM $tableName LIKE ?");
    $stmt->execute([$columnName]);
    return $stmt->rowCount() > 0;
}

// Uncomment the line below to auto-initialize database on first run
// initializeDatabase($conn);
