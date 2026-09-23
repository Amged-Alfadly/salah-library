<?php
// ملف add_category.php
include 'db_config.php'; // الاتصال بالمخزن

$name = $_POST['cat_name']; // استلام الاسم المرسل

// وضع الاسم في الجدول الخاص بالأقسام
$sql = "INSERT INTO categories (name) VALUES (?)";
$stmt = $pdo->prepare($sql);
$stmt->execute([$name]);

echo json_encode(["success" => true]);
?>