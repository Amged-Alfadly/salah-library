<?php
include 'api/db_config.php';
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مكتبة صلاح | وجهتك للأناقة </title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    
    /* حل مشكلة الفوتر في منتصف الصفحة */
        html, body {
            height: 100%; /* ضمان أن الصفحة تأخذ كامل طول الشاشة */
        }

        body {
            display: flex;
            flex-direction: column; /* ترتيب العناصر عمودياً */
            min-height: 100vh; /* الطول الأدنى هو طول شاشة العرض */
        }

        main {
            flex: 1; /* هذا السطر يخبر قسم المحتوى بأن يمتد ليملأ الفراغ ويدفع الفوتر للأسفل */
        }

        /* تنسيق إضافي لرسالة "لا توجد منتجات" لتظهر بشكل جميل */
        .no-products {
            text-align: center;
            padding: 100px 20px;
            color: #888;
        }
        /* 1. الإعدادات العامة للموقع */
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #fcfcfc;
            transition: background 0.3s, color 0.3s;
            margin: 0;
            padding: 0;
        }

        /* 2. زر الإضافة الأخضر (+) داخل كروت المنتجات */
        .add-btn {
            background-color: #28a745 !important;
            /* اللون الأخضر */
            color: white !important;
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            border: none !important;
            cursor: pointer;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 0 !important;
            /* إخفاء نص "إضافة" نهائياً */
            overflow: hidden !important;
            transition: transform 0.2s, background-color 0.2s;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
            padding: 0 !important;
            position: relative;
        }

        /* إظهار علامة الزائد فقط */
        .add-btn::before {
            content: '+';
            font-size: 24px !important;
            font-weight: bold;
            line-height: 1;
            display: block !important;
        }

        .add-btn:hover {
            transform: scale(1.1);
            background-color: #218838 !important;
        }

        /* 3. زر السلة العائم (الواتساب) - المظهر المحدث */
        .mobile-order-btn {
            position: fixed;
            bottom: 25px;
            left: 20px;
            background: #25d366 !important;
            color: white !important;
            padding: 10px 18px !important;
            border-radius: 50px !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2) !important;
            z-index: 2000;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            text-decoration: none !important;
            border: none !important;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 140px;
            height: 55px;
            direction: rtl;
        }

        /* تنظيم محتوى زر السلة العائم */
        .mobile-order-btn span {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            font-size: 12px;
            line-height: 1.2;
            font-weight: bold;
        }

        /* السعر داخل الزر */
        #cart-total-price {
            font-size: 15px;
            font-weight: 800;
        }

        .mobile-order-btn i {
            font-size: 22px;
        }

        .mobile-order-btn:hover {
            transform: scale(1.05) translateY(-5px);
            background: #1eb954 !important;
        }

        /* 4. الوضع الليلي الشامل (Dark Mode) */
        body.dark-mode {
            background-color: #1a1a1a;
            color: #eee;
        }

        body.dark-mode .main-header {
            background: #222;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            border-bottom: 1px solid #333;
        }

        body.dark-mode .cart-drawer,
        body.dark-mode .drawer-header,
        body.dark-mode .drawer-content,
        body.dark-mode .drawer-footer {
            background: #222 !important;
            color: white !important;
            border-color: #444 !important;
        }

        body.dark-mode .category-item {
            color: #ccc;
            background: #333;
        }

        /* 5. شريط تقدم الشحن المجاني */
        .shipping-promo {
            padding: 15px;
            background: #e0f7fa;
            border-radius: 12px;
            margin: 10px;
            font-size: 0.9rem;
        }

        body.dark-mode .shipping-promo {
            background: #00262b;
            color: #00bcd4;
        }

        .progress-container {
            background: #ccc;
            height: 8px;
            border-radius: 10px;
            margin-top: 8px;
            overflow: hidden;
        }

        .progress-bar {
            background: #00bcd4;
            height: 100%;
            width: 0%;
            transition: width 0.5s ease;
        }

        /* 6. تنبيهات الإضافة (Toast) */
        #toast-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
        }

        .toast {
            background: #333;
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeInOut 3s ease forwards;
        }

        @keyframes fadeInOut {
            0% {
                opacity: 0;
                transform: translateY(-20px);
            }

            10% {
                opacity: 1;
                transform: translateY(0);
            }

            90% {
                opacity: 1;
            }

            100% {
                opacity: 0;
                transform: translateY(-20px);
            }
        }

        /* 7. أيقونات الهيدر */
        .theme-toggle,
        .cart-icon,
        .user-icon {
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            transition: 0.3s;
        }

        .theme-toggle:hover {
            background: rgba(0, 0, 0, 0.05);
        }

        body.dark-mode .theme-toggle {
            color: #ffeb3b;
        }

        #cart-count {
            position: absolute;
            top: -8px;
            right: -10px;
            background: #00bcd4;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: bold;
            border: 2px solid white;
        }
    </style>
