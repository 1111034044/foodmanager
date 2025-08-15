<?php
require_once 'config.php';
header('Content-Type: application/json');

$api_key = OPENAI_API_KEY;

$food = $_GET['food'] ?? '';
$quantity = $_GET['quantity'] ?? 1;

if (!$food) {
    echo json_encode(['error' => '請提供食物名稱']);
    exit;
}

$prompt = "請分析 {$quantity} 份 {$food} 的營養成分，請以JSON格式回覆，包含以下欄位：
- protein: 蛋白質(克)，請給出數字
- fat: 脂肪(克)，請給出數字
- carb: 碳水化合物(克)，請給出數字
- fiber: 膳食纖維(克)，請給出數字
- vitamin: 主要維生素(文字描述)
- mineral: 主要礦物質(文字描述)

請只回覆JSON格式，不要使用markdown格式，不要其他文字。範例格式：
{
  \"protein\": 10.5,
  \"fat\": 5.2,
  \"carb\": 25.8,
  \"fiber\": 3.1,
  \"vitamin\": \"維生素C、維生素B群\",
  \"mineral\": \"鉀、鈣、鐵\"
}";

try {
    $response = file_get_contents(OPENAI_API_URL, false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ],
            'content' => json_encode([
                'model' => OPENAI_MODEL,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => OPENAI_MAX_TOKENS,
                'temperature' => OPENAI_TEMPERATURE
            ])
        ]
    ]));

    $data = json_decode($response, true);
    
    if (isset($data['choices'][0]['message']['content'])) {
        $content = $data['choices'][0]['message']['content'];
        
        // 清理 markdown 格式
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);
        
        $nutrition = json_decode($content, true);
        
        if ($nutrition) {
            // 確保數值欄位是數字
            $nutrition['protein'] = floatval($nutrition['protein'] ?? 0);
            $nutrition['fat'] = floatval($nutrition['fat'] ?? 0);
            $nutrition['carb'] = floatval($nutrition['carb'] ?? 0);
            $nutrition['fiber'] = floatval($nutrition['fiber'] ?? 0);
            
            echo json_encode([
                'success' => true,
                'nutrition' => $nutrition
            ]);
        } else {
            // 除錯：顯示原始回應
            echo json_encode([
                'error' => '無法解析營養資料',
                'debug' => [
                    'raw_content' => $content,
                    'json_error' => json_last_error_msg()
                ]
            ]);
        }
    } else {
        echo json_encode(['error' => 'API回應格式錯誤', 'debug' => $data]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'API呼叫失敗: ' . $e->getMessage()]);
}
?> 