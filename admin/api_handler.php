<?php
header("X-Content-Type-Options: nosniff");
session_start();

// Include shared DB config which sets $pdo
require_once __DIR__ . '/../api/db_config.php';

header('Content-Type: application/json; charset=utf-8');
//$action = $_GET['action'] ?? ''; قيل ان هذا الجزء مفقود
if (empty($pdo)) {
    echo json_encode(["success" => false, "message" => "قاعدة البيانات غير متاحة"]);
    exit;
}
// حل الثغرة الاولى في الملف وتتمثل في id admin
$admin_actions = ['get_stats', 'list_users', 'add_product', 'add_category', 'edit_category', 'delete_category', 'change_password', 'delete_product', 'edit_product', 'toggle_user_role', 'delete_product_image', 'set_main_image'];

if (in_array($action, $admin_actions)) {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        echo json_encode(["success" => false, "message" => "غير مسموح لك بالوصول"]);
        exit;
    }
}
// التحقق من صلاحيات المشرف (بعض الطرق مسموح بها للزوار لذلك لا نمنع كل الحالات هنا)
// لاحقاً كل إجراء يتحقق من الجلسة إذا تطلب الأمر

// قراءة JSON body عندما يرسل العميل JSON
function get_json_body() {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return [];
}

// التحقق من CSRF token
function verify_csrf() {
    $headers = [];
    if (function_exists('getallheaders')) $headers = getallheaders();
    $token = null;
    if (!empty($headers['X-CSRF-Token'])) $token = $headers['X-CSRF-Token'];
    if (!$token && !empty($_SERVER['HTTP_X_CSRF_TOKEN'])) $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    if (!$token && !empty($_POST['csrf_token'])) $token = $_POST['csrf_token'];
    if (!$token) {
        $body = get_json_body();
        if (!empty($body['csrf_token'])) $token = $body['csrf_token'];
    }
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new Exception('فشل التحقق من CSRF');
    }
}

// سجل تدقيق إداري
function log_audit($pdo, $admin_id, $action, $details = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO admin_audit_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $json = null;
        if (!is_null($details)) {
            if (is_array($details) || is_object($details)) $json = json_encode($details, JSON_UNESCAPED_UNICODE);
            else $json = json_encode(['info' => (string)$details], JSON_UNESCAPED_UNICODE);
        }
        $stmt->execute([$admin_id, $action, $json, $ip]);
    } catch (Exception $e) {
        // لا تفشل العملية الأساسية بسبب فشل التسجيل
    }
}

// رفع صورة آمنة مع فحوصات MIME والحجم
function handle_image_upload($fieldName) {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$fieldName];
    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new Exception('حجم الملف أكبر من 5MB');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];
    if (!isset($allowed[$mime])) {
        throw new Exception('نوع الملف غير مسموح');
    }

    $ext = $allowed[$mime];
    $safeName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $target = $uploadDir . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('فشل رفع الملف');
    }

    return 'uploads/' . $safeName;
}

