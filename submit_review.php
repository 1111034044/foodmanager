<?php
session_start();

// 確保用戶已登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 獲取並處理表單提交的資料
$RecipeId = isset($_POST['RecipeId']) ? (int)$_POST['RecipeId'] : 0;
$Rating = isset($_POST['Rating']) ? (int)$_POST['Rating'] : 0;
$Comment = isset($_POST['Comment']) ? trim($_POST['Comment']) : '';

// 確保所有資料都有填寫
if ($RecipeId && $Rating && $Comment) {
    // 建立資料庫連線
    $conn = new mysqli("localhost", "root", "", "foodmanager");

    if ($conn->connect_error) {
        die("連線失敗: " . $conn->connect_error);
    }

    // 插入評論到資料庫
    $stmt = $conn->prepare("INSERT INTO reviews (RecipeId, UserId, Rating, Comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $RecipeId, $_SESSION['user_id'], $Rating, $Comment);

    if ($stmt->execute()) {
        // 插入成功後，重定向回該食譜的詳細頁面
        header("Location: recipe_detail.php?RecipeId=$RecipeId");
        exit;
    } else {
        // 若提交失敗，顯示錯誤訊息
        echo "提交評論時出現錯誤，請稍後再試。";
    }

    $stmt->close();
    $conn->close();
} else {
    echo "無效的評論資料。";
}
?>