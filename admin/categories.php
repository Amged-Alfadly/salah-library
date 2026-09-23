<?php
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}
require_once '../api/db_config.php';

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

require_once __DIR__ . '/layout.php';
admin_start_page('إدارة التصنيفات', 'categories');
?>

<style>
    /* حاوية رئيسية مرنة */
    .categories-wrapper {
        width: 95%;
        max-width: 1100px; /* يمنع التمدد الزائد على الكمبيوتر */
        margin: 30px auto;
        direction: rtl;
    }

    /* رأس الصفحة */
    .page-title-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    }

    /* نموذج الإضافة - تم تحسينه للكمبيوتر والجوال */
    .add-cat-card {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        margin-bottom: 25px;
    }

    .quick-add-form {
        display: grid;
        grid-template-columns: 1fr auto; /* يضمن بقاء الزر بجانب الحقل على الكمبيوتر */
        gap: 15px;
    }

    .quick-add-form input {
        padding: 12px 15px;
        border: 2px solid #f1f3f5;
        border-radius: 8px;
        font-size: 16px;
        transition: 0.3s;
    }

    .quick-add-form input:focus {
        border-color: #4361ee;
        outline: none;
        background: #fdfdfd;
    }

    .btn-submit {
        background: #4361ee;
        color: white;
        border: none;
        padding: 0 30px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-submit:hover { background: #3730a3; }

    /* الجدول الاحترافي */
    .table-container {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        overflow: hidden; /* لضمان زوايا دائرية */
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table th {
        background: #f8f9fa;
        padding: 18px;
        text-align: right;
        color: #495057;
        font-weight: 700;
        border-bottom: 2px solid #eee;
    }

    .admin-table td {
        padding: 16px 18px;
        border-bottom: 1px solid #f8f9fa;
        vertical-align: middle;
    }

    .admin-table tr:hover { background: #fcfdff; }

    /* أزرار الإجراءات */
    .actions {
        display: flex;
        gap: 8px;
        justify-content: flex-start;
    }

    .btn-action {
        padding: 8px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: 0.2s;
    }

    .edit-btn { background: #e7f0ff; color: #4361ee; }
    .delete-btn { background: #fff0f0; color: #e03131; }

    .edit-btn:hover { background: #4361ee; color: #fff; }
    .delete-btn:hover { background: #e03131; color: #fff; }

    /* تعديلات الجوال */
    @media (max-width: 600px) {
        .quick-add-form {
            grid-template-columns: 1fr; /* جعل الحقل والزر تحت بعض في الجوال */
        }
        .btn-submit { padding: 12px; }
        .page-title-section { flex-direction: column; align-items: flex-start; gap: 10px; }
        .admin-table th:nth-child(1), .admin-table td:nth-child(1) { display: none; } /* إخفاء رقم ID في الجوال لتوفير مساحة */
    }
</style>

<div class="categories-wrapper">
    
    <div class="page-title-section">
        <h2 style="margin:0;">إدارة التصنيفات</h2>
        <div style="color: #636e72;">
            <i class="fas fa-layer-group"></i> <?= count($categories) ?> تصنيف متاح
        </div>
    </div>

    <div class="add-cat-card">
        <form id="add-cat" class="quick-add-form" method="post" action="api_handler.php?action=add_category">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="text" name="cat_name" placeholder="اكتب اسم التصنيف الجديد هنا..." required>
            <button type="submit" class="btn-submit">إضافة الآن</button>
        </form>
    </div>

    <div class="table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 80px;">#</th>
                    <th>اسم التصنيف</th>
                    <th style="width: 200px;">إجراءات التحكم</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $c): ?>
                <tr>
                    <td style="color: #adb5bd; font-weight: bold;">#<?= $c['id'] ?></td>
                    <td><span id="cat-name-<?= $c['id'] ?>" style="font-weight: 600; color: #2d3436;"><?= htmlspecialchars($c['name']) ?></span></td>
                    <td class="actions">
                        <a href="javascript:void(0)" class="btn-action edit-btn" onclick="editCat(<?= $c['id'] ?>)">
                            تعديل
                        </a>
                        <a href="javascript:void(0)" class="btn-action delete-btn" onclick="deleteCat(<?= $c['id'] ?>)">
                            حذف
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
    // الدوال الخاصة بك (Fetch API) ستبقى تعمل هنا بشكل ممتاز كما هي
    document.getElementById('add-cat').onsubmit = async function(e){
        e.preventDefault();
        const form = e.target;
        const fd = new FormData(form);
        const res = await fetch(form.action, {method:'POST', headers:{'X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, body: fd});
        const data = await res.json();
        if (data.success) location.reload(); else alert(data.message);
    };

    async function deleteCat(id){
        if(!confirm('هل أنت متأكد من حذف هذا التصنيف؟')) return;
        const res = await fetch('api_handler.php?action=delete_category', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, body:JSON.stringify({id})});
        const data = await res.json(); if(data.success) location.reload();
    }

    async function editCat(id){
        const current = document.getElementById('cat-name-' + id).innerText;
        const nv = prompt('تعديل الاسم:', current);
        if (nv && nv !== current) {
            const res = await fetch('api_handler.php?action=edit_category', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, body: JSON.stringify({id, name: nv})});
            const data = await res.json();
            if (data.success) document.getElementById('cat-name-' + id).innerText = nv;
        }
    }
</script>

<?php admin_end_page(); ?>