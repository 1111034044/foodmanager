<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只允許 POST 請求']);
    exit;
}

// 檢查是否啟用（沿用 VALIDATION_ENABLED 作為是否允許 AI 功能的總開關）
if (!VALIDATION_ENABLED) {
    http_response_code(503);
    echo json_encode(['error' => 'AI 功能已停用']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['food']) || !isset($input['qty'])) {
    http_response_code(400);
    echo json_encode(['error' => '缺少必要欄位：food, qty']);
    exit;
}

$food = trim((string)$input['food']);
$qty = trim((string)$input['qty']);

if ($food === '' || $qty === '') {
    http_response_code(400);
    echo json_encode(['error' => 'food 與 qty 不可為空']);
    exit;
}

$prompt = "請告訴我 {$qty} 份 {$food} 的熱量（kcal），請計算到小數點第一位。只回覆數字，不要加單位。";

$request_data = [
    'model' => OPENAI_MODEL,
    'messages' => [
        [
            'role' => 'user',
            'content' => $prompt
        ]
    ],
    'max_tokens' => 20,
    'temperature' => OPENAI_TEMPERATURE
];

try {
    $response = file_get_contents(OPENAI_API_URL, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENAI_API_KEY
            ],
            'content' => json_encode($request_data)
        ]
    ]));

    if ($response === false) {
        throw new Exception('無法連接到 OpenAI API');
    }

    $data = json_decode($response, true);
    if (!$data || !isset($data['choices'][0]['message']['content'])) {
        throw new Exception('API 回應格式錯誤');
    }

    $content = $data['choices'][0]['message']['content'];
    $kcal = preg_replace('/[^\d\.]/', '', (string)$content);

    if ($kcal === '') {
        throw new Exception('無法從回應中解析熱量');
    }

    echo json_encode([
        'success' => true,
        'kcal' => $kcal,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'OpenAI API 請求失敗: ' . $e->getMessage()]);
}
?>


