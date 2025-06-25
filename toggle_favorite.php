<?php
session_start();

// 檢查登入與 POST 參數
if (!isset($_SESSION['user_id']) || !isset($_POST['RecipeId'])) {
    header("Location: recipe.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "foodmanager");
if ($conn->connect_error) {
    die("資料庫連線失敗：" . $conn->connect_error);
}

$userId = (int)$_SESSION['user_id'];
$recipeId = (int)$_POST['RecipeId'];

// 檢查是否已經收藏
$stmt = $conn->prepare("SELECT 1 FROM recipe_favorites WHERE UserId = ? AND RecipeId = ?");
if ($stmt) {
    $stmt->bind_param("ii", $userId, $recipeId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // 已收藏，執行取消收藏
        $stmt->close();
        $delete = $conn->prepare("DELETE FROM recipe_favorites WHERE UserId = ? AND RecipeId = ?");
        if ($delete) {
            $delete->bind_param("ii", $userId, $recipeId);
            $delete->execute();
            $delete->close();
        }
    } else {
        // 尚未收藏，新增收藏紀錄
        $stmt->close();
        $insert = $conn->prepare("INSERT INTO recipe_favorites (UserId, RecipeId) VALUES (?, ?)");
        if ($insert) {
            $insert->bind_param("ii", $userId, $recipeId);
            $insert->execute();
            $insert->close();
        }
    }
} else {
    error_log("查詢失敗：" . $conn->error);
}

$conn->close();

// 取得回跳網址，預設為 recipe.php
$return_url = isset($_POST['return_url']) ? $_POST['return_url'] : 'recipe.php';

// 防止跳轉到外部網址（簡單安全檢查）
if (strpos($return_url, '/') !== 0) {
    $return_url = 'recipe.php';
}

header("Location: $return_url");
exit();
?>
