<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_POST['RecipeId'])) {
    header("Location: recipe.php");
    exit();
}

$userId = $_SESSION['user_id'];
$recipeId = (int) $_POST['RecipeId'];

$conn = new mysqli("localhost", "root", "", "foodmanager");
if ($conn->connect_error) {
    die("資料庫連線失敗：" . $conn->connect_error);
}

// 檢查是否已經點過愛心
$stmt = $conn->prepare("SELECT 1 FROM recipe_likes WHERE RecipeId = ? AND UserId = ?");
$stmt->bind_param("ii", $recipeId, $userId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // 已經按過，取消愛心
    $stmt->close();
    $stmt = $conn->prepare("DELETE FROM recipe_likes WHERE RecipeId = ? AND UserId = ?");
    $stmt->bind_param("ii", $recipeId, $userId);
    $stmt->execute();
} else {
    // 尚未按，新增愛心
    $stmt->close();
    $stmt = $conn->prepare("INSERT INTO recipe_likes (RecipeId, UserId) VALUES (?, ?)");
    $stmt->bind_param("ii", $recipeId, $userId);
    $stmt->execute();
}

$stmt->close();
$conn->close();

// 取得回跳網址，若無則回列表頁
$return_url = isset($_POST['return_url']) ? $_POST['return_url'] : 'recipe.php';

// 防止跳轉到外部網址（簡易安全處理）
if (strpos($return_url, '/') !== 0) {
    $return_url = 'recipe.php';
}

header("Location: $return_url");
exit();
?>
