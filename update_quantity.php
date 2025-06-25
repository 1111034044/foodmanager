<?php
session_start();

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

// 檢查連線是否成功
if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = $_POST['itemId'] ?? null;
    $quantity = $_POST['quantity'] ?? null;

    if (!is_numeric($itemId) || !is_numeric($quantity)) {
        echo "參數錯誤";
        exit;
    }

    // 使用預備語句更新資料
    $stmt = $conn->prepare("UPDATE shoppingitem SET Quantity = ? WHERE ItemId = ?");
    $stmt->bind_param("ii", $quantity, $itemId);

    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "資料庫更新失敗: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "非法請求";
}

$conn->close();
?>
