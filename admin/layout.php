<?php
if (!function_exists('admin_start_page')) {
    function admin_start_page($title = 'لوحة الإدارة', $active = 'dashboard'){
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        ?>
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo htmlspecialchars($title); ?></title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="admin-style.css">
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <style>
                /* التعديل لضمان جعل كامل الصندوق قابلاً للضغط */
                .sidebar nav ul li {
                    padding: 0 !important; /* إزالة الحشو من القائمة */
                    margin: 5px 0;
                }
                .sidebar nav ul li a {
                    display: flex !important; /* جعل الرابط مرناً */
                    align-items: center;
                    padding: 12px 20px; /* الحشو الآن داخل الرابط وليس الصندوق */
                    color: inherit;
                    text-decoration: none;
                    width: 100%; /* جعل الرابط يملأ كامل العرض */
                    transition: 0.3s;
                    border-radius: 8px;
                }
                .sidebar nav ul li.active a, 
                .sidebar nav ul li a:hover {
                    background: rgba(67, 97, 238, 0.1); /* تأثير عند الضغط أو التحويم */
                    color: #ffffff;
                }
                .sidebar nav ul li a i {
                    margin-left: 12px; /* مسافة بين الأيقونة والنص */
                    font-size: 1.1rem;
                }
            </style>
        </head>
        <body>
        <div class="admin-container">
            <aside class="sidebar">
                <div class="logo">لوحة المدير</div>
                <nav>
                    <ul>
                        <li class="nav-item <?php echo $active==='dashboard'?'active':'';?>">
                            <a href="index.php"><i class="fas fa-home"></i> <span>الإحصائيات</span></a>
                        </li>
                        <li class="nav-item <?php echo $active==='products'?'active':'';?>">
                            <a href="products.php"><i class="fas fa-box"></i> <span>المنتجات</span></a>
                        </li>
                        <li class="nav-item <?php echo $active==='categories'?'active':'';?>">
                            <a href="categories.php"><i class="fas fa-tags"></i> <span>التصنيفات</span></a>
                        </li>
                        <li class="nav-item <?php echo $active==='users'?'active':'';?>">
                            <a href="users.php"><i class="fas fa-users"></i> <span>المستخدمين</span></a>
                        </li>
                        <li class="nav-item <?php echo $active==='settings'?'active':'';?>">
                            <a href="change_password.php"><i class="fas fa-user-shield"></i> <span>الأمان والحساب</span></a>
                        </li>
                        <li class="logout">
                            <a href="logout.php"><i class="fas fa-power-off"></i> <span>خروج</span></a>
                        </li>
                    </ul>
                </nav>
            </aside>

            <main class="main-content">
                <div class="admin-header">
                    <div>
                        <h2><?php echo htmlspecialchars($title); ?></h2>
                    </div>
                    <div class="header-actions">
                        <span>المستخدم: <?php echo htmlspecialchars($_SESSION['admin_name'] ?? ''); ?></span>
                    </div>
                </div>
        <?php
    }

    function admin_end_page($extraJs = ''){
        ?>
                </main>
            </div>

            <script>
            function switchTab(tabId, el){
                document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                const target = document.getElementById(tabId);
                if(target) target.classList.add('active');
                if(el) el.classList.add('active');
            }
            </script>
            <?php if ($extraJs) echo $extraJs; ?>
        </body>
        </html>
        <?php
    }
}
?>