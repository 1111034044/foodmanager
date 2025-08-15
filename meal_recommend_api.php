<?php
session_start();
require_once 'db.php';
require_once 'config.php';

if (!isset($_SESSION['uId'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$uId = $_SESSION['uId'];
$mealType = $_GET['meal_type'] ?? '';
$isRetry = isset($_GET['retry']) && $_GET['retry'] === '1';
$today = date('Y-m-d');

if (!$mealType) {
    http_response_code(400);
    exit('Missing meal type');
}

// 取得今日已攝取的營養素
$stmt = $db->prepare("
    SELECT 
        SUM(calorie) as total_calorie,
        SUM(protein) as total_protein,
        SUM(fat) as total_fat,
        SUM(carb) as total_carb,
        SUM(fiber) as total_fiber
    FROM calorie_records 
    WHERE user_id = ? AND record_date = ?
");
$stmt->execute([$uId, $today]);
$todayNutrition = $stmt->fetch(PDO::FETCH_ASSOC);

// 取得使用者營養素目標
$stmt = $db->prepare("
    SELECT protein_goal, fat_goal, carb_goal, fiber_goal 
    FROM user_nutrition_goal 
    WHERE user_id = ?
");
$stmt->execute([$uId]);
$nutritionGoal = $stmt->fetch(PDO::FETCH_ASSOC);

// 如果沒有設定目標，使用預設值
if (!$nutritionGoal) {
    $nutritionGoal = [
        'protein_goal' => 60,
        'fat_goal' => 65,
        'carb_goal' => 250,
        'fiber_goal' => 25
    ];
}

// 計算營養素缺口
$proteinDeficit = max(0, $nutritionGoal['protein_goal'] - ($todayNutrition['total_protein'] ?? 0));
$fatDeficit = max(0, $nutritionGoal['fat_goal'] - ($todayNutrition['total_fat'] ?? 0));
$carbDeficit = max(0, $nutritionGoal['carb_goal'] - ($todayNutrition['total_carb'] ?? 0));
$fiberDeficit = max(0, $nutritionGoal['fiber_goal'] - ($todayNutrition['total_fiber'] ?? 0));

// 準備給 OpenAI 的資料
$mealTypeText = [
    'breakfast' => '早餐',
    'lunch' => '午餐', 
    'dinner' => '晚餐',
    'snack' => '點心'
][$mealType] ?? $mealType;

// 根據是否為重試來調整 prompt
if ($isRetry) {
    $prompt = "請根據以下資訊推薦一個「完全不同風格」的{$mealTypeText}：

今日已攝取營養素：
- 熱量：{$todayNutrition['total_calorie']} kcal
- 蛋白質：{$todayNutrition['total_protein']}g (目標：{$nutritionGoal['protein_goal']}g)
- 脂肪：{$todayNutrition['total_fat']}g (目標：{$nutritionGoal['fat_goal']}g)
- 碳水化合物：{$todayNutrition['total_carb']}g (目標：{$nutritionGoal['carb_goal']}g)
- 膳食纖維：{$todayNutrition['total_fiber']}g (目標：{$nutritionGoal['fiber_goal']}g)

營養素缺口：
- 蛋白質：{$proteinDeficit}g
- 脂肪：{$fatDeficit}g
- 碳水化合物：{$carbDeficit}g
- 膳食纖維：{$fiberDeficit}g

請提供以下格式的 JSON 回應（不要包含 markdown 格式）：
{
  \"meal_name\": \"餐點名稱\",
  \"description\": \"餐點簡短描述\",
  \"calorie\": 數字,
  \"protein\": 數字,
  \"fat\": 數字,
  \"carb\": 數字,
  \"fiber\": 數字,
  \"recipe\": \"簡單的食譜或搭配建議\",
  \"reason\": \"推薦理由，說明為什麼推薦這個餐點\"
}

重要要求：
1. 所有營養素數值請用數字，不要加單位
2. 餐點要符合{$mealTypeText}的特性
3. 優先補充缺口較大的營養素
4. 食譜要簡單易懂
5. 推薦理由要具體說明營養需求
6. 「必須」與之前的推薦完全不同風格（例如：如果之前推薦中式，這次推薦西式；如果之前推薦熱食，這次推薦冷食；如果之前推薦葷食，這次推薦素食等）
7. 嘗試不同的烹調方式（蒸、煮、炒、烤、涼拌等）
8. 使用不同的主要食材組合";
} else {
    $prompt = "請根據以下資訊推薦一個適合的{$mealTypeText}：

今日已攝取營養素：
- 熱量：{$todayNutrition['total_calorie']} kcal
- 蛋白質：{$todayNutrition['total_protein']}g (目標：{$nutritionGoal['protein_goal']}g)
- 脂肪：{$todayNutrition['total_fat']}g (目標：{$nutritionGoal['fat_goal']}g)
- 碳水化合物：{$todayNutrition['total_carb']}g (目標：{$nutritionGoal['carb_goal']}g)
- 膳食纖維：{$todayNutrition['total_fiber']}g (目標：{$nutritionGoal['fiber_goal']}g)

營養素缺口：
- 蛋白質：{$proteinDeficit}g
- 脂肪：{$fatDeficit}g
- 碳水化合物：{$carbDeficit}g
- 膳食纖維：{$fiberDeficit}g

請提供以下格式的 JSON 回應（不要包含 markdown 格式）：
{
  \"meal_name\": \"餐點名稱\",
  \"description\": \"餐點簡短描述\",
  \"calorie\": 數字,
  \"fat\": 數字,
  \"carb\": 數字,
  \"fiber\": 數字,
  \"recipe\": \"簡單的食譜或搭配建議\",
  \"reason\": \"推薦理由，說明為什麼推薦這個餐點\"
}

注意：
1. 所有營養素數值請用數字，不要加單位
2. 餐點要符合{$mealTypeText}的特性
3. 優先補充缺口較大的營養素
4. 食譜要簡單易懂
5. 推薦理由要具體說明營養需求";
}

// 呼叫 OpenAI API
$apiKey = OPENAI_API_KEY;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, OPENAI_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => OPENAI_MODEL,
    'messages' => [
        ['role' => 'user', 'content' => $prompt]
    ],
    'max_tokens' => OPENAI_MAX_TOKENS,
    'temperature' => $isRetry ? 0.9 : 0.7
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(500);
    exit('API request failed');
}

$data = json_decode($response, true);
$content = $data['choices'][0]['message']['content'] ?? '';

// 移除可能的 markdown 格式
$content = preg_replace('/```json\s*|\s*```/', '', $content);

$result = json_decode($content, true);

if (!$result) {
    http_response_code(500);
    exit('Failed to parse API response');
}

// 回傳結果
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'meal' => $result
]);
?> 