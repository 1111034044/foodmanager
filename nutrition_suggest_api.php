<?php
header('Content-Type: application/json');
require_once 'db.php';

session_start();
if (!isset($_SESSION['uId'])) {
    echo json_encode(['error' => '未登入']);
    exit;
}

$uId = $_SESSION['uId'];

// 從前端接收參數，如果沒有則從資料庫讀取
$gender = $_GET['gender'] ?? '';
$age = $_GET['age'] ?? '';
$height = $_GET['height'] ?? '';
$weight = $_GET['weight'] ?? '';

// 如果前端沒有傳參數，則從資料庫讀取
if (!$gender || !$age || !$height || !$weight) {
    $stmt = $db->prepare("SELECT gender, age, height, weight FROM user_calorie_goal WHERE user_id=? ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute([$uId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => '請先設定基本資料']);
        exit;
    }
    
    $gender = $user['gender'];
    $age = $user['age'];
    $height = $user['height'];
    $weight = $user['weight'];
}

// 計算基礎代謝率 (BMR) - 使用 Mifflin-St Jeor 公式
if ($gender === 'male') {
    $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
} else {
    $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
}

// 假設輕度活動，每日總熱量需求
$daily_calorie = $bmr * 1.375;

// 計算三大營養素建議攝取量
$protein_ratio = 0.2; // 蛋白質 20%
$fat_ratio = 0.3;     // 脂肪 30%
$carb_ratio = 0.5;    // 碳水化合物 50%

$suggested_protein = round($daily_calorie * $protein_ratio / 4, 1); // 1g蛋白質=4kcal
$suggested_fat = round($daily_calorie * $fat_ratio / 9, 1);         // 1g脂肪=9kcal
$suggested_carb = round($daily_calorie * $carb_ratio / 4, 1);       // 1g碳水=4kcal
$suggested_fiber = round($daily_calorie / 1000 * 14, 1);            // 每1000kcal約14g纖維

echo json_encode([
    'success' => true,
    'daily_calorie' => round($daily_calorie),
    'suggestions' => [
        'protein' => $suggested_protein,
        'fat' => $suggested_fat,
        'carb' => $suggested_carb,
        'fiber' => $suggested_fiber
    ],
    'user_info' => [
        'gender' => $gender,
        'age' => $age,
        'height' => $height,
        'weight' => $weight
    ]
]);
?> 