<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只允許 POST 請求']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['message']) || empty($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => '請提供訊息內容']);
    exit;
}

$message = $input['message'];
$api_key = getenv('OPENAI_API_KEY');

// **正確的 OpenAI API 端點**
$url = 'https://api.openai.com/v1/chat/completions';

// **正確的資料格式**
$data = [
    'model' => 'gpt-4.1', // 或 'gpt-4'，依你帳號權限
    'messages' => [
        [
            'role' => 'user',
            'content' => $message
        ]
    ],
    'max_tokens' => 1000,
    'temperature' => 0.7
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => '網路錯誤: ' . $error]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'API 請求失敗，狀態碼: ' . $httpCode]);
    exit;
}

$responseData = json_decode($response, true);

if (!$responseData) {
    http_response_code(500);
    echo json_encode(['error' => '無法解析 API 回應']);
    exit;
}

if (isset($responseData['error'])) {
    http_response_code(400);
    echo json_encode(['error' => 'API 錯誤: ' . $responseData['error']['message']]);
    exit;
}

// **正確提取回應內容**
if (isset($responseData['choices'][0]['message']['content'])) {
    $aiResponse = $responseData['choices'][0]['message']['content'];
    echo json_encode([
        'success' => true,
        'response' => $aiResponse,
        'usage' => $responseData['usage'] ?? null
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => '無法獲取 AI 回應']);
}
?>