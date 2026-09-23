<?php
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}
require_once '../api/db_config.php';
require_once __DIR__ . '/layout.php';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
// تأكد من وجود CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

admin_start_page('إضافة منتج', 'products');
?>

    <div class="form-card">
        <h3>إضافة منتج جديد</h3>
        <form id="add-product-form" enctype="multipart/form-data" method="post" action="api_handler.php?action=add_product">
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
                <!-- تم إزالة حقل الكمية عند الإضافة وفقاً للطلب -->
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
                <a class="cancel-btn" href="products.php">إلغاء</a>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> // عرض اشعارات افضل من alert 
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // استخدمت نفس منطق "add-cat" الموجود في كود التصنيفات الخاص بك
    document.getElementById('add-product-form').onsubmit = async function(e) {
        e.preventDefault(); // منع الانتقال لصفحة الجيسون
        
        const form = e.target;
        const fd = new FormData(form);
        
        // إظهار حالة تحميل بسيطة (اختياري)
        const submitBtn = form.querySelector('.btn-save');
        const originalText = submitBtn.innerText;
        submitBtn.disabled = true;
        submitBtn.innerText = 'جاري الحفظ...';

        try {
            // نفس طريقة fetch في كود التصنيفات
            const res = await fetch(form.action, {
                method: 'POST', 
                headers: {
                    'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'
                }, 
                body: fd
            });

            // استقبال الرد وتحويله لـ JSON
            const data = await res.json();

            if (data.success) {
                // بدلاً من location.reload()، نستخدم SweetAlert لإبلاغ المستخدم
                Swal.fire({
                    title: 'تمت الإضافة!',
                    text: data.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // تفريغ الفورم بعد النجاح كما في منطق التصنيفات
                    form.reset(); 
                    // إذا أردت الانتقال لصفحة المنتجات فعل هذا السطر:
                    // window.location.href = 'products.php';
                });
            } else {
                // إظهار الخطأ إذا فشلت العملية
                Swal.fire('خطأ', data.message, 'error');
            }
        } catch (err) {
            console.error(err);
            Swal.fire('خطأ', 'فشل في الاتصال بالسيرفر', 'warning');
        } finally {
            // إعادة الزر لحالته
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        }
    };
</script>

<?php admin_end_page(); ?>

