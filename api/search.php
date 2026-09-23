<?php
// منع ظهور أي أخطاء نصية قد تخرب استجابة الـ JSON
error_reporting(0);
ini_set('display_errors', 0);

// إرسال الـ Headers قبل أي عملية إخراج بيانات
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // للسماح بالطلبات من المتصفح

include 'db_config.php';

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (empty($term)) {
    echo json_encode([]);
    exit;
}

try {
    // استعلام البحث - تأكد من مطابقة أسماء الجداول والأعمدة في قاعدتك
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.title LIKE :term 
              OR p.description LIKE :term 
              OR c.name LIKE :term 
              LIMIT 15";

    $stmt = $pdo->prepare($query);
    $searchTerm = "%" . $term . "%";
    $stmt->bindValue(':term', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // معالجة البيانات قبل الإرسال
    $output = [];
    foreach ($products as $product) {
        $output[] = [
            'id' => (int)$product['id'],
            'title' => $product['title'],
            'price' => (float)$product['price'],
            'main_image' => !empty($product['main_image']) ? $product['main_image'] : 'img/default.jpg',
            'category_name' => $product['category_name'] ?? 'عام',
            'view_count' => (int)($product['view_count'] ?? 0),
            'is_new' => (bool)($product['is_new'] ?? false)
        ];
    }

    echo json_encode($output, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // في حال الخطأ نرسل كود 500 ورسالة JSON بدلاً من خطأ PHP نصي
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
}
exit;
