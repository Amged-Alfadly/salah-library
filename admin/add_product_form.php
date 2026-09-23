<?php
session_start();
if (!isset($_SESSION['is_admin'])) { http_response_code(403); exit(); }
require_once __DIR__ . '/../api/db_config.php';
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<div class="form-card" id="add-product-modal-card">
    <h3>إضافة منتج جديد</h3>
    <form id="modal-add-product-form" enctype="multipart/form-data" method="post" action="api_handler.php?action=add_product">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="grid-inputs">
            <div class="form-group">
                <label>اسم المنتج</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>التصنيف</label>
                <select name="category_id">
                    <option value="">-- اختر --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>السعر</label>
                <input type="number" step="0.01" name="price" required>
            </div>
            <div class="form-group">
                <label>الصورة الرئيسية</label>
                <input type="file" name="main_image" accept="image/*" required>
            </div>
            <div class="form-group">
                <label>صور إضافية (اختياري، يمكنك اختيار عدة صور)</label>
                <input type="file" name="images[]" accept="image/*" multiple>
            </div>
            <div class="form-group" style="grid-column:1/-1;">
                <label>الوصف</label>
                <textarea name="description"></textarea>
            </div>
            <div class="form-group">
                <label>رابط فيديو</label>
                <input type="url" name="video_url">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn-save" type="submit">حفظ</button>
            <button type="button" class="cancel-btn" onclick="closeAddModal()">إغلاق</button>
        </div>
    </form>
</div>
<script>
document.getElementById('modal-add-product-form').onsubmit = async function(e){
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    try {
        const res = await fetch(form.action, {method:'POST', headers:{'X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, body: fd});
        const data = await res.json();
        alert(data.message || (data.success? 'تم':'فشل'));
        if (data.success) {
            closeAddModal();
            location.reload();
        }
    } catch (err) { console.error(err); alert('خطأ في الاتصال'); }
};
</script>
