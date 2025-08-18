<?php
session_start();
require_once 'db.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$uId = $_SESSION['user_id'];
$isRetry = isset($_GET['retry']) && $_GET['retry'] === '1';
$today = date('Y-m-d');

// 取得即將過期和已過期的食材
$conn = new mysqli("localhost", "root", "", "foodmanager");
if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed');
}

// 查詢即將過期的食材（7天內）
$expiryThreshold = date('Y-m-d', strtotime('+7 days'));

// 檢查是否有指定的食材 ID
$selectedIngredients = isset($_GET['ingredients']) ? $_GET['ingredients'] : [];

if (!empty($selectedIngredients)) {
    // 如果有選定的食材，只查詢這些食材
    $placeholders = str_repeat('?,', count($selectedIngredients) - 1) . '?';
    $sql = "SELECT IngredientId, IName, Quantity, Unit, ExpireDate, StoreType 
            FROM Ingredient 
            WHERE uId = ? AND IngredientId IN ($placeholders) AND Quantity > 0 
            ORDER BY ExpireDate ASC";
    
    $stmt = $conn->prepare($sql);
    $types = 'i' . str_repeat('i', count($selectedIngredients));
    $params = array_merge([$uId], $selectedIngredients);
    
    $stmt->bind_param($types, ...$params);
} else {
    // 否則查詢所有即將過期和已過期的食材
    $sql = "SELECT IngredientId, IName, Quantity, Unit, ExpireDate, StoreType 
            FROM Ingredient 
            WHERE uId = ? AND ExpireDate IS NOT NULL 
            AND ((ExpireDate BETWEEN ? AND ?) OR (ExpireDate < ?)) 
            AND Quantity > 0 
            ORDER BY ExpireDate ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $uId, $today, $expiryThreshold, $today);
}

$stmt->execute();
$result = $stmt->get_result();

$expiringIngredients = [];
$expiredIngredients = [];

while ($row = $result->fetch_assoc()) {
    // 區分已過期和即將過期
    if (strtotime($row['ExpireDate']) < strtotime($today)) {
        $expiredIngredients[] = $row;
    } else {
        $expiringIngredients[] = $row;
    }
}

$stmt->close();

// 如果沒有即將過期或已過期的食材
if (empty($expiringIngredients) && empty($expiredIngredients)) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'message' => '沒有即將過期或已過期的食材'
    ]);
    exit;
}

// 準備食材列表
$ingredientsList = [];
foreach ($expiringIngredients as $ingredient) {
    $ingredientsList[] = $ingredient['IName'] . ' (' . $ingredient['Quantity'] . ' ' . ($ingredient['Unit'] ?: '個') . ')';
}
foreach ($expiredIngredients as $ingredient) {
    $ingredientsList[] = $ingredient['IName'] . ' (' . $ingredient['Quantity'] . ' ' . ($ingredient['Unit'] ?: '個') . ')';
}

