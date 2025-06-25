<?php
session_start();
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 確保使用者有登入
if (!isset($_SESSION['user_id'])) {
    die("未授權的操作");
}

// 確保收到食譜 ID
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['RecipeId'])) {
    $recipeId = intval($_POST['RecipeId']);
    $userId = $_SESSION['user_id'];

    // 確認這道食譜是該使用者上傳的
    $checkQuery = "SELECT uId FROM Recipe WHERE RecipeId = ? AND uId = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ii", $recipeId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
    // 使用者擁有這道食譜，可以刪除

    // 啟動資料庫交易
    $conn->begin_transaction();

    try {
        // 刪除評論
        $stmt = $conn->prepare("DELETE FROM reviews WHERE RecipeId = ?");
        $stmt->bind_param("i", $recipeId);
        $stmt->execute();

        // 刪除步驟
        $stmt = $conn->prepare("DELETE FROM RecipeSteps WHERE RecipeId = ?");
        $stmt->bind_param("i", $recipeId);
        $stmt->execute();

        // 刪除食材
        $stmt = $conn->prepare("DELETE FROM RecipeIngredient WHERE RecipeId = ?");
        $stmt->bind_param("i", $recipeId);
        $stmt->execute();

        // 刪除食譜本身
        $stmt = $conn->prepare("DELETE FROM Recipe WHERE RecipeId = ?");
        $stmt->bind_param("i", $recipeId);
        $stmt->execute();

        // 提交交易
        $conn->commit();

        echo "<script>alert('刪除成功！'); window.location.href='my_recipes.php';</script>";
    } catch (Exception $e) {
        // 發生錯誤，回滾所有操作
        $conn->rollback();
        echo "<script>alert('刪除失敗，請稍後再試。'); window.location.href='my_recipes.php';</script>";
    }
} else {
    echo "<script>alert('無權刪除此食譜。'); window.location.href='my_recipes.php';</script>";
}

    $stmt->close();
}

$conn->close();
?>
