<?php
// =================================================================
// ملف الوكيل (Proxy) - يستخدم cURL لإحضار البث في بيئة Codespaces
// =================================================================

if (!isset($_GET['url'])) {
    http_response_code(400); 
    die("Error: Missing 'url' parameter.");
}

$url = $_GET['url'];

// تهيئة cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
// محاكاة متصفح لمنع الحظر
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'); 
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Origin: ']); // إزالة Origin

// استلام الرؤوس (Headers) وإرسالها مباشرة للزائر
$headers = [];
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header_line) use (&$headers) {
    $len = strlen($header_line);
    $header_line = trim($header_line);
    if (!empty($header_line) && strpos($header_line, 'HTTP') !== 0) {
        // إرسال الرؤوس الأساسية فقط لتجنب مشاكل CORS
        if (!preg_match('/^(access-control|x-frame|content-security|set-cookie)/i', $header_line)) {
             header($header_line, false);
        }
    }
    return $len;
});

$content = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
$curl_errno = curl_errno($ch);

curl_close($ch);

if ($content === false || $curl_errno !== 0 || $http_code >= 400) {
    // تشخيص بسيط للخطأ
    http_response_code(500);
    die("Proxy Error in Codespaces: " . ($curl_error ? $curl_error : "HTTP Status: $http_code"));
}

// إرسال المحتوى
http_response_code($http_code); 
echo $content;

?>
