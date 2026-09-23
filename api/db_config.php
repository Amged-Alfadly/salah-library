<?php
// api/db_config.php - Unified Database Connection
error_reporting(0);
ini_set('display_errors', 0);

$host     = getenv('DB_HOST') ?: "localhost";
$dbname   = getenv('DB_NAME') ?: "LibraryStore";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') ?: "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB CONNECT ERROR: " . $e->getMessage());
    $pdo = null;
}
?>
