<?php
// test_api.php
// 测试发送验证码 API

echo "<h1>API 测试</h1>";

// 测试邮箱
$test_email = 'test@example.com';  // 改成你的测试邮箱

echo "<p>测试邮箱: <strong>$test_email</strong></p>";

$url = 'https://ragemi.com/api/send_verification';
$data = ['email' => $test_email];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>响应结果</h3>";
echo "<p>HTTP 状态码: <strong>$http_code</strong></p>";

if ($error) {
    echo "<p style='color:red;'>❌ cURL 错误: $error</p>";
}

echo "<p>响应内容:</p>";
echo "<pre style='background:#f5f5f5;padding:15px;border-radius:5px;'>" . htmlspecialchars($response) . "</pre>";

$json = json_decode($response, true);
if ($json) {
    echo "<h3>解析结果</h3>";
    echo "<pre style='background:#f5f5f5;padding:15px;border-radius:5px;'>";
    print_r($json);
    echo "</pre>";
}