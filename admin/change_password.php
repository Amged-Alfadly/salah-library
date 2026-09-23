<?php
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/layout.php';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
admin_start_page('تغيير كلمة المرور', 'settings');
?>

<style>
    /* حاوية لتوسيط المحتوى */
    .settings-center-container {
        display: flex;
        justify-content: center; /* توسيط أفقي */
        align-items: flex-start; /* يبدأ من الأعلى مع هامش */
        min-height: 70vh; /* يعطي مساحة عمودية للشاشة */
        padding: 40px 20px;
        direction: rtl;
    }

    /* تحسين تصميم البطاقة */
    .password-card {
        background: #fff;
        width: 100%;
        max-width: 450px; /* عرض مثالي للكمبيوتر */
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        border: 1px solid #f0f0f0;
    }

    .password-card h3 {
        margin-bottom: 25px;
        color: #2d3436;
        text-align: center;
        font-size: 1.4rem;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .password-card h3 i {
        color: #4361ee;
        font-size: 2rem;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #636e72;
    }

    .form-group input {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #edf2f7;
        border-radius: 10px;
        transition: 0.3s;
    }

    .form-group input:focus {
        border-color: #4361ee;
        outline: none;
        box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    }

    .form-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 30px;
    }

    .btn-save {
        background: #4361ee;
        color: white;
        border: none;
        padding: 14px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.3s;
    }

    .btn-save:hover { background: #3730a3; transform: translateY(-2px); }

    .cancel-btn {
        text-align: center;
        text-decoration: none;
        color: #b2bec3;
        font-size: 0.9rem;
    }

    .cancel-btn:hover { color: #636e72; }
</style>

<div class="settings-center-container">
    <div class="password-card">
        <h3>
            <i class="fas fa-user-shield"></i>
            تأمين حساب المشرف
        </h3>
        
        <form id="change-pass" method="post" action="api_handler.php?action=change_password">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label><i class="fas fa-key"></i> كلمة المرور الحالية</label>
                <input type="password" name="old_pass" placeholder="••••••••" required>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-lock"></i> كلمة المرور الجديدة</label>
                <input type="password" name="new_pass" placeholder="أدخل كلمة مرور قوية" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">تحديث كلمة المرور</button>
                <a class="cancel-btn" href="index.php">إلغاء والعودة للرئيسية</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('change-pass').onsubmit = async function(e){
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('.btn-save');
    const originalText = btn.innerText;
    
    btn.innerText = 'جاري التحديث...';
    btn.disabled = true;

    try {
        const fd = new FormData(form);
        const res = await fetch(form.action, {
            method:'POST', 
            headers:{'X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, 
            body: fd
        });
        const data = await res.json();
        
        if (data.success) {
            alert('تم تغيير كلمة المرور بنجاح');
            location.href = 'index.php';
        } else {
            alert(data.message || 'فشل التحديث');
            btn.innerText = originalText;
            btn.disabled = false;
        }
    } catch (err) {
        alert('حدث خطأ في الاتصال');
        btn.innerText = originalText;
        btn.disabled = false;
    }
};
</script>

<?php admin_end_page(); ?>