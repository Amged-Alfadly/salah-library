<?php
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}
require_once '../api/db_config.php';

$users = $pdo->query("SELECT id, username, email, role_id, created_at FROM users ORDER BY created_at DESC")->fetchAll();

require_once __DIR__ . '/layout.php';
admin_start_page('إدارة المستخدمين', 'users');
?>

    <table class="admin-table">
        <thead><tr><th>#</th><th>اسم المستخدم</th><th>البريد</th><th>الصلاحية</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= $u['role_id'] == 1 ? 'مدير' : 'مستخدم' ?></td>
                <td>
                    <button onclick="changeRole(<?= $u['id'] ?>)">تبديل الصلاحية</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <script>
    async function changeRole(id){
        const res = await fetch('api_handler.php?action=toggle_user_role', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':'<?= $_SESSION['csrf_token'] ?>'}, body:JSON.stringify({id})});
        const data = await res.json(); alert(data.message); if(data.success) location.reload();
    }
    </script>

<?php admin_end_page(); ?>
