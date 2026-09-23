<?php
session_start();
// التحقق من صلاحيات المشرف
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}
require_once '../api/db_config.php';

try {
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            ORDER BY p.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    die("خطأ في جلب البيانات: " . $e->getMessage());
}

require_once __DIR__ . '/layout.php';
admin_start_page('إدارة كافة المنتجات', 'products');
?>

<style>
    /* حاوية العرض الرئيسية */
    .products-container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 0 15px;
        direction: rtl;
    }

    /* بطاقة المحتوى */
    .content-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        padding: 20px;
        overflow: hidden;
    }

    /* الهيدر - متجاوب */
    .header-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap; /* يسمح بنزول العناصر في الجوال */
        gap: 15px;
    }

    .btn-add-new {
        background-color: #28a745;
        color: #fff;
        border: none;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
        white-space: nowrap;
    }

    .btn-add-new:hover { background-color: #218838; transform: translateY(-2px); }

    /* الحاوية السحرية للجوال - تمنع خروج الجدول عن النطاق */
    .table-responsive-wrapper {
        width: 100%;
        overflow-x: auto; /* تمرير أفقي عند الحاجة فقط */
        -webkit-overflow-scrolling: touch;
        border: 1px solid #f0f0f0;
        border-radius: 8px;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px; /* يضمن مساحة مريحة للبيانات على الجوال */
    }

    .admin-table th {
        background: #f8f9fa;
        padding: 15px;
        text-align: right;
        font-weight: 700;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
    }

    .admin-table td {
        padding: 15px;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
        color: #333;
    }

    .badge-cat {
        background: #e9ecef;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.85em;
        color: #495057;
    }

    .action-links a {
        text-decoration: none;
        margin-left: 15px;
        font-weight: 600;
        font-size: 0.9em;
    }

    /* تصميم المودال للجوال */
    .admin-form-modal {
        background: #fff;
        padding: 25px;
        border-radius: 12px;
        width: 95%;
        max-width: 650px;
        max-height: 85vh;
        overflow-y: auto;
    }

    @media (max-width: 600px) {
        .header-flex { flex-direction: column; align-items: stretch; text-align: center; }
        .btn-add-new { width: 100%; }
        .content-card { padding: 15px; }
    }
</style>

<div class="products-container">
    
    <div class="header-flex">
        <h2 style="margin:0; color:#2d3436;">قائمة المنتجات (<?= count($products) ?>)</h2>
        <button class="btn-add-new" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> إضافة منتج جديد
        </button>
    </div>

    <div style="margin-bottom: 20px;">
        <a href="index.php" style="text-decoration:none; color:#4361ee; font-weight:500;">
            <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
        </a>
    </div>

    <div class="content-card">
        <div class="table-responsive-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>اسم المنتج</th>
                        <th>التصنيف</th>
                        <th>السعر</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:40px; color:#999;">لا توجد منتجات مضافة حالياً.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td style="color:#adb5bd;">#<?= $p['id'] ?></td>
                                <td><strong style="color:#2d3436;"><?= htmlspecialchars($p['title']) ?></strong></td>
                                <td><span class="badge-cat"><?= htmlspecialchars($p['category_name'] ?? 'عام') ?></span></td>
                                <td style="white-space: nowrap; font-weight:bold; color:#2ecc71;"><?= number_format($p['price'], 2) ?> ر.س</td>
                                <td class="action-links">
                                    <a href="edit_product.php?id=<?= $p['id'] ?>" style="color: #4361ee;"><i class="fas fa-edit"></i> تعديل</a>
                                    <a href="javascript:void(0)" onclick="deleteProduct(<?= $p['id'] ?>)" style="color: #e74c3c;"><i class="fas fa-trash"></i> حذف</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- مودال إضافة المنتج -->
<div id="add-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
    <div class="admin-form-modal" id="add-modal-content">
        <!-- يتم تحميل المحتوى هنا -->
    </div>
</div>

<script>
/** حذف منتج **/
async function deleteProduct(id) {
    if (!confirm('هل أنت متأكد من حذف المنتج رقم #' + id + '؟ لا يمكن التراجع عن هذه الخطوة.')) return;
    
    try {
        const res = await fetch('api_handler.php?action=delete_product', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= $_SESSION['csrf_token'] ?? '' ?>'
            },
            body: JSON.stringify({ id: id })
        });
        
        const data = await res.json();
        if (data.success) {
            location.reload();
        } else {
            alert('خطأ: ' + data.message);
        }
    } catch (error) {
        alert('حدث خطأ في الاتصال بالخادم');
    }
}

/** فتح نافذة الإضافة **/
function openAddModal() {
    const overlay = document.getElementById('add-modal');
    const content = document.getElementById('add-modal-content');
    
    overlay.style.display = 'flex';
    content.innerHTML = '<div style="padding:40px; text-align:center;"><i class="fas fa-spinner fa-spin"></i> جاري تحميل النموذج...</div>';
    
    fetch('add_product_form.php')
        .then(r => r.text())
        .then(html => { 
            content.innerHTML = html; 
        })
        .catch(e => { 
            content.innerHTML = '<div style="padding:30px; color:red; text-align:center;">خطأ في تحميل النموذج.</div>'; 
        });
}

function closeAddModal() {
    document.getElementById('add-modal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('add-modal')) {
        closeAddModal();
    }
}
</script>

<?php admin_end_page(); ?>