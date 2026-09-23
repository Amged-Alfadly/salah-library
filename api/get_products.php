<?php
// منع ظهور أي أخطاء نصية قبل الـ JSON
error_reporting(0); 
header('Content-Type: application/json; charset=UTF-8');
require_once 'db_config.php';

// تنظيف أي مخرجات زائدة
ob_start();

$category = isset($_GET['category']) ? $_GET['category'] : 'all';
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

try {
    // بناء الاستعلام مع جلب الصورة الرئيسية من جدول الصور المنفصل
    $sql = "SELECT p.*, c.name as category_name, 
            (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id";

    if ($category !== 'all') {
        $sql .= " WHERE p.category_id = :category_id";
    }

    // إضافة الترتيب والـ LIMIT
    $sql .= " ORDER BY p.created_at DESC LIMIT :offset, :limit";

    $stmt = $pdo->prepare($sql);
    
    if ($category !== 'all') {
        $stmt->bindValue(':category_id', $category, PDO::PARAM_INT);
    }
    
    // ضروري جداً تحديد النوع PARAM_INT لضمان عدم وضع علامات تنصيص حول الأرقام
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تنظيف المخرجات وإرسال JSON نظيف
    ob_end_clean();
    echo json_encode($products);

} catch (PDOException $e) {
    ob_end_clean();
    // إرسال الخطأ بصيغة JSON لكي تفهمه الجافا سكريبت ولا تنهار
    echo json_encode(["success" => false, "message" => "SQL Error: " . $e->getMessage()]);
}
?>