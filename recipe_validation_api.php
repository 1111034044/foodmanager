<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 檢查是否為 POST 請求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只允許 POST 請求']);
    exit;
}

// 讀取 POST 資料
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => '無效的 JSON 資料']);
    exit;
}

// 驗證必要欄位
$required_fields = ['recipe_name', 'cook_time', 'difficulty', 'ingredients', 'steps'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "缺少必要欄位: {$field}"]);
        exit;
    }
}

// 檢查是否啟用驗證
if (!VALIDATION_ENABLED) {
    http_response_code(503);
    echo json_encode(['error' => 'AI 驗證功能已停用']);
    exit;
}

// OpenAI API 設定
$openai_api_key = OPENAI_API_KEY;
$openai_api_url = OPENAI_API_URL;

// 準備驗證提示詞
$validation_prompt = "請分析以下食譜的合理性，並提供專業建議，請以JSON格式回覆，包含以下欄位：

食譜名稱：{$input['recipe_name']}
烹煮時間：{$input['cook_time']} 分鐘
難度等級：{$input['difficulty']}
食材清單：{$input['ingredients']}
製作步驟：{$input['steps']}

請只回覆JSON格式，不要使用markdown格式，不要其他文字。範例格式：
{
  \"score\": 8.5,
  \"issues\": [\"烹煮時間過短\", \"步驟描述不夠詳細\"],
  \"suggestions\": [\"建議增加烹煮時間至20分鐘\", \"詳細描述每個步驟的關鍵點\"],
  \"cook_time_suggestion\": \"建議烹煮時間：20-25分鐘\",
  \"difficulty_suggestion\": \"根據步驟複雜度，建議難度：中等\",
  \"ingredient_suggestions\": [\"牛肉和洋蔥搭配合理\", \"建議加入適量調味料\"],
  \"step_suggestions\": [\"步驟順序合理\", \"建議加入火候控制說明\"]
}";

// 準備 OpenAI API 請求
$request_data = [
    'model' => OPENAI_MODEL,
    'messages' => [
        [
            'role' => 'user',
            'content' => $validation_prompt
        ]
    ],
    'max_tokens' => OPENAI_MAX_TOKENS,
    'temperature' => OPENAI_TEMPERATURE
];

// 發送 API 請求
try {
    $response = file_get_contents($openai_api_url, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $openai_api_key
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
    
    $ai_content = $data['choices'][0]['message']['content'];
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'OpenAI API 請求失敗: ' . $e->getMessage()]);
    exit;
}

// 清理 markdown 格式
$ai_content = preg_replace('/```json\s*/', '', $ai_content);
$ai_content = preg_replace('/```\s*$/', '', $ai_content);
$ai_content = trim($ai_content);

// 嘗試解析 AI 的 JSON 回應
$ai_analysis = json_decode($ai_content, true);

if (!$ai_analysis) {
    // 如果 AI 沒有回傳 JSON，建立一個結構化的回應
    $ai_analysis = [
        'score' => 7,
        'issues' => ['AI 回應格式異常，無法解析驗證結果'],
        'suggestions' => ['請檢查食譜資料的完整性'],
        'cook_time_suggestion' => '建議參考類似食譜的烹煮時間',
        'difficulty_suggestion' => '根據步驟複雜度調整難度',
        'ingredient_suggestions' => ['檢查食材搭配的合理性'],
        'step_suggestions' => ['優化步驟順序和描述']
    ];
    
    // 除錯：顯示原始回應
    error_log('AI 回應無法解析: ' . $ai_content);
}

// 回傳驗證結果
echo json_encode([
    'success' => true,
    'validation_result' => $ai_analysis,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
