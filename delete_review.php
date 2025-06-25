<?php
session_start();

// 確保用戶已登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 確保已經提供了 ReviewId
if (!isset($_POST['ReviewId'])) {
    echo "無效的評論資料。";
    exit;
}

$ReviewId = (int)$_POST['ReviewId'];

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 查詢該評論是否屬於當前用戶
$stmt = $conn->prepare("SELECT UserId FROM reviews WHERE ReviewId = ?");
$stmt->bind_param("i", $ReviewId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    echo "評論不存在。";
    exit;
}

$stmt->bind_result($UserId);
$stmt->fetch();

// 如果該評論不是當前用戶發表的，則拒絕刪除
if ($UserId != $_SESSION['user_id']) {
    echo "您無權刪除此評論。";
    exit;
}

// 刪除該評論
$stmt = $conn->prepare("DELETE FROM reviews WHERE ReviewId = ?");
$stmt->bind_param("i", $ReviewId);

if ($stmt->execute()) {
    // 刪除成功後，重定向回該食譜的詳細頁面
    header("Location: recipe_detail.php?RecipeId=" . (int)$_POST['RecipeId']);
    exit;
} else {
    echo "刪除評論時出現錯誤，請稍後再試。";
}

$stmt->close();
$conn->close();
?>