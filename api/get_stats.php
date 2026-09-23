<?php
header("Content-Type: application/json");
include 'db_config.php'; // التأكد أن هذا الملف يعرف متغير $pdo

try {
    // 1. جلب إجمالي عدد المنتجات
    $stmt1 = $pdo->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt1->fetch(PDO::FETCH_ASSOC)['total'];

    // 2. جلب إجمالي عدد الأقسام
    $stmt2 = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $total_categories = $stmt2->fetch(PDO::FETCH_ASSOC)['total'];

    // 3. جلب إجمالي المشاهدات (مع معالجة حالة القيمة NULL)
    $stmt3 = $pdo->query("SELECT SUM(view_count) as total FROM products");
    $total_views = $stmt3->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // إرسال البيانات كـ JSON
    echo json_encode([
        "total_products"   => (int)$total_products,
        "total_categories" => (int)$total_categories,
        "total_views"      => (int)$total_views
    ]);

} catch (PDOException $e) {
    // في حال حدوث خطأ في قاعدة البيانات
    echo json_encode([
        "error" => "فشل جلب الإحصائيات: " . $e->getMessage()
    ]);
}
?>