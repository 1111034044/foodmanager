<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['uId'])) exit('no user');
$uId = $_SESSION['uId'];
$goal = intval($_POST['calorie_goal'] ?? 0);
$gender = $_POST['gender'] ?? null;
$age = $_POST['age'] ?? null;
$height = $_POST['height'] ?? null;
$weight = $_POST['weight'] ?? null;

if (!$goal) exit('no goal');

// 儲存熱量目標
$stmt = $db->prepare("INSERT INTO user_calorie_goal (user_id, calorie_goal, gender, age, height, weight, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
$stmt->execute([$uId, $goal, $gender, $age, $height, $weight]);

// 處理營養素目標
$protein_goal = floatval($_POST['protein_goal'] ?? 0);
$fat_goal = floatval($_POST['fat_goal'] ?? 0);
$carb_goal = floatval($_POST['carb_goal'] ?? 0);
$fiber_goal = floatval($_POST['fiber_goal'] ?? 0);

// 如果使用者沒有手動設定，則使用計算的建議值
if ($protein_goal == 0 && $fat_goal == 0 && $carb_goal == 0 && $fiber_goal == 0) {
    if ($gender && $age && $height && $weight) {
        if ($gender === 'male') {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
        } else {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
        }
        
        $daily_calorie = $bmr * 1.375;
        $protein_goal = round($daily_calorie * 0.2 / 4, 1);
        $fat_goal = round($daily_calorie * 0.3 / 9, 1);
        $carb_goal = round($daily_calorie * 0.5 / 4, 1);
        $fiber_goal = round($daily_calorie / 1000 * 14, 1);
    }
}

// 儲存營養素目標 - 先檢查是否存在記錄
$stmt = $db->prepare("SELECT id FROM user_nutrition_goal WHERE user_id = ? LIMIT 1");
$stmt->execute([$uId]);
$existing = $stmt->fetch();

if ($existing) {
    // 更新現有記錄
    $stmt = $db->prepare("
        UPDATE user_nutrition_goal 
        SET protein_goal = ?, fat_goal = ?, carb_goal = ?, fiber_goal = ?, updated_at = CURRENT_TIMESTAMP
        WHERE user_id = ?
    ");
    $stmt->execute([$protein_goal, $fat_goal, $carb_goal, $fiber_goal, $uId]);
} else {
    // 新增記錄
    $stmt = $db->prepare("
        INSERT INTO user_nutrition_goal (user_id, protein_goal, fat_goal, carb_goal, fiber_goal) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$uId, $protein_goal, $fat_goal, $carb_goal, $fiber_goal]);
}

echo 'ok'; 