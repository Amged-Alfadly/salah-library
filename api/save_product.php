<?php
// 1. منع ظهور أي أخطاء نصية تفسد الـ JSON
error_reporting(0);
header("Content-Type: application/json; charset=UTF-8");

include 'db_config.php';

// تنظيف أي مخرجات سابقة (مثل مسافات فارغة في ملف db_config)
ob_start();
// بدء الجلسة لقراءة اسم المشرف إذا كان موجوداً
if (session_status() === PHP_SESSION_NONE) session_start();

try {
    if (!$pdo) {
        throw new Exception("فشل الاتصال بقاعدة البيانات");
    }

    $pdo->beginTransaction();

    // 1. استلام وتجهيز البيانات (تغيير المسميات لتناسب منتجات عامة)
    $title     = $_POST['title'] ?? '';
    $cat_id    = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    $price     = $_POST['price'] ?? 0;
    $desc      = $_POST['description'] ?? '';
    $stock     = $_POST['stock_quantity'] ?? 0; // حقل الكمية الموجود في قاعدتك

    if (empty($title)) {
        throw new Exception("اسم المنتج مطلوب");
    }

    // إذا كان المتصل مسجلاً كمشرف، خزّن اسمه في حقل author حتى تظهر للمدير
    $author = $_SESSION['admin_name'] ?? ($_POST['author'] ?? null);
    if ($author) {
        $sql_prod = "INSERT INTO products (title, category_id, price, description, stock_quantity, author) 
                     VALUES (:title, :cat, :price, :desc, :stock, :author)";
    } else {
        $sql_prod = "INSERT INTO products (title, category_id, price, description, stock_quantity) 
                     VALUES (:title, :cat, :price, :desc, :stock)";
    }
    $stmt = $pdo->prepare($sql_prod);
    $params = [
        ':title' => $title,
        ':cat'   => $cat_id,
        ':price' => $price,
        ':desc'  => $desc,
        ':stock' => $stock
    ];
    if ($author) $params[':author'] = $author;
    $stmt->execute($params);

    $product_id = $pdo->lastInsertId();

    // 2. معالجة الصورة الرئيسية للمنتج
    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === 0) {
        $upload_dir = '../../uploads/'; // تأكد من المسار الصحيح للمجلد
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($file_ext, $allowed_extensions)) {
            throw new Exception("امتداد الصورة غير مسموح به");
        }

        $file_name = time() . '_' . uniqid() . '.' . $file_ext;

        if (move_uploaded_file($_FILES['main_image']['tmp_name'], $upload_dir . $file_name)) {
            // حفظ المسار ليكون متاحاً للعرض في الموقع
            $img_path = $file_name;
            $sql_img = "INSERT INTO product_images (product_id, image_path, is_main) 
                        VALUES (:pid, :path, 1)";
            $pdo->prepare($sql_img)->execute([':pid' => $product_id, ':path' => $img_path]);
        }
    }

    // 3. معالجة رابط الفيديو (يوتيوب مثلاً)
    $video_url = $_POST['video_url'] ?? '';
    if (!empty($video_url)) {
        $sql_vid = "INSERT INTO product_videos (product_id, video_url, video_type) 
                    VALUES (:pid, :url, 'link')";
        $pdo->prepare($sql_vid)->execute([':pid' => $product_id, ':url' => $video_url]);
    }

    $pdo->commit();

    // مسح أي "شوشرة" ناتجة عن التحذيرات وإرسال JSON نظيف
    ob_end_clean();
    echo json_encode([
        "success" => true,
        "message" => "تمت إضافة المنتج بنجاح!",
        "product_id" => $product_id
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "message" => "فشل: " . $e->getMessage()
    ]);
}
