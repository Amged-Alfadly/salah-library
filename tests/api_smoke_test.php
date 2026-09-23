<?php
// اختبار بسيط للـ API (تأكد من تشغيل الخادم محلياً قبل التشغيل)

function call_api($action, $data = [], $baseUrl = "http://localhost/salah-library") {
    $url = rtrim($baseUrl, '/') . "/admin/api_handler.php?action=" . urlencode($action);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['error'=>$err];
    return json_decode($res, true);
}

// مثال: جلب الإحصائيات
$res = call_api('get_stats');
print_r($res);
