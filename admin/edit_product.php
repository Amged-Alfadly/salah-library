<?php
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}
require_once '../api/db_config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo "رقم المنتج غير صالح";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
$stmt->execute([':id'=>$id]);
$product = $stmt->fetch();
if (!$product) {
    echo "المنتج غير موجود";
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// جلب صور المنتج الحالية
$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC");
$imgStmt->execute([$id]);
$product_images = $imgStmt->fetchAll();

require_once __DIR__ . '/layout.php';
admin_start_page('تعديل منتج', 'products'); // تأكد من استدعاء الدالة هنا
?>

<div class="form-card">
    <h3>تعديل المنتج #<?= htmlspecialchars($product['id']) ?></h3>
    <form id="edit-product-form" enctype="multipart/form-data" method="post" action="api_handler.php?action=edit_product">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <input type="hidden" name="id" value="<?= htmlspecialchars($product['id']) ?>">
        
        <div class="grid-inputs">
            <div class="form-group">
                <label>اسم المنتج</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($product['title']) ?>">
            </div>
            <div class="form-group">
                <label>التصنيف</label>
                <select name="category_id">
                    <option value="">-- اختر --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id']==$product['category_id']? 'selected':'' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>السعر</label>
                <input type="number" step="0.01" name="price" required value="<?= htmlspecialchars($product['price']) ?>">
            </div>
            <div class="form-group">
                <label>الصورة الرئيسية (اختياري لتغييرها)</label>
                <input type="file" name="main_image" accept="image/*">
            </div>
            <div class="form-group">
                <label>إضافة صور إضافية</label>
                <input type="file" name="images[]" accept="image/*" multiple>
            </div>
        </div>

        <?php if (!empty($product_images)): ?>
        <div class="form-group">
            <label>صور المنتج الحالية</label>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php foreach ($product_images as $img): ?>
                    <div style="text-align:center; border:1px solid #ddd; padding:5px; border-radius:8px;">
                        <img src="../<?= htmlspecialchars($img['image_path']) ?>" style="width:120px;height:90px;object-fit:cover;border-radius:8px;display:block;margin-bottom:6px;">
                        <div>
                            <button type="button" class="btn-delete-img" onclick="deleteImage(<?= $img['id'] ?>)">حذف</button>
                            <label style="margin-right:6px;">
                                <input type="radio" name="main_choice" <?= $img['is_main']? 'checked':'' ?> onclick="setMain(<?= $img['id'] ?>)"> رئيسية
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="form-group">
            <label>الوصف</label>
            <textarea name="description" rows="5" style="width:100%"><?= htmlspecialchars($product['description']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-save">تحديث المنتج</button>
            <a class="cancel-btn" href="products.php">إلغاء والعودة</a>
        </div>
    </form>
</div>

<script>
document.getElementById('edit-product-form').onsubmit = async function(e){
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    
    try {
        const res = await fetch(form.action, {
            method:'POST', 
            headers:{'X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, 
            body: fd
        });
        const data = await res.json();
        alert(data.message || (data.success ? 'تم التحديث بنجاح' : 'فشل التحديث'));
        if (data.success) location.href = 'products.php';
    } catch (err) {
        alert('حدث خطأ في الاتصال بالخادم');
    }
};

async function deleteImage(id){
    if(!confirm('هل أنت متأكد من حذف هذه الصورة؟')) return;
    const res = await fetch('api_handler.php?action=delete_product_image', {
        method:'POST', 
        headers:{'Content-Type':'application/json','X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, 
        body: JSON.stringify({id})
    });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.message);
}

async function setMain(id){
    const res = await fetch('api_handler.php?action=set_main_image', {
        method:'POST', 
        headers:{'Content-Type':'application/json','X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, 
        body: JSON.stringify({id})
    });
    const data = await res.json();
    if (!data.success) alert(data.message);
    else location.reload();
}
</script>

<?php admin_end_page(); ?>