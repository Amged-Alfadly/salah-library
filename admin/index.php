<?php
// تشديد إعدادات الجلسة متناسق مع صفحة الدخول
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}
require_once '../api/db_config.php';

// جلب البيانات السريعة للإحصائيات (جدول site_analytics و products)
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_views = $pdo->query("SELECT SUM(total_page_views) FROM site_analytics")->fetchColumn() ?: 0;
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();

require_once __DIR__ . '/layout.php';
admin_start_page('لوحة الإدارة', 'dashboard');
?>
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .card {
        background: #fff;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        text-align: center;
        transition: transform 0.3s ease;
    }
    .card:hover { transform: translateY(-5px); }
    .card h3 { color: #666; font-size: 0.9rem; margin: 10px 0; }
    .card p { font-size: 1.8rem; font-weight: bold; color: #2d3436; margin: 0; }
    .form-card { background: #fff; padding: 20px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
</style>
        <!-- 1. تبويب الإحصائيات -->
<section id="dashboard" class="tab-content active">
    <div class="stats-grid">
        <div class="card">
            <i class="fas fa-box-open" style="color:#4361ee; font-size: 2rem;"></i>
            <h3>إجمالي المنتجات</h3>
            <p><?= $total_products ?></p>
        </div>
        <div class="card">
            <i class="fas fa-eye" style="color:#2ecc71; font-size: 2rem;"></i>
            <h3>زيارات الموقع</h3>
            <p><?= $total_views ?></p>
        </div>
        <div class="card">
            <i class="fas fa-users" style="color:#e67e22; font-size: 2rem;"></i>
            <h3>المستخدمين</h3>
            <p><?= $total_users ?></p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
        <div class="form-card">
            <h3>توزيع المنتجات حسب الأقسام</h3>
            <canvas id="categoryChart" height="200"></canvas>
        </div>
        <div class="form-card">
            <h3>ملخص النشاط</h3>
            <canvas id="activityChart" height="200"></canvas>
        </div>
    </div>
</section>
            <!-- 2. روابط سريعة لإدارة الأقسام المختلفة - توجه إلى صفحات مستقلة لإدارة المحتوى -->
            <section id="products-mgr" class="tab-content">
                <div class="form-card">
                    <h3>روابط الإدارة السريعة</h3>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <a class="btn-save" href="products.php">
                            <i class="fas fa-boxes"></i> إدارة المنتجات
                        </a>
                        <a class="btn-save btn-info" href="add_product.php"> 
                            <i class="fas fa-plus-circle"></i> إضافة منتج
                        </a>
                        <a class="btn-save btn-purple" href="categories.php"> 
                            <i class="fas fa-tags"></i> إدارة التصنيفات
                        </a>
                        <a class="btn-save btn-warning" href="users.php"> 
                            <i class="fas fa-users"></i> إدارة المستخدمين
                        </a>
                        <a class="btn-save btn-dark" href="change_password.php"> 
                            <i class="fas fa-lock"></i> إعدادات الأمان
                        </a>
                    </div>
                    <p style="margin-top:12px; color:#666;">الروابط أعلاه تفتح صفحات منفصلة لإدارة المحتوى — هذا يمنع الالتباس الناتج عن النماذج الداخلية.</p>
                </div>
            </section>

            <!-- 3. تبويب إدارة التصنيفات -->
            <section id="cats-mgr" class="tab-content">
                <div class="form-card">
                    <h3>إضافة تصنيف جديد</h3>
                    <form id="add-cat-form">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="text" name="cat_name" placeholder="اسم القسم (مثلاً: عطورات)" required>
                        <button type="submit">إضافة قسم</button>
                    </form>
                    <div id="cats-list"></div>
                </div>
            </section>

            <!-- 4. تبويب الأمان (تغيير كلمة المرور) -->
            <section id="settings" class="tab-content">
                <div class="form-card" style="max-width: 500px;">
                    <h3>تأمين حساب المشرف</h3>
                    <form id="change-pass-form">
                        <div class="form-group">
                            <label>كلمة المرور الحالية</label>
                            <input type="password" name="old_pass" required>
                        </div>
                        <div class="form-group">
                            <label>كلمة المرور الجديدة</label>
                            <input type="password" name="new_pass" required>
                        </div>
                        <button type="submit" class="btn-save">تحديث بيانات الدخول</button>
                    </form>
                </div>
            </section>

    <script>
        // AJAX helpers
        async function apiPostForm(action, formElement) {
            const formData = new FormData(formElement);
            const res = await fetch('api_handler.php?action='+encodeURIComponent(action), {method:'POST', headers:{'X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, body: formData});
            return res.json();
        }

        async function apiPostJSON(action, obj) {
            const res = await fetch('api_handler.php?action='+encodeURIComponent(action), {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, body: JSON.stringify(obj)});
            return res.json();
        }

        // إرسال إضافة منتج (يعمل فقط إن كان النموذج موجوداً داخل الصفحة)
        const fullProductForm = document.getElementById('full-product-form');
        if (fullProductForm) {
            fullProductForm.onsubmit = async (e) => {
                e.preventDefault();
                const data = await apiPostForm('add_product', e.target);
                alert(data.message || (data.success? 'تم' : 'فشل'));
                if (data.success) { e.target.reset(); if (typeof loadProducts === 'function') loadProducts(); }
            };
        }

        // تغيير كلمة المرور
        document.getElementById('change-pass-form').onsubmit = async (e) => {
            e.preventDefault();
            const data = await apiPostForm('change_password', e.target);
            alert(data.message);
        };

        // تحميل وعرض المنتجات
        async function loadProducts() {
            const res = await fetch('api_handler.php?action=list_products');
            const products = await res.json();
            const container = document.getElementById('products-list-container');
            if (!products || products.length === 0) { container.innerHTML = '<p>لا توجد منتجات</p>'; return; }
            let html = '<table class="admin-table"><thead><tr><th>#</th><th>العنوان</th><th>تصنيف</th><th>السعر</th><th>مخزون</th><th>الصورة</th><th>إجراءات</th></tr></thead><tbody>';
            for (const p of products) {
                html += `<tr><td>${p.id}</td><td>${escapeHtml(p.title)}</td><td>${escapeHtml(p.category_name || '')}</td><td>${p.price}</td><td>${p.stock_quantity}</td><td>${p.main_image? `<img src="../${p.main_image}" style="max-width:80px">` : ''}</td><td><a href="edit_product.php?id=${p.id}">تعديل</a> | <a href="#" onclick="deleteProduct(${p.id})">حذف</a></td></tr>`;
            }
            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // تحميل وعرض التصنيفات
        async function loadCategories() {
            const res = await fetch('api_handler.php?action=list_categories');
            const cats = await res.json();
            const el = document.getElementById('cats-list');
            if (!cats || cats.length === 0) { el.innerHTML = '<p>لا توجد تصنيفات</p>'; return; }
            let html = '<ul>';
            for (const c of cats) html += `<li>${escapeHtml(c.name)} <button onclick="deleteCat(${c.id})">حذف</button></li>`;
            html += '</ul>';
            el.innerHTML = html;
        }

        // تحميل وعرض المستخدمين
        async function loadUsers() {
            const res = await fetch('api_handler.php?action=list_users');
            const users = await res.json();
            const section = document.getElementById('users-mgr');
            if (!users || users.length === 0) { section.innerHTML = '<p>لا يوجد مستخدمون</p>'; return; }
            let html = '<table class="admin-table"><thead><tr><th>#</th><th>اسم المستخدم</th><th>البريد</th><th>الصلاحية</th><th>إجراءات</th></tr></thead><tbody>';
            for (const u of users) {
                html += `<tr><td>${u.id}</td><td>${escapeHtml(u.username)}</td><td>${escapeHtml(u.email)}</td><td>${u.role_id==1? 'مدير':'مستخدم'}</td><td><button onclick="changeRole(${u.id})">تبديل الصلاحية</button></td></tr>`;
            }
            html += '</tbody></table>';
            section.innerHTML = html;
        }

        // utils
        function escapeHtml(s) { if (!s) return ''; return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

        // load initial (نفّذ فقط عندما توجد الحاويات المقابلة)
        if (document.getElementById('products-list-container') && typeof loadProducts === 'function') loadProducts();
        if (document.getElementById('cats-list') && typeof loadCategories === 'function') loadCategories();
        if (document.getElementById('users-mgr') && typeof loadUsers === 'function') loadUsers();

        // Actions used by rendered lists
        async function deleteProduct(id){
            if(!confirm('تأكيد حذف المنتج #' + id + '؟')) return;
            const data = await apiPostJSON('delete_product', {id});
            alert(data.message || (data.success? 'تم':'فشل'));
            if (data.success) loadProducts();
        }

        async function deleteCat(id){
            if(!confirm('حذف التصنيف؟')) return;
            const data = await apiPostJSON('delete_category', {id});
            alert(data.message || (data.success? 'تم':'فشل'));
            if (data.success) loadCategories();
        }

        async function changeRole(id){
            const data = await apiPostJSON('toggle_user_role', {id});
            alert(data.message || (data.success? 'تم':'فشل'));
            if (data.success) loadUsers();
        }


        // إضافة معالجة إرسال صيغة إضافة التصنيف في هذه الصفحة
        document.getElementById('add-cat-form').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const res = await fetch('api_handler.php?action=add_category', {method: 'POST', headers: {'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?>'}, body: formData});
            const data = await res.json(); alert(data.message || (data.success? 'تم':'فشل')); if (data.success) e.target.reset();
        };
        document.addEventListener('DOMContentLoaded', function() {
    // 1. رسم بياني دائري للتصنيفات
    const ctxCat = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: [<?php foreach($categories as $c) echo "'".$c['name']."',"; ?>],
            datasets: [{
                data: [/* هنا يمكنك جلب عدد المنتجات لكل قسم مستقبلاً */ 10, 20, 15, 5], 
                backgroundColor: ['#4361ee', '#2ecc71', '#e67e22', '#e74c3c', '#9b59b6'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // 2. رسم بياني خطي للزيارات (مثال تجريبي)
    const ctxAct = document.getElementById('activityChart').getContext('2d');
    new Chart(ctxAct, {
        type: 'line',
        data: {
            labels: ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'],
            datasets: [{
                label: 'الزيارات اليومية',
                data: [65, 59, 80, 81, 56, 55, 40],
                fill: true,
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                borderColor: '#4361ee',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });
});
    </script>

<?php admin_end_page(); ?>