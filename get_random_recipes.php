<?php
// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => '資料庫連線失敗']);
    exit();
}

// 查詢隨機三個食譜
$query = "
    SELECT r.RecipeId, r.rName, r.cooktime, r.DifficultyLevel, r.CoverImage
    FROM Recipe r
    ORDER BY RAND()
    LIMIT 3
";

$result = $conn->query($query);

$recipes = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recipes[] = $row;
    }
}

$conn->close();

// 設置 JSON 頭部並返回數據
header('Content-Type: application/json');
echo json_encode($recipes);
?>