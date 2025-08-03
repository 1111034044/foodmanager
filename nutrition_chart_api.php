<?php
header('Content-Type: application/json');
require_once 'db.php';

session_start();
if (!isset($_SESSION['uId'])) {
    echo json_encode(['error' => '未登入']);
    exit;
}

$uId = $_SESSION['uId'];
$today = date('Y-m-d');

try {
    // 取得今日營養素攝取量
    $stmt = $db->prepare("
        SELECT 
            SUM(protein) as total_protein,
            SUM(fat) as total_fat,
            SUM(carb) as total_carb,
            SUM(fiber) as total_fiber
        FROM calorie_records 
        WHERE user_id = ? AND record_date = ?
    ");
    $stmt->execute([$uId, $today]);
    $today_nutrition = $stmt->fetch(PDO::FETCH_ASSOC);

    // 取得營養素目標
    $stmt = $db->prepare("
        SELECT protein_goal, fat_goal, carb_goal, fiber_goal 
        FROM user_nutrition_goal 
        WHERE user_id = ? 
        ORDER BY updated_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$uId]);
    $nutrition_goal = $stmt->fetch(PDO::FETCH_ASSOC);

    // 如果沒有目標，從營養素建議 API 取得
    if (!$nutrition_goal) {
        $stmt = $db->prepare("SELECT gender, age, height, weight FROM user_calorie_goal WHERE user_id=? ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$uId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // 計算建議營養素目標
            $gender = $user['gender'];
            $age = $user['age'];
            $height = $user['height'];
            $weight = $user['weight'];
            
            if ($gender === 'male') {
                $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
            } else {
                $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
            }
            
            $daily_calorie = $bmr * 1.375;
            $nutrition_goal = [
                'protein_goal' => round($daily_calorie * 0.2 / 4, 1),
                'fat_goal' => round($daily_calorie * 0.3 / 9, 1),
                'carb_goal' => round($daily_calorie * 0.5 / 4, 1),
                'fiber_goal' => round($daily_calorie / 1000 * 14, 1)
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'today' => [
            'protein' => floatval($today_nutrition['total_protein'] ?? 0),
            'fat' => floatval($today_nutrition['total_fat'] ?? 0),
            'carb' => floatval($today_nutrition['total_carb'] ?? 0),
            'fiber' => floatval($today_nutrition['total_fiber'] ?? 0)
        ],
        'goal' => [
            'protein' => floatval($nutrition_goal['protein_goal'] ?? 0),
            'fat' => floatval($nutrition_goal['fat_goal'] ?? 0),
            'carb' => floatval($nutrition_goal['carb_goal'] ?? 0),
            'fiber' => floatval($nutrition_goal['fiber_goal'] ?? 0)
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => '查詢失敗: ' . $e->getMessage()]);
}
?> 