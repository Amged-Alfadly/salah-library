<?php
// 1. ضبط إعدادات الكوكي للجلسة قبل البدء
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// 2. استدعاء ملف الاتصال الموحد
require_once '../api/db_config.php';

// التحقق من أن المتغير $pdo يعمل (تم الاتصال بنجاح)
if (!$pdo) {
    die("خطأ: لا يمكن الوصول إلى قاعدة البيانات. تأكد من إعدادات ملف db_connection.php");
}

// دوال مساعدة لتقييد محاولات الدخول
function get_client_ip() {
    foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) return $_SERVER[$key];
    }
    return '0.0.0.0';
}

$ip = get_client_ip();
$maxAttempts = 5;
$windowMinutes = 15; // فترة النافذة الزمنية

// 3. تحقق إذا تجاوزت المحاولات المسموح بها (مع ميزة الإصلاح التلقائي للجداول)
$threshold = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
try {
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE (username = ? OR ip_address = ?) AND attempt_time > ? AND success = 0");
} catch (PDOException $e) {
    // ميزة الميجريشن: إذا كان الجدول غير موجود، حاول إنشاءه من ملف SQL
    $msg = $e->getMessage();
    if (strpos($msg, '1146') !== false || strpos($msg, '42S02') !== false) {
        $sqlFile = __DIR__ . '/../sql/upgrade_admin.sql';
        if (file_exists($sqlFile)) {
            $mig = file_get_contents($sqlFile);
            if ($mig !== false) {
                $stmts = array_filter(array_map('trim', explode(';', $mig)));
                foreach ($stmts as $s) {
                    if ($s === '') continue;
                    try { $pdo->exec($s); } catch (Exception $ex) { /* تجاهل أخطاء الجمل الفردية */ }
                }
                // إعادة محاولة التجهيز بعد إنشاء الجداول
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE (username = ? OR ip_address = ?) AND attempt_time > ? AND success = 0");
            } else {
                throw $e;
            }
        } else {
            throw $e;
        }
    } else {
        throw $e;
    }
}

// 4. معالجة بيانات الدخول
if (isset($_POST['login'])) {
    $username_input = trim($_POST['username']);
    $password_input = $_POST['password'];

    // تحقق من الحد الأعلى للمحاولات
    $checkStmt->execute([$username_input, $ip, $threshold]);
    $failCount = (int)$checkStmt->fetchColumn();
    
    if ($failCount >= $maxAttempts) {
        $error = "تم حظر المحاولة مؤقتًا بعد عدة محاولات فاشلة. حاول مرة أخرى لاحقاً.";
    } else {
        if (!empty($username_input) && !empty($password_input)) {
            // التحقق من اسم المستخدم أو البريد + التأكد أنه مدير (role_id = 1)
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND role_id = 1 LIMIT 1");
            $stmt->execute([$username_input, $username_input]);
            $user_data = $stmt->fetch();

            if ($user_data && password_verify($password_input, $user_data['password'])) {
                // نجاح الدخول - تشديد الجلسة وحمايتها
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $user_data['id'];
                $_SESSION['admin_name'] = $user_data['username'];
                $_SESSION['is_admin'] = true;
                
                // أنشئ CSRF token إن لم يكن موجوداً للحماية من الهجمات
                if (empty($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }

                // تسجيل محاولة ناجحة في السجل
                try {
                    $ins = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, 1)");
                    $ins->execute([$username_input, $ip]);
                } catch (Exception $e) { /* لا تمنع الدخول إذا فشل التسجيل */ }

                header("Location: index.php");
                exit();
            } else {
                $error = "بيانات الدخول خاطئة أو لا تملك صلاحية الوصول.";
                // تسجيل محاولة فاشلة لغرض الحماية
                try {
                    $ins = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, 0)");
                    $ins->execute([$username_input, $ip]);
                } catch (Exception $e) { /* تجاهل أخطاء السجل */ }
            }
        } else {
            $error = "يرجى تعبئة كافة الحقول المطلوبة.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة الإدارة | مكتبة صلاح</title>
    <!-- Font Awesome للأيقونات -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- ملف التنسيق -->
    <style>
                /* استيراد خط Tajawal من Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap');

        :root {
            --primary-color: #4361ee;
            --primary-hover: #3730a3;
            --bg-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            --card-bg: #ffffff;
            --text-main: #2d3436;
            --text-muted: #636e72;
            --error-bg: #fff5f5;
            --error-text: #e03131;
            --shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Tajawal', sans-serif;
        }

        body.login-body {
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            perspective: 1000px;
        }

        .login-card {
            background: var(--card-bg);
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: transform 0.3s ease;
        }

        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .brand-logo {
            width: 70px;
            height: 70px;
            background-image: url("../assets/images/logo.png");
            background-size: cover;
            background-repeat: no-repeat;
            color: white;
            border-radius: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 30px;
            box-shadow: 0 8px 16px rgba(67, 97, 238, 0.3);
            transform: rotate(-10deg);
        }

        .login-header h1 {
            font-size: 24px;
            color: var(--text-main);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 500;
        }

        .form-group label i {
            margin-left: 5px;
            color: var(--primary-color);
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #edf2f7;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #fdfdfd;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-login:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
        }

        .btn-login i {
            font-size: 14px;
            transition: transform 0.3s ease;
        }

        .btn-login:hover i {
            transform: translateX(-5px); /* سهم لليسار لأن الاتجاه RTL */
        }

        .error-alert {
            background: var(--error-bg);
            color: var(--error-text);
            padding: 12px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(224, 49, 49, 0.2);
            animation: shake 0.5s ease-in-out;
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f1f3f5;
        }

        .login-footer p {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        /* حركة الاهتزاز عند الخطأ */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
        }

        /* استجابة الشاشات الصغيرة */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>

<body class="login-body">

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div  class="brand-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 >لوحة التحكم</h1>
                <p>سجل دخولك لإدارة المكتبة</p>
            </div>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> اسم المستخدم / البريد</label>
                    <input type="text" name="username" placeholder="أدخل اسم المستخدم..." required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> كلمة المرور</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" name="login" class="btn-login">
                    <span>تسجيل الدخول</span>
                    <i class="fas fa-arrow-left"></i>
                </button>

                <?php if (isset($error)): ?>
                    <div class="error-alert">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
            </form>

            <div class="login-footer">
                <p>مكتبة صلاح &copy; 2026</p>
                <a href="../index.php"><i class="fas fa-home"></i> العودة للموقع الرئيسي</a>
            </div>
        </div>
    </div>

</body>

</html>