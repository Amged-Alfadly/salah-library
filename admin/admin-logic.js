/**
 * لوحة تحكم مكتبة النخبة - المحرك التشغيلي الاحترافي
 * متوافق تماماً مع جداول: products, categories, product_videos, product_images
 */

document.addEventListener('DOMContentLoaded', () => {
    // جلب البيانات الأولية
    updateDashboardStats();
    loadCategoriesForSelect();

    // القسم الافتراضي
    showSection('stats');

    // إغلاق المودالات عند النقر الخارج
    window.onclick = (event) => {
        if (event.target.classList.contains('modal-overlay')) {
            closeAllModals();
        }
    };
});

// --- [1. نظام التنقل الذكي] ---
window.showSection = function (sectionId) {
    // إخفاء الكل
    document.querySelectorAll('.tab-content').forEach(section => {
        section.classList.remove('active');
    });

    // إزالة النشاط من القائمة
    document.querySelectorAll('.sidebar li').forEach(li => li.classList.remove('active'));

    // إظهار القسم المختار
    const target = document.getElementById(sectionId); // يتوافق مع IDs التبويبات في HTML
    if (target) target.classList.add('active');

    // تفعيل الزر في السايدبار
    const activeMenu = document.querySelector(`li[onclick*="${sectionId}"]`);
    if (activeMenu) activeMenu.classList.add('active');

    // تحميل بيانات القسم
    if (sectionId === 'products-mgr') loadAdminProducts();
    if (sectionId === 'cats-mgr') loadAdminCategories();
};

// --- [2. تحديث الإحصائيات من قاعدة البيانات] ---
async function updateDashboardStats() {
    try {
        // نستخدم api_handler الذي صممناه سابقاً أو ملفاتك الخاصة
        const response = await fetch('api_handler.php?action=get_stats');
        const stats = await response.json();

        document.getElementById('stat-products-count').innerText = stats.total_products;
        document.getElementById('stat-views-count').innerText = stats.total_views;
        document.getElementById('stat-users-count').innerText = stats.total_users;
    } catch (error) {
        console.error("خطأ في جلب الإحصائيات");
    }
}

// --- [3. إدارة المنتجات مع دعم الصور والفيديو] ---
async function loadAdminProducts() {
    const container = document.getElementById('products-list-container');
    container.innerHTML = '<div class="loader">جاري التحميل...</div>';

    try {
        const response = await fetch('api_handler.php?action=list_products');
        const products = await response.json();

        let html = `
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>الصورة</th>
                        <th>العنوان</th>
                        <th>التصنيف</th>
                        <th>السعر</th>
                        <th>المخزن</th>
                        <th>التحكم</th>
                    </tr>
                </thead>
                <tbody>`;

        html += products.map(p => `
            <tr>
                <td><img src="uploads/${p.main_image}" class="table-img" onerror="this.src='assets/default.png'"></td>
                <td>${p.title}</td>
                <td><span class="badge">${p.category_name}</span></td>
                <td>${p.price} $</td>
                <td>${p.stock_quantity}</td>
                <td>
                    <button class="action-btn edit-btn" onclick="editProduct(${p.id})"><i class="fas fa-edit"></i></button>
                    <button class="action-btn delete-btn" onclick="deleteProduct(${p.id})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');

        html += `</tbody></table>`;
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<p class="error">فشل تحميل المنتجات</p>';
    }
}

// --- [4. معالجة نموذج الإضافة الشامل] ---
const fullProductForm = document.getElementById('full-product-form');
if (fullProductForm) {
    fullProductForm.onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(fullProductForm);

        // إظهار مؤشر تحميل على الزر
        const btn = e.submitter;
        const originalText = btn.innerText;
        btn.innerText = "جاري الحفظ...";
        btn.disabled = true;

        try {
            const res = await fetch('api_handler.php?action=add_product', {
                method: 'POST',
                body: formData
            });
            const result = await res.json();

            if (result.success) {
                alert("✅ تم إضافة المنتج بنجاح مع الصور والفيديو");
                fullProductForm.reset();
                loadAdminProducts();
                updateDashboardStats();
            } else {
                alert("❌ خطأ: " + result.message);
            }
        } catch (error) {
            alert("حدث خطأ في الاتصال");
        } finally {
            btn.innerText = originalText;
            btn.disabled = false;
        }
    };
}

// --- [5. إدارة الأمان (تغيير كلمة المرور)] ---
const changePassForm = document.getElementById('change-pass-form');
if (changePassForm) {
    changePassForm.onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(changePassForm);

        const res = await fetch('api_handler.php?action=change_password', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            alert("✅ تم تحديث كلمة المرور");
            changePassForm.reset();
        } else {
            alert("❌ " + data.message);
        }
    };
}

// دالة مساعدة لإغلاق كافة المودالات
function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
}