// 取得今日已攝取的營養素
$stmt = $conn->prepare("
    SELECT 
        SUM(calorie) as total_calorie,
        SUM(protein) as total_protein,
        SUM(fat) as total_fat,
        SUM(carb) as total_carb,
        SUM(fiber) as total_fiber
    FROM calorie_records 
    WHERE user_id = ? AND record_date = ?
");
$stmt->bind_param("is", $uId, $today);
$stmt->execute();
$todayNutrition = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 取得使用者營養素目標
$stmt = $conn->prepare("
    SELECT protein_goal, fat_goal, carb_goal, fiber_goal 
    FROM user_nutrition_goal 
    WHERE user_id = ?
");
$stmt->bind_param("i", $uId);
$stmt->execute();
$nutritionGoal = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
// 根據是否為重試來調整 prompt
if ($isRetry) {
    $prompt = "請根據以下即將過期和已過期的食材，推薦一個「完全不同風格」的餐點：\n\n";
} else {
    $prompt = "請根據以下即將過期和已過期的食材，推薦一個適合的餐點：\n\n";
}

// 添加食材列表
$prompt .= "即將過期的食材：\n";
if (!empty($expiringIngredients)) {
    foreach ($expiringIngredients as $ingredient) {
        $expireDate = new DateTime($ingredient['ExpireDate']);
        $today = new DateTime();
        $interval = $today->diff($expireDate);
        $daysLeft = $interval->days;
        $prompt .= "- {$ingredient['IName']} ({$ingredient['Quantity']} {$ingredient['Unit']}), 還有 {$daysLeft} 天過期\n";
    }
} else {
    $prompt .= "- 無\n";
}

$prompt .= "\n已過期的食材：\n";
if (!empty($expiredIngredients)) {
    foreach ($expiredIngredients as $ingredient) {
        $expireDate = new DateTime($ingredient['ExpireDate']);
        $today = new DateTime();
        $interval = $today->diff($expireDate);
        $daysExpired = $interval->days;
        $prompt .= "- {$ingredient['IName']} ({$ingredient['Quantity']} {$ingredient['Unit']}), 已過期 {$daysExpired} 天\n";
    }
} else {
    $prompt .= "- 無\n";
}

$prompt .= "\n今日已攝取營養素：\n";
$prompt .= "- 熱量：{$todayNutrition['total_calorie']} kcal\n";
$prompt .= "- 蛋白質：{$todayNutrition['total_protein']}g (目標：{$nutritionGoal['protein_goal']}g)\n";
$prompt .= "- 脂肪：{$todayNutrition['total_fat']}g (目標：{$nutritionGoal['fat_goal']}g)\n";
$prompt .= "- 碳水化合物：{$todayNutrition['total_carb']}g (目標：{$nutritionGoal['carb_goal']}g)\n";
$prompt .= "- 膳食纖維：{$todayNutrition['total_fiber']}g (目標：{$nutritionGoal['fiber_goal']}g)\n\n";

$prompt .= "營養素缺口：\n";
$prompt .= "- 蛋白質：{$proteinDeficit}g\n";
$prompt .= "- 脂肪：{$fatDeficit}g\n";
$prompt .= "- 碳水化合物：{$carbDeficit}g\n";
$prompt .= "- 膳食纖維：{$fiberDeficit}g\n\n";

// 修改 prompt 以要求明確列出餐點需要的食材
$prompt .= "請提供以下格式的 JSON 回應（不要包含 markdown 格式）：\n";
$prompt .= "{\n";
$prompt .= "  \"meal_name\": \"餐點名稱\",\n";
$prompt .= "  \"description\": \"餐點簡短描述\",\n";
$prompt .= "  \"calorie\": 數字,\n";
$prompt .= "  \"protein\": 數字,\n";
$prompt .= "  \"fat\": 數字,\n";
$prompt .= "  \"carb\": 數字,\n";
$prompt .= "  \"fiber\": 數字,\n";
$prompt .= "  \"ingredients_needed\": [\"食材1（用量）\", \"食材2（用量）\", ...],\n";
$prompt .= "  \"recipe\": \"簡單的食譜或搭配建議\",\n";
$prompt .= "  \"reason\": \"推薦理由，說明為什麼推薦這個餐點，以及如何利用即將過期的食材\"\n";
$prompt .= "}\n\n";

$prompt .= "注意：\n";
$prompt .= "1. 所有營養素數值請用數字，不要加單位\n";
$prompt .= "2. 優先使用即將過期的食材，然後是已過期但仍可食用的食材\n";
$prompt .= "3. 優先補充缺口較大的營養素\n";
$prompt .= "4. 食譜要簡單易懂\n";
$prompt .= "5. 推薦理由要具體說明如何利用即將過期的食材\n";
$prompt .= "6. ingredients_needed 必須明確列出製作這道餐點所需的所有食材及其大約用量\n";

if ($isRetry) {
    $prompt .= "6. 「必須」與之前的推薦完全不同風格（例如：如果之前推薦中式，這次推薦西式；如果之前推薦熱食，這次推薦冷食；如果之前推薦葷食，這次推薦素食等）\n";
    $prompt .= "7. 嘗試不同的烹調方式（蒸、煮、炒、烤、涼拌等）\n";
    $prompt .= "8. 使用不同的主要食材組合";
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

// 準備最終響應數據
$responseData = [
    'success' => true,
    'meal' => $result,
    'expiring_ingredients' => $expiringIngredients,
    'expired_ingredients' => $expiredIngredients
];

// 如果成功生成推薦餐點，添加自動消耗食材的功能
if (isset($_GET['consume']) && $_GET['consume'] === '1') {
    // 獲取當前日期
    $today = date('Y-m-d');
    $role = $_SESSION['user_role'] ?? '未指定';
    
    // 遍歷所有過期和即將過期的食材
    $ingredientsToConsume = array_merge($expiredIngredients, $expiringIngredients);
    $consumedCount = 0;
    
    foreach ($ingredientsToConsume as $ingredient) {
        $ingredientId = $ingredient['IngredientId'];
        $usedQuantity = $ingredient['Quantity']; // 使用全部數量
        $unit = $ingredient['Unit'] ?: '';
        
        // 記錄食材使用
        $stmt = $conn->prepare("INSERT INTO IngredientUsage (IngredientId, uId, UsedQuantity, Unit, UsageDate, Note, Role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $note = "從過期食材推薦餐點自動消耗";
        $stmt->bind_param("iiissss", $ingredientId, $uId, $usedQuantity, $unit, $today, $note, $role);
        $stmt->execute();
        
        // 更新食材數量為0（表示已全部使用）
        $stmt = $conn->prepare("UPDATE Ingredient SET Quantity = 0 WHERE IngredientId = ? AND uId = ?");
        $stmt->bind_param("ii", $ingredientId, $uId);
        $stmt->execute();
        
        $consumedCount++;
    }
    
    // 在響應中添加消耗信息
    $responseData['consumed'] = true;
    $responseData['consumed_count'] = $consumedCount;
}

header('Content-Type: application/json');
echo json_encode($responseData);
?>