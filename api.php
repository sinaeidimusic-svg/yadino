<?php
/* ============================================================
   api.php — پروکسی کوچک یادینو برای سرویس 1xAi (1xai.ir)
   کنار index.html در هاست آپلود کن. فقط درخواست POST را به یک
   آدرس ثابت می‌فرستد؛ پروکسی باز نیست.
   ============================================================ */
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ok' => true, 'service' => '1xai', 'message' => 'api.php سالم است — درخواست‌های POST به 1xai.ir فرستاده می‌شوند.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'Only POST is allowed.']]);
    exit;
}

$body = file_get_contents('php://input');
if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Empty body.']]);
    exit;
}

/* کلید: اگر مرورگر هدر Authorization فرستاد همان، وگرنه کلید داخل خود فایل */
$auth = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) $auth = $_SERVER['HTTP_AUTHORIZATION'];
elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
elseif (function_exists('getallheaders')) {
    foreach (getallheaders() as $h => $v) {
        if (strtolower($h) === 'authorization') { $auth = $v; break; }
    }
}
if (!$auth) {
    $auth = 'Bearer 1xai-1w7wHkNhEGoLH1pv1dCP77Fn0CrZG5Cw1K6XMHU_SKg';
}

if (function_exists('curl_init')) {
$ch = curl_init('https://1xai.ir/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: ' . $auth,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 120,
]);
$res  = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

} else {
    /* هاست‌هایی که curl را غیرفعال کرده‌اند */
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAuthorization: " . $auth . "\r\n",
        'content' => $body,
        'timeout' => 120,
        'ignore_errors' => true
    ]]);
    $res = @file_get_contents('https://1xai.ir/v1/chat/completions', false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $mm)) $code = (int)$mm[1];
}
if ($res === false || !$code) {
    http_response_code(502);
    echo json_encode(['error' => ['message' => 'Gateway unreachable' . (isset($err) && $err ? (': ' . $err) : '')]]);
    exit;
}
http_response_code($code);
echo $res;