function handle_images_uploads($fieldName) {
    $paths = [];
    if (!isset($_FILES[$fieldName])) return $paths;
    $files = $_FILES[$fieldName];
    if (!is_array($files['name'])) return $paths;
    for ($i=0;$i<count($files['name']);$i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $tmp = $files['tmp_name'][$i];
        $size = $files['size'][$i];
        $maxBytes = 5 * 1024 * 1024;
        if ($size > $maxBytes) continue;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];
        if (!isset($allowed[$mime])) continue;
        $ext = $allowed[$mime];
        $safeName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $target = $uploadDir . $safeName;
        if (!move_uploaded_file($tmp, $target)) continue;
        $paths[] = 'uploads/' . $safeName;
    }
    return $paths;
}

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'get_stats':
        $products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $views = $pdo->query("SELECT SUM(total_page_views) FROM site_analytics")->fetchColumn() ?: 0;
        $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

        echo json_encode([
            "total_products" => $products,
            "total_categories" => $categories,
            "total_views" => $views,
            "total_users" => $users
        ]);
        break;

    case 'list_products':
        $sql = "SELECT p.*, c.name as category_name, 
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY p.created_at DESC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll());
        break;

    case 'list_users':
        $stmt = $pdo->query("SELECT id, username, email, role_id, created_at FROM users ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll());
        break;

    case 'add_product':
        try {
            verify_csrf();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('الطريقة غير مدعومة');
            $title = trim($_POST['title'] ?? '');
            $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
            $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
            $desc = $_POST['description'] ?? '';
            if ($title === '') throw new Exception('اسم المنتج مطلوب');
            $pdo->beginTransaction();
            $author = $_SESSION['admin_name'] ?? ($_POST['author'] ?? null);
            $stmt = $pdo->prepare("INSERT INTO products (category_id, title, author, description, price, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$category_id, $title, $author, $desc, $price]);
            $product_id = $pdo->lastInsertId();
            if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                $imgPath = handle_image_upload('main_image');
                $imgStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, ?)");
                $imgStmt->execute([$product_id, $imgPath, 1]);
            }
            $additional = handle_images_uploads('images');
            if (!empty($additional)) {
                $imgStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, 0)");
                foreach ($additional as $p) { $imgStmt->execute([$product_id, $p]); }
            }
            if (!empty($_POST['video_url'])) {
                $vidStmt = $pdo->prepare("INSERT INTO product_videos (product_id, video_url, video_type) VALUES (?, ?, 'link')");
                $vidStmt->execute([$product_id, $_POST['video_url']]);
            }
            $pdo->commit();
            try { log_audit($pdo, $_SESSION['admin_id'] ?? null, 'add_product', ['product_id'=>$product_id, 'title'=>$title]); } catch (Exception $e) {}
            echo json_encode(["success" => true, "message" => "تم حفظ المنتج والوسائط بنجاح"]);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(["success" => false, "message" => "فشل الحفظ: " . $e->getMessage()]);
            exit;
        }
        break;

    case 'add_category':
        try {
            verify_csrf();
            $name = $_POST['cat_name'] ?? '';
            $slug = strtolower(str_replace(' ', '-', $name));
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
            $catId = $pdo->lastInsertId();
            try { log_audit($pdo, $_SESSION['admin_id'] ?? null, 'add_category', ['category_id'=>$catId, 'name'=>$name]); } catch (Exception $e) {}
            echo json_encode(["success" => true]);
        } catch (Exception $e) {
            echo json_encode(["success" => false, "message" => "التصنيف موجود مسبقاً"]);
        }
        break;

    case 'edit_category':
        try {
            verify_csrf();
            $data = get_json_body();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            $name = trim($data['name'] ?? '');
            if ($id <= 0 || $name === '') { echo json_encode(["success"=>false]); break; }
            $slug = strtolower(str_replace(' ', '-', $name));
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
            $ok = $stmt->execute([$name, $slug, $id]);
            if ($ok) try { log_audit($pdo, $_SESSION['admin_id'] ?? null, 'edit_category', ['category_id'=>$id, 'name'=>$name]); } catch (Exception $e) {}
            echo json_encode(["success" => (bool)$ok]);
        } catch (Exception $e) { echo json_encode(["success"=>false,"message"=>$e->getMessage()]); }
        break;

    case 'list_categories':
        echo json_encode($pdo->query("SELECT * FROM categories")->fetchAll());
        break;

    case 'delete_category':
        try {
            verify_csrf();
            $data = get_json_body();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) { echo json_encode(["success"=>false, "message"=>"معرف غير صالح"]); break; }
            $nameRow = $pdo->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
            $nameRow->execute([$id]);
            $row = $nameRow->fetch();
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $ok = $stmt->execute([$id]);
            if ($ok) try { log_audit($pdo, $_SESSION['admin_id'] ?? null, 'delete_category', ['category_id'=>$id, 'name'=>$row['name'] ?? null]); } catch (Exception $e) {}
            echo json_encode(["success" => (bool)$ok]);
        } catch (Exception $e) { echo json_encode(["success"=>false, "message"=>$e->getMessage()]); }
        break;

    case 'change_password':
    try {
        verify_csrf();
        $admin_id = $_SESSION['admin_id'] ?? null;
        // ... (كود التحقق من كلمة المرور القديمة كما هو لديك)
        
        if ($user && password_verify($old_pass, $user['password'])) {
            $new_hash = password_hash($new_pass_raw, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$new_hash, $admin_id]);

            // إجراء أمني هام جداً:
            session_regenerate_id(true); // تغيير معرف الجلسة لمنع اختطافها
            
            log_audit($pdo, $admin_id, 'change_password');
            echo json_encode(["success" => true, "message" => "تم تحديث كلمة المرور بنجاح"]);
        } else {
            echo json_encode(["success" => false, "message" => "كلمة المرور الحالية غير صحيحة"]);
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(["success" => false, "message" => "فشل تحديث البيانات"]);
    }
    break;

    case 'delete_product':
        try {
            verify_csrf();
            $data = get_json_body();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) { 
                echo json_encode(["success"=>false, "message"=>"معرف غير صالح"]); 
                break; 
            }

            // 1. جلب بيانات المنتج والأسماء قبل الحذف للتدقيق
            $p = $pdo->prepare("SELECT title FROM products WHERE id = ? LIMIT 1");
            $p->execute([$id]);
            $prod = $p->fetch();

            if (!$prod) throw new Exception("المنتج غير موجود");

            // 2. جلب مسارات جميع الصور المرتبطة بهذا المنتج لحذفها من السيرفر
            $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
            $imgStmt->execute([$id]);
            $images = $imgStmt->fetchAll();

            $pdo->beginTransaction();

            // 3. حذف سجلات الصور من قاعدة البيانات (في حال عدم وجود Cascade)
            $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$id]);
            
            // 4. حذف سجلات الفيديو المرتبطة
            $pdo->prepare("DELETE FROM product_videos WHERE product_id = ?")->execute([$id]);

            // 5. حذف المنتج نفسه
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $ok = $stmt->execute([$id]);

            if ($ok) {
                // 6. الآن نحذف الملفات الفعلية من المجلد فقط بعد التأكد من نجاح حذف السجلات
                foreach ($images as $img) {
                    $fullPath = __DIR__ . '/../' . $img['image_path'];
                    if (file_exists($fullPath)) {
                        @unlink($fullPath);
                    }
                }
                
                $pdo->commit();
                log_audit($pdo, $_SESSION['admin_id'] ?? null, 'delete_product', ['product_id'=>$id, 'title'=>$prod['title']]);
                echo json_encode(["success" => true, "message" => "تم حذف المنتج وجميع ملفاته بنجاح"]);
            } else {
                $pdo->rollBack();
                echo json_encode(["success" => false, "message" => "فشل حذف المنتج من قاعدة البيانات"]);
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(["success"=>false, "message"=>$e->getMessage()]); 
        }
        break;
    case 'edit_product':
        try {
            verify_csrf();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('الطريقة غير مدعومة');
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) throw new Exception('معرف المنتج غير صالح');
            $title = trim($_POST['title'] ?? '');
            $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
            $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
            $stock = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : 0;
            $desc = $_POST['description'] ?? '';
            if ($title === '') throw new Exception('اسم المنتج مطلوب');
            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE products SET category_id = ?, title = ?, description = ?, price = ? WHERE id = ?");
            $upd->execute([$category_id, $title, $desc, $price, $id]);
            if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                $imgPath = handle_image_upload('main_image');
                $pdo->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ?")->execute([$id]);
                $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, 1)")->execute([$id, $imgPath]);
            }
            $additional = handle_images_uploads('images');
            if (!empty($additional)) {
                $imgStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, 0)");
                foreach ($additional as $p) { $imgStmt->execute([$id, $p]); }
            }
            $pdo->commit();
            try { log_audit($pdo, $_SESSION['admin_id'] ?? null, 'edit_product', ['product_id'=>$id, 'title'=>$title]); } catch (Exception $e) {}
            echo json_encode(["success"=>true, "message"=>"تم التحديث"]);
        } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); echo json_encode(["success"=>false, "message"=>"فشل التحديث: " . $e->getMessage()]); }
        break;

    case 'toggle_user_role':
        try {
            verify_csrf();
            $data = get_json_body();
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            if ($id <= 0) { echo json_encode(["success"=>false]); break; }
            $r = $pdo->prepare("SELECT role_id FROM users WHERE id = ? LIMIT 1");
            $r->execute([$id]);
            $u = $r->fetch();
            if (!$u) { echo json_encode(["success"=>false]); break; }
            $newRole = $u['role_id'] == 1 ? 2 : 1;
            $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ?")->execute([$newRole, $id]);
            try { log_audit($pdo, $_SESSION['admin_id'] ?? null, 'toggle_user_role', ['user_id'=>$id, 'new_role'=>$newRole]); } catch (Exception $e) {}
            echo json_encode(["success"=>true, "message"=>"تم تبديل الصلاحية"]);
        } catch (Exception $e) { echo json_encode(["success"=>false, "message"=>$e->getMessage()]); }
        break;

    case 'delete_product_image':
    try {
        verify_csrf();
        $data = get_json_body();
        $id = (int)($data['id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT image_path, product_id FROM product_images WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row) {
            $fullPath = __DIR__ . '/../' . $row['image_path'];
            // 1. حذف السجل أولاً
            $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$id]);
            
            // 2. حذف الملف الفعلي (استخدام @ لمنع إظهار خطأ إذا كان الملف محذوفاً أصلاً)
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            
            log_audit($pdo, $_SESSION['admin_id'], 'delete_image', ['product_id'=>$row['product_id']]);
            echo json_encode(["success" => true]);
        } else {
            throw new Exception("الصورة غير موجودة");
        }
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "خطأ في الحذف"]);
    }
    break;

    case 'set_main_image':
        try {
            verify_csrf();
            $data = get_json_body();
            $id = isset($data['id']) ? (int)$data['id'] : 0; // image id
            if ($id <= 0) { echo json_encode(["success"=>false]); break; }
            $stmt = $pdo->prepare("SELECT product_id FROM product_images WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) { echo json_encode(["success"=>false]); break; }
            $product_id = $row['product_id'];
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ?")->execute([$product_id]);
            $pdo->prepare("UPDATE product_images SET is_main = 1 WHERE id = ?")->execute([$id]);
            $pdo->commit();
            try { log_audit($pdo, $_SESSION['admin_id'] ?? null, 'set_main_image', ['product_id'=>$product_id, 'image_id'=>$id]); } catch (Exception $e) {}
            echo json_encode(["success"=>true]);
        } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); echo json_encode(["success"=>false, "message"=>$e->getMessage()]); }
        break;

    case 'add_review':
    try {
        verify_csrf();
        $data = get_json_body();
        
        $product_id = isset($data['product_id']) ? (int)$data['product_id'] : 0;
        $rating = isset($data['rating']) ? (int)$data['rating'] : 0;
        $comment = trim($data['comment'] ?? '');
        $visitor_name = trim($data['visitor_name'] ?? 'زائر');
        
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR']; // جلب عنوان الـ IP

        if ($product_id <= 0 || $rating < 1 || $rating > 5) throw new Exception('بيانات غير صحيحة');

        // منع تكرار التقييم: التحقق إذا كان الـ IP قد قيم هذا المنتج مسبقاً
        $check = $pdo->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND (user_id = ? OR ip_address = ?) LIMIT 1");
        $check->execute([$product_id, $user_id, $ip]);
        if ($check->fetch()) {
            throw new Exception('لقد قمت بتقييم هذا المنتج مسبقاً');
        }

        // إذا كان زائراً، ندمج اسمه مع التعليق
        if (!$user_id) {
            $comment = "بواسطة ($visitor_name): " . $comment;
        }

        // تنفيذ الإضافة
        $stmt = $pdo->prepare("INSERT INTO product_reviews (product_id, user_id, rating, comment, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $user_id, $rating, $comment, $ip]);

        echo json_encode(["success" => true, "message" => 'تم إرسال تقييمك بنجاح']);
    } 
    catch (Exception $e) {
     // تسجيل الخطأ الحقيقي في السيرفر للمبرمج
        error_log($e->getMessage()); 
        
        // إرسال رسالة "لطيفة" ومبهمة للمستخدم
        echo json_encode([
            "success" => false, 
            "message" => "حدث خطأ غير متوقع، يرجى المحاولة لاحقاً" 
        ]);
    }
    break;

    default:
        echo json_encode(["success" => false, "message" => "طلب غير معروف"]);
        break;
}
