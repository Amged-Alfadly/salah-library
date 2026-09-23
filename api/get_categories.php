<?php
// api/get_categories.php
header("Content-Type: application/json");
include 'db_config.php';

try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($categories);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>