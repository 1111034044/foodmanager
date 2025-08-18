<?php
// 關閉錯誤顯示，防止 PHP 錯誤破壞 JSON 輸出
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'db.php';

// 設置響應頭為 JSON
header('Content-Type: application/json');

try {
    // 檢查用戶是否已登入
    if (!isset($_SESSION['uId'])) {
        echo json_encode(['success' => false, 'message' => '未登入']);
        exit;
    }

    // 從 session 中獲取 uId
    $uId = $_SESSION['uId'];
    $role = $_SESSION['role'] ?? '未指定';

    // 獲取 POST 數據
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => '無效的請求數據']);
        exit;
    }
    
    $mealName = $data['meal_name'] ?? '未指定餐點';

    // 建立資料庫連線
    $conn = new mysqli("localhost", "root", "", "foodmanager");

    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => '資料庫連線失敗']);
        exit;
    }

    // 獲取今天的日期
    $today = date('Y-m-d');

    // 準備 SQL 查詢
    $ingredientsToConsume = [];

    // 如果指定了特定食材
    if (isset($data['ingredients']) && is_array($data['ingredients']) && !empty($data['ingredients'])) {
        $ingredientIds = $data['ingredients'];
        $placeholders = str_repeat('?,', count($ingredientIds) - 1) . '?';
        
        $sql = "SELECT IngredientId, IName, Quantity, Unit FROM Ingredient 
                WHERE uId = ? AND IngredientId IN ($placeholders) AND Quantity > 0";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => '準備查詢失敗: ' . $conn->error]);
            exit;
        }
        
        $types = str_repeat('i', count($ingredientIds) + 1);
        $params = array_merge([$types, $uId], $ingredientIds);
        
        call_user_func_array([$stmt, 'bind_param'], $params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $ingredientsToConsume[] = $row;
        }
        
        $stmt->close();
    } 
    // 如果選擇「全部」食材
    elseif (isset($data['ingredients']) && $data['ingredients'] === 'all') {
        // 查詢即將過期和已過期的食材
        $expiryThreshold = date('Y-m-d', strtotime('+7 days'));
        $sql = "SELECT IngredientId, IName, Quantity, Unit 
                FROM Ingredient 
                WHERE uId = ? AND ExpireDate IS NOT NULL 
                AND ((ExpireDate BETWEEN ? AND ?) OR (ExpireDate < ?)) 
                AND Quantity > 0";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => '準備查詢失敗: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("isss", $uId, $today, $expiryThreshold, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $ingredientsToConsume[] = $row;
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => '未指定要消耗的食材']);
        exit;
    }

    // 如果沒有找到食材
    if (empty($ingredientsToConsume)) {
        echo json_encode(['success' => false, 'message' => '沒有找到可消耗的食材']);
        exit;
    }

    // 消耗食材
    $consumedCount = 0;
    foreach ($ingredientsToConsume as $ingredient) {
        $ingredientId = $ingredient['IngredientId'];
        $usedQuantity = $ingredient['Quantity']; // 使用全部數量
        $unit = $ingredient['Unit'] ?: '';
        
        // 記錄食材使用
        $stmt = $conn->prepare("INSERT INTO IngredientUsage (IngredientId, uId, UsedQuantity, Unit, UsageDate, Note, Role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            continue; // 跳過這個食材，繼續處理其他食材
        }
        
        $note = "製作 {$mealName} 時消耗";
        $stmt->bind_param("iiissss", $ingredientId, $uId, $usedQuantity, $unit, $today, $note, $role);
        $stmt->execute();
        
        // 更新食材數量為0（表示已全部使用）
        $stmt = $conn->prepare("UPDATE Ingredient SET Quantity = 0 WHERE IngredientId = ? AND uId = ?");
        if (!$stmt) {
            continue; // 跳過這個食材，繼續處理其他食材
        }
        
        $stmt->bind_param("ii", $ingredientId, $uId);
        $stmt->execute();
        
        $consumedCount++;
    }

    // 返回結果
    echo json_encode([
        'success' => true, 
        'consumed_count' => $consumedCount,
        'message' => "已成功消耗 {$consumedCount} 個食材"
    ]);
} catch (Exception $e) {
    // 捕獲所有異常，確保返回有效的 JSON
    echo json_encode([
        'success' => false,
        'message' => '發生錯誤: ' . $e->getMessage()
    ]);
}
?>