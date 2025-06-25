<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    die("請先登入後再執行此操作。");
}

if (!isset($_POST['ReviewId'], $_POST['RecipeId'], $_POST['Comment'], $_POST['Rating'])) {
    die("缺少必要的表單資料。");
}

$reviewId = (int) $_POST['ReviewId'];
$recipeId = (int) $_POST['RecipeId'];
$comment = trim($_POST['Comment']);
$rating = max(1, min(5, (int) $_POST['Rating'])); // 限制在 1~5 星之間
$userId = (int) $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "foodmanager");
if ($conn->connect_error) {
    die("資料庫連線失敗：" . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT 1 FROM reviews WHERE ReviewId = ? AND UserId = ?");
$stmt->bind_param("ii", $reviewId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("你無權編輯這則評論。");
}

$stmt = $conn->prepare("UPDATE reviews SET Comment = ?, Rating = ? WHERE ReviewId = ?");
$stmt->bind_param("sii", $comment, $rating, $reviewId);

if ($stmt->execute()) {
    header("Location: recipe_detail.php?RecipeId=" . $recipeId);
    exit();
} else {
    echo "評論更新失敗：" . $stmt->error;
}

$stmt->close();
$conn->close();
?>
