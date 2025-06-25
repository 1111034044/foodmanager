<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $recipeId = (int) $_POST['RecipeId'];

    $conn = new mysqli("localhost", "root", "", "foodmanager");
    if ($conn->connect_error) {
        die("連線失敗: " . $conn->connect_error);
    }

    // 安全處理欄位
    $rName = $conn->real_escape_string($_POST['rName']);
    $description = $conn->real_escape_string($_POST['Description']);
    $cooktime = (int) $_POST['cooktime'];
    $difficulty = $conn->real_escape_string($_POST['DifficultyLevel']);

    // 處理封面圖片上傳
    if ($_FILES['CoverImage']['error'] === UPLOAD_ERR_OK) {
        $coverTmpName = $_FILES['CoverImage']['tmp_name'];
        $coverName = basename($_FILES['CoverImage']['name']);
        $coverPath = "uploads/" . time() . "_" . $coverName;
        move_uploaded_file($coverTmpName, $coverPath);
        
        $stmt = $conn->prepare("UPDATE Recipe SET rName=?, Description=?, cooktime=?, DifficultyLevel=?, CoverImage=? WHERE RecipeId=?");
        $stmt->bind_param("ssissi", $rName, $description, $cooktime, $difficulty, $coverPath, $recipeId);
    } else {
        $stmt = $conn->prepare("UPDATE Recipe SET rName=?, Description=?, cooktime=?, DifficultyLevel=? WHERE RecipeId=?");
        $stmt->bind_param("ssisi", $rName, $description, $cooktime, $difficulty, $recipeId);
    }
    $stmt->execute();

    // 更新標籤
    if (isset($_POST['Tags'])) {
        // 處理標籤資料
        $tags = $_POST['Tags'];  // 假設標籤是由逗號分隔的
        $tagsArray = explode(',', $tags); // 轉換為數組
        $tagsArray = array_map('trim', $tagsArray); // 去除多餘的空格
        $tagsJson = json_encode($tagsArray); // 編碼為 JSON 字串，方便儲存

        // 刪除舊標籤
        $stmt = $conn->prepare("DELETE FROM recipetags WHERE RecipeId = ?");
        $stmt->bind_param("i", $recipeId);
        $stmt->execute();

        // 插入新的標籤
        foreach ($tagsArray as $tag) {
            $stmt = $conn->prepare("INSERT INTO recipetags (RecipeId, Tag) VALUES (?, ?)");
            $stmt->bind_param("is", $recipeId, $tag);
            $stmt->execute();
        }
    }

    // 更新食材
    $conn->query("DELETE FROM RecipeIngredient WHERE RecipeId = $recipeId");
    if (!empty($_POST['IngredientName'])) {
        foreach ($_POST['IngredientName'] as $i => $name) {
            $quantity = $_POST['Quantity'][$i];
            $unit = $_POST['Unit'][$i];

            $stmt = $conn->prepare("INSERT INTO RecipeIngredient (RecipeId, IngredientName, Quantity, Unit) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $recipeId, $name, $quantity, $unit);
            $stmt->execute();
        }
    }

    // 更新步驟
    $conn->query("DELETE FROM RecipeSteps WHERE RecipeId = $recipeId");

    if (!empty($_POST['StepDescription'])) {
        foreach ($_POST['StepDescription'] as $i => $desc) {
            $desc = $conn->real_escape_string($desc);
            $stepOrder = (int) $_POST['StepOrder'][$i];
            $existingImage = $_POST['ExistingStepImage'][$i];

            // 新圖片處理
            $stepImageName = $existingImage;
            if (isset($_FILES['StepImage']['name'][$i]) && $_FILES['StepImage']['error'][$i] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['StepImage']['tmp_name'][$i];
                $filename = basename($_FILES['StepImage']['name'][$i]);
                $path = "uploads/step_" . time() . "_" . $filename;
                move_uploaded_file($tmp, $path);
                $stepImageName = $path;
            }

            $stmt = $conn->prepare("INSERT INTO RecipeSteps (RecipeId, StepOrder, StepDescription, StepImage) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $recipeId, $stepOrder, $desc, $stepImageName);
            $stmt->execute();
        }
    }

    // 修改完成後跳轉
    header("Location: recipe_detail.php?RecipeId=" . $recipeId);
    exit();
} else {
    echo "無效的請求方式。";
}
?>