</head>

<body>
    <!-- حاوية التنبيهات -->
    <div id="toast-container"></div>

    <!-- السلة الجانبية (Drawer) -->
    <div id="cart-drawer" class="cart-drawer">
        <div class="drawer-header">
            <h3><i class="fas fa-shopping-basket"></i> سلة المشتريات</h3>
            <button class="close-drawer" onclick="toggleCartDrawer(false)">×</button>
        </div>

        <!-- شريط الشحن المجاني التفاعلي -->
        <div class="shipping-promo" id="shipping-promo">
            <span id="shipping-msg">أضف منتجات للحصول على شحن مجاني!</span>
            <div class="progress-container">
                <div class="progress-bar" id="shipping-progress"></div>
            </div>
        </div>

        <div id="cart-items-list" class="drawer-content">
            <!-- المنتجات تظهر هنا -->
        </div>
        <div class="drawer-footer">
            <div class="total-price-row">
                <span>الإجمالي:</span>
                <div><span id="drawer-total">0</span> ريال </div>
            </div>
            <button class="send-wa-btn" onclick="openCart()">
                <i class="fab fa-whatsapp"></i> إرسال الطلب عبر واتساب
            </button>
        </div>
    </div>

    <div id="drawer-overlay" onclick="toggleCartDrawer(false)"></div>

    <header class="main-header">
        <div class="container">
            <div class="top-nav">
                <a href="index.php" class="logo">
                    <span class="icon"></span> مكتبة صلاح
                    <img style="border-radius:20px;width: 40px; height: 40px;" src="assets/images/logo.png" alt="logo">
                </a>

                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="main-search" placeholder="ابحث عن منتجك ...">
                </div>

                <div class="icons">
                    <!-- زر الوضع الليلي المحسن -->
                    <div class="theme-toggle" id="dark-mode-toggle" title="تبديل الوضع">
                        <i class="fas fa-moon" id="dark-mode-icon"></i>
                    </div>

                    <a href="admin/index.php" class="user-icon" title="لوحة التحكم">
                        <i class="far fa-user-circle"></i>
                    </a>

                    <div class="cart-icon" id="open-cart" onclick="toggleCartDrawer(true)" title="عرض السلة">
                        <i class="fas fa-shopping-basket"></i>
                        <span id="cart-count">0</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <nav class="categories-nav">
            <ul id="categories-list">
                <li class="category-item active" data-id="all" onclick="filterByCategory('all', 'وصل حديثاً', event)">الكل</li>
                <?php foreach ($categories as $cat): ?>
                    <li class="category-item" data-id="<?php echo $cat['id']; ?>"
                        onclick="filterByCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>', event)">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="section-header">
            <h1 id="category-title">وصل حديثاً</h1>
            <div class="view-options">
                <i class="fas fa-th-large active" id="grid-view"></i>
                <i class="fas fa-list" id="list-view"></i>
            </div>
        </div>

        <div id="products-grid" class="products-grid">
            <!-- الكروت يتم رسمها بواسطة app.js -->
        </div>

        <div id="sentinel" style="height: 20px; margin-bottom: 50px;"></div>
    </main>

    <button id="whatsapp-float-btn" class="mobile-order-btn" onclick="toggleCartDrawer(true)" style="display: none;">
        <i class="fab fa-whatsapp"></i>
        <span><span id="cart-total-price">0</span> ريال</span>
    </button>

    <div id="modal-root"></div>

    <footer style="background-color: #00bcd4;" class="main-footer">
        <div class="container">
            <p><a style="text-decoration: none; color:white;" href="http://aliqasem7716.alwaysdata.net/">© 2026 جميع الحقوق محفوظة لشركة YP Soft</a></p>
        </div>
    </footer>

    <!-- تحميل الملف الأساسي كموديول -->
    <script type="module" src="js/app.js"></script>

    <script>
        const darkModeToggle = document.getElementById('dark-mode-toggle');
        const darkModeIcon = document.getElementById('dark-mode-icon');

        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');

            // تغيير الأيقونة
            darkModeIcon.classList.toggle('fa-sun', isDark);
            darkModeIcon.classList.toggle('fa-moon', !isDark);

            // حفظ التفضيل
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });

        // استعادة الوضع عند التحميل
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
            darkModeIcon.classList.add('fa-sun');
            darkModeIcon.classList.remove('fa-moon');
        }
    </script>
</body>

</html>