<?php
// 1. بدء الجلسة للتمكن من الوصول إليها
session_start();

// 2. إزالة جميع متغيرات الجلسة
$_SESSION = array();

// 3. تدمير الجلسة تماماً
session_destroy();

// 4. التوجيه إلى الصفحة الرئيسية للموقع
header("Location: ../index.php");
exit();
