<?php
session_start(); // 啟動 session

// 檢查是否提供了 RecipeId
if (!isset($_GET['RecipeId'])) {
    header("Location: recipe.php");
    exit();
}

$recipeId = (int) $_GET['RecipeId'];


// 初始化 session 儲存替代選擇（如果尚未初始化）
if (!isset($_SESSION['ingredient_alternatives'])) {
    $_SESSION['ingredient_alternatives'] = [];
}

// 處理用戶的替代選擇
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['ingredient_name'])) {
    $ingredientName = $_POST['ingredient_name'];
    $action = $_POST['action'];

    if ($action === 'accept' || $action === 'reject') {
        $_SESSION['ingredient_alternatives'][$recipeId][$ingredientName] = $action;
    }
    // 重新導向以避免表單重複提交
    header("Location: recipe_detail.php?RecipeId=$recipeId");
    exit();
}

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}
// 增加瀏覽數
$stmtView = $conn->prepare("UPDATE recipe SET ViewCount = ViewCount + 1 WHERE RecipeId = ?");
$stmtView->bind_param("i", $recipeId);
$stmtView->execute();
$stmtView->close();
// 查詢指定食譜的詳細資料（表名改為小寫 recipe）
$stmt = $conn->prepare("
    SELECT r.RecipeId, r.rName, r.cooktime, r.DifficultyLevel, r.Description, r.uId, r.UploadDate, r.CoverImage, r.ViewCount, u.uName, u.uImage
    FROM recipe r
    JOIN user u ON r.uId = u.uId
    WHERE r.RecipeId = ?
");

if ($stmt === false) {
    die("SQL 語句準備失敗: " . $conn->error);
}

$stmt->bind_param("i", $recipeId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: recipe.php");
    exit();
}

$recipe = $result->fetch_assoc();
$stmt->close();

// 查詢該食譜的食材（表名修正為 recipeingredients）
$stmt = $conn->prepare("
    SELECT IngredientName, Quantity, Unit
    FROM recipeingredient
    WHERE RecipeId = ?
");
$stmt->bind_param("i", $recipeId);
$stmt->execute();
$ingResult = $stmt->get_result();

$ingredients = [];
while ($ingRow = $ingResult->fetch_assoc()) {
    $ingredients[] = $ingRow;
}
$stmt->close();

// 計算營養資訊（統一鍵名）
$totalNutrition = [
    'kcal' => 0,
    'protein' => 0,
    'fat' => 0,
    'carbohydrate' => 0,
];
$nutritionNotes = []; // 儲存未找到營養數據的食材

// 單位轉換假設
$unitToGrams = [
    '個' => 50,  // 1 個 ≈ 50 克（粗略估計）
    '瓶' => 500, // 1 瓶 ≈ 500 克（假設為 500 毫升，密度 1 g/ml）
    '包' => 100, // 1 包 ≈ 100 克（粗略估計）
    '公斤' => 1000, // 1 公斤 = 1000 克
    '克' => 1,      // 1 克 = 1 克
    '毫升' => 1,    // 1 毫升 ≈ 1 克（假設密度為 1 g/ml）
];

// 查詢 nutrition_facts 表中的營養數據
foreach ($ingredients as &$ingredient) {
    $ingredientName = trim($ingredient['IngredientName']);
    $quantity = (float) $ingredient['Quantity'];
    $unit = $ingredient['Unit'];

    // 嘗試模糊比對食材名稱
    $stmt = $conn->prepare("SELECT sample_name, kcal, protein, fat, carbohydrate FROM nutrition_facts");
    if ($stmt === false) {
        $nutritionNotes[] = "無法查詢營養數據表： " . $conn->error;
        continue;
    }
    $stmt->execute();
    $nutritionResult = $stmt->get_result();

    $bestMatch = null;
    $highestSimilarity = 0;
    $similarityThreshold = 70; // 相似度門檻

    while ($nutritionRow = $nutritionResult->fetch_assoc()) {
        similar_text(strtolower($ingredientName), strtolower($nutritionRow['sample_name']), $percent);
        if ($percent >= $similarityThreshold && $percent > $highestSimilarity) {
            $highestSimilarity = $percent;
            $bestMatch = $nutritionRow;
        }
    }
    $stmt->close();

    if ($bestMatch) {
        // 將數量轉換為克
        $grams = 0;
        if (!empty($unit) && isset($unitToGrams[$unit])) {
            $grams = $quantity * $unitToGrams[$unit];
        } else {
            $nutritionNotes[] = "食材「{$ingredientName}」的單位「{$unit}」無法轉換為克，營養計算可能不準確";
            $grams = $quantity; // 假設為克
        }

        // 計算營養值（假設 nutrition_facts 數據為每 100 克）
        $factor = $grams / 100;
        $totalNutrition['kcal'] += (float) ($bestMatch['kcal'] ?? 0) * $factor;
        $totalNutrition['protein'] += (float) ($bestMatch['protein'] ?? 0) * $factor;
        $totalNutrition['fat'] += (float) ($bestMatch['fat'] ?? 0) * $factor;
        $totalNutrition['carbohydrate'] += (float) ($bestMatch['carbohydrate'] ?? 0) * $factor;
    } else {
        $nutritionNotes[] = "無法找到食材「{$ingredientName}」的營養數據";
    }
}
unset($ingredient); // 解除引用

// 定義常見調味料清單（不加入購物清單）
$seasonings = array_map('strtolower', [
    '油',
    '食用油',
    '橄欖油',
    '沙拉油',
    '鹽',
    '食鹽',
    '海鹽',
    '糖',
    '白糖',
    '砂糖',
    '醬油',
    '味精',
    '胡椒',
    '黑胡椒',
    '白胡椒',
    '醋',
    '米醋',
    '香油'
]);

// 為所有食材設定 isSeasoning 鍵
foreach ($ingredients as &$ingredient) {
    $ingredientName = strtolower(trim($ingredient['IngredientName']));
    $ingredient['isSeasoning'] = in_array($ingredientName, $seasonings);
}
unset($ingredient); // 解除引用

// 定義單位轉換關係（僅用於計算缺少數量）
$unitConversions = [
    ['kg', 'g' => 1000], // 1 公斤 = 1000 克
    ['g', 'kg' => 0.001],
    ['份', 'g' => 100], // 1 份 ≈ 100 克（粗略估計）
    ['g', '份' => 0.01],
    ['個', 'g' => 50], // 1 個 ≈ 50 克（粗略估計，可依食材調整）
    ['g', '個' => 0.02]
];

// 定義重量單位和個數單位
$weightUnits = ['g', 'kg'];
$countUnits = ['個'];

// 檢查用戶已有的食材並進行模糊查詢
$userIngredients = [];
$missingIngredients = [];
$alternativeIngredients = [];
if (isset($_SESSION['user_id'])) {
    $uId = $_SESSION['user_id'];

    // 查詢用戶的食材庫存（表名改為小寫 ingredient）
    $stmt = $conn->prepare("SELECT IName, Quantity, Unit FROM ingredient WHERE uId = ? AND Quantity > 0");
    $stmt->bind_param("i", $uId);
    $stmt->execute();
    $userIngResult = $stmt->get_result();

    while ($row = $userIngResult->fetch_assoc()) {
        $userIngredients[strtolower(trim($row['IName']))] = [
            'name' => $row['IName'],
            'quantity' => $row['Quantity'],
            'unit' => $row['Unit']
        ];
    }
    $stmt->close();

    // 比對食譜所需食材與用戶庫存，並進行模糊查詢
    $similarityThreshold = 70; // 提高至 70% 以減少不合理替代
    foreach ($ingredients as &$ingredient) {
        $ingredientName = strtolower(trim($ingredient['IngredientName']));
        $requiredQuantity = (float) $ingredient['Quantity'];
        $requiredUnit = $ingredient['Unit'];

        // 檢查是否未註明單位
        if (empty($requiredUnit)) {
            $ingredient['unitNote'] = "食譜未註明食材單位";
            $ingredient['hasEnough'] = false;
            $ingredient['missing'] = round($requiredQuantity);
        }

        // 比對庫存（考慮單位轉換）
        if (isset($userIngredients[$ingredientName])) {
            $availableQuantity = $userIngredients[$ingredientName]['quantity'];
            $availableUnit = $userIngredients[$ingredientName]['unit'];

            // 檢查庫存是否未註明單位
            if (empty($availableUnit)) {
                $ingredient['unitNote'] = "您未註明食材單位";
                $ingredient['hasEnough'] = false;
                $ingredient['missing'] = round($requiredQuantity);
                continue;
            }

            // 嘗試單位轉換
            $convertedAvailable = $availableQuantity;
            $unitMismatch = ($requiredUnit !== $availableUnit);
            $conversionFound = false;
            if ($unitMismatch && !empty($requiredUnit)) {
                foreach ($unitConversions as $conversion) {
                    $fromUnit = key($conversion);
                    $toUnit = array_key_first($conversion);
                    $factor = $conversion[$toUnit];
                    if (($requiredUnit === $toUnit && $availableUnit === $fromUnit) ||
                        ($requiredUnit === $fromUnit && $availableUnit === $toUnit)
                    ) {
                        $convertedAvailable = round($availableQuantity * $factor); // 四捨五入至整數
                        $conversionFound = true;
                        break;
                    }
                }
                // 根據單位類型設置提示語
                if (in_array($requiredUnit, $weightUnits) && in_array($availableUnit, $countUnits)) {
                    $ingredient['unitNote'] = "缺少 {$ingredient['missing']} {$requiredUnit}，你有 {$availableQuantity} {$availableUnit}，但我們不確定重量";
                } elseif (in_array($requiredUnit, $countUnits) && in_array($availableUnit, $weightUnits)) {
                    $ingredient['unitNote'] = "缺少 {$ingredient['missing']} {$requiredUnit}，你有 {$availableQuantity} {$availableUnit}，但我們不確定個數";
                } else {
                    $ingredient['unitNote'] = "你有 {$availableQuantity} {$availableUnit}，但我們不確定重量或個數";
                }
            }

            if ($convertedAvailable >= $requiredQuantity) {
                $ingredient['hasEnough'] = true;
                if ($unitMismatch) {
                    if (in_array($requiredUnit, $weightUnits) && in_array($availableUnit, $countUnits)) {
                        $ingredient['unitNote'] = "你有 {$availableQuantity} {$availableUnit}，但我們不確定重量";
                    } elseif (in_array($requiredUnit, $countUnits) && in_array($availableUnit, $weightUnits)) {
                        $ingredient['unitNote'] = "你有 {$availableQuantity} {$availableUnit}，但我們不確定個數";
                    } else {
                        $ingredient['unitNote'] = "你有 {$availableQuantity} {$availableUnit}，但我們不確定重量或個數";
                    }
                }
            } else {
                $ingredient['hasEnough'] = false;
                $ingredient['missing'] = round($requiredQuantity - $convertedAvailable); // 四捨五入至整數
                if ($unitMismatch) {
                    if (in_array($requiredUnit, $weightUnits) && in_array($availableUnit, $countUnits)) {
                        $ingredient['unitNote'] = "缺少 {$ingredient['missing']} {$requiredUnit}，你有 {$availableQuantity} {$availableUnit}，但我們不確定重量";
                    } elseif (in_array($requiredUnit, $countUnits) && in_array($availableUnit, $weightUnits)) {
                        $ingredient['unitNote'] = "缺少 {$ingredient['missing']} {$requiredUnit}，你有 {$availableQuantity} {$availableUnit}，但我們不確定個數";
                    } else {
                        $ingredient['unitNote'] = "你有 {$availableQuantity} {$availableUnit}，但我們不確定重量或個數";
                    }
                }
                // 檢查轉換建議
                if ($unitMismatch && $conversionFound) {
                    $suggestedQuantity = ceil($ingredient['missing'] / $factor);
                    $ingredient['conversionSuggestion'] = "缺少 {$ingredient['missing']} {$requiredUnit}，可考慮用 {$suggestedQuantity} {$availableUnit} 替代";
                }
            }
        } else {
            $ingredient['hasEnough'] = false;
            $ingredient['missing'] = round($requiredQuantity); // 四捨五入至整數
        }

        // 只有非調味料且缺少的食材才加入購物清單（根據用戶選擇調整）
        if (!$ingredient['isSeasoning'] && !$ingredient['hasEnough']) {
            $ingredientKey = $ingredient['IngredientName'];
            $acceptedAlternative = isset($_SESSION['ingredient_alternatives'][$recipeId][$ingredientKey]) &&
                $_SESSION['ingredient_alternatives'][$recipeId][$ingredientKey] === 'accept';
            if (!$acceptedAlternative) {
                $missingIngredients[] = $ingredient;
            }
        }

        // 模糊查詢可替代食材
        if (!$ingredient['hasEnough'] && !$ingredient['isSeasoning']) {
            foreach ($userIngredients as $userIngName => $userIngData) {
                similar_text($ingredientName, $userIngName, $percent);
                if ($percent >= $similarityThreshold && $userIngData['quantity'] >= $requiredQuantity) {
                    $ingredient['alternative'] = $userIngData['name'];
                    break; // 找到一個可替代食材後停止搜索
                }
            }
        }
    }
    unset($ingredient); // 解除引用
}

// 生成購物清單（排除調味料和已接受替代的食材）
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_shopping_list'])) {
    if (!isset($_SESSION['user_id'])) {
        $error_message = "請先登入才能生成購物清單！";
    } else {
        // 使用食譜名稱作為清單名稱
        $listName = "為『" . htmlspecialchars($recipe['rName']) . "』生成的購物清單 - " . date('Y-m-d');

        // 插入 shoppinglist 表
        $stmt = $conn->prepare("INSERT INTO shoppinglist (uId, ListName, CreateDate, IsCompleted) VALUES (?, ?, CURDATE(), 0)");
        $stmt->bind_param("is", $uId, $listName);
        if ($stmt->execute()) {
            $shoppingId = $conn->insert_id;

            // 插入 shoppingitem 表（計算實際需要購買的數量）
            $stmtItem = $conn->prepare("INSERT INTO shoppingitem (ShoppingId, IngredientName, Quantity, Unit) VALUES (?, ?, ?, ?)");
            foreach ($missingIngredients as $missing) {
                $ingredientName = $missing['IngredientName'];
                $requiredQuantity = $missing['Quantity'];
                $unit = $missing['Unit'];

                // 檢查使用者現有庫存
                $actualQuantityNeeded = $requiredQuantity;
                if (isset($userIngredients[strtolower(trim($ingredientName))])) {
                    $stockQuantity = $userIngredients[strtolower(trim($ingredientName))]['quantity'];
                    // 計算實際需要購買的數量（所需數量減去庫存數量）
                    $actualQuantityNeeded = max(0, $requiredQuantity - $stockQuantity);
                }

                // 只有當實際需要購買的數量大於0時才加入購物清單
                if ($actualQuantityNeeded > 0) {
                    $stmtItem->bind_param("isds", $shoppingId, $ingredientName, $actualQuantityNeeded, $unit);
                    $stmtItem->execute();
                }
            }
            $stmtItem->close();

            // 清空該食譜的替代選擇
            unset($_SESSION['ingredient_alternatives'][$recipeId]);

            header("Location: ShoppingList.php");
            exit();
        } else {
            $error_message = "生成購物清單失敗：" . $stmt->error;
        }
        $stmt->close();
    }
}

// 查詢該食譜的步驟（表名改為小寫 recipesteps）
$stmt = $conn->prepare("
    SELECT StepOrder, StepDescription, StepImage
    FROM recipesteps
    WHERE RecipeId = ?
    ORDER BY StepOrder ASC
");
$stmt->bind_param("i", $recipeId);
$stmt->execute();
$stepResult = $stmt->get_result();

$steps = [];
while ($stepRow = $stepResult->fetch_assoc()) {
    $steps[] = $stepRow;
}
$stmt->close();

// 查詢該食譜的評論（表名改為小寫 reviews）
$stmt = $conn->prepare("
    SELECT r.ReviewId, r.Rating, r.Comment, r.CreatedAt, u.uName, u.uImage, r.UserId
    FROM reviews r
    JOIN user u ON r.UserId = u.uId
    WHERE r.RecipeId = ?
    ORDER BY r.CreatedAt DESC
");
if ($stmt === false) {
    die("準備 SQL 語句失敗: " . $conn->error);
}
$stmt->bind_param("i", $recipeId);
$stmt->execute();
$reviewResult = $stmt->get_result();

$reviews = [];
while ($reviewRow = $reviewResult->fetch_assoc()) {
    $reviews[] = $reviewRow;
}
$stmt->close();
// 查詢作者製作的食譜數量
$stmtCount = $conn->prepare("SELECT COUNT(*) AS recipe_count FROM recipe WHERE uId = ?");
$stmtCount->bind_param("i", $recipe['uId']);
$stmtCount->execute();
$resultCount = $stmtCount->get_result();
$authorRecipeCount = 0;
if ($rowCount = $resultCount->fetch_assoc()) {
    $authorRecipeCount = $rowCount['recipe_count'];
}
$stmtCount->close();
// 取得目前登入使用者 ID（可能為 null）
$uId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// 查詢該食譜的喜歡數
$stmtLikes = $conn->prepare("SELECT COUNT(*) AS like_count FROM recipe_likes WHERE RecipeId = ?");
$stmtLikes->bind_param("i", $recipeId);
$stmtLikes->execute();
$resultLikes = $stmtLikes->get_result();
$likeCount = 0;
if ($rowLikes = $resultLikes->fetch_assoc()) {
    $likeCount = $rowLikes['like_count'];
}
$stmtLikes->close();

$liked = false;
$favorited = false;

if ($uId !== null) {
    // 檢查是否被使用者喜歡
    $stmtLiked = $conn->prepare("SELECT 1 FROM recipe_likes WHERE RecipeId = ? AND UserId = ?");
    $stmtLiked->bind_param("ii", $recipeId, $uId);
    $stmtLiked->execute();
    $stmtLiked->store_result();
    $liked = $stmtLiked->num_rows > 0;
    $stmtLiked->close();

    // 檢查是否被使用者收藏
    $stmtFav = $conn->prepare("SELECT 1 FROM recipe_favorites WHERE RecipeId = ? AND UserId = ?");
    $stmtFav->bind_param("ii", $recipeId, $uId);
    $stmtFav->execute();
    $stmtFav->store_result();
    $favorited = $stmtFav->num_rows > 0;
    $stmtFav->close();
}

// 查詢該食譜的標籤（表名改為小寫 recipetags）
$stmtTags = $conn->prepare("SELECT Tag FROM recipetags WHERE RecipeId = ?");
$stmtTags->bind_param("i", $recipeId);
$stmtTags->execute();
$tagResult = $stmtTags->get_result();

$tags = [];
while ($tagRow = $tagResult->fetch_assoc()) {
    $tags[] = $tagRow['Tag']; // 取得標籤
}
$stmtTags->close();


$conn->close();

// 判斷是否為上傳者
$isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $recipe['uId'];
?>

<!DOCTYPE html>
<html lang="zh-Hant">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>食譜詳細資訊</title>
    <link rel="stylesheet" href="css/boot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

</head>

<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2>推薦食譜</h2>
        <div class="mb-3">
            <a href="recipe.php" class="btn custom-btn-light-blue">返回食譜列表</a>
        </div>
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <?php if ($recipe['CoverImage']): ?>
                        <img src="<?php echo htmlspecialchars($recipe['CoverImage']); ?>" class="card-img-top" alt="食譜封面">
                    <?php else: ?>
                        <img src="img/recipe-placeholder.jpg" class="card-img-top" alt="預設封面">
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($recipe['rName']); ?></h5>
                        <p class="card-text">
                            <?php echo nl2br(htmlspecialchars($recipe['Description'] ?? '無簡介')); ?>
                        </p>
                        <p class="card-text">
                            <strong>料理程度：</strong>
                            <?php echo htmlspecialchars($recipe['DifficultyLevel'] ?? '未指定'); ?>
                        </p>
                        <p class="card-text">
                            <strong>預計用時：</strong>
                            <?php echo $recipe['cooktime'] ? $recipe['cooktime'] . ' 分鐘' : '未指定'; ?>
                        </p>
                        <p>
                        <div class="mb-3">
                            <strong>標籤：</strong>
                            <div class="tags-list">
                                <?php if (!empty($tags)): ?>
                                    <?php
                                    // 逐一解析標籤
                                    foreach ($tags as $tagJson):
                                        // 移除標籤字串的首尾 [ 和 ] 字符
                                        $tagJson = trim($tagJson, '[]');

                                        // 嘗試解析每個標籤的 JSON 格式
                                        $decodedTags = json_decode('[' . $tagJson . ']', true); // 在標籤外圍加上方括號來解析成 JSON 陣列
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedTags)) {
                                            foreach ($decodedTags as $decodedTag) {
                                                // 確保標籤包含 'value' 屬性
                                                if (isset($decodedTag['value'])) {
                                                    // 顯示標籤的 value 屬性，並加上 '#'
                                                    echo "<span class='badge bg-secondary me-1'>#" . htmlspecialchars($decodedTag['value']) . "</span>";
                                                }
                                            }
                                        } else {
                                            // 如果 JSON 解析失敗，顯示原始標籤
                                            echo "<span class='badge bg-secondary me-1'>#" . htmlspecialchars($tagJson) . "</span>";
                                        }
                                    endforeach;
                                    ?>
                                <?php else: ?>
                                    <span class="badge bg-secondary">無標籤</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        </p>
                        <p class="card-text">
                            <strong>營養資訊（根據食材估算不一定準確）：</strong>
                            <?php if (empty($ingredients)): ?>
                                <small class="text-muted">尚未添加食材，無法計算營養資訊</small>
                            <?php else: ?>
                        <ul>
                            <li>熱量：<?php echo round($totalNutrition['kcal']); ?> 千卡</li>
                            <li>蛋白質：<?php echo round($totalNutrition['protein'], 1); ?> 克</li>
                            <li>脂肪：<?php echo round($totalNutrition['fat'], 1); ?> 克</li>
                            <li>碳水化合物：<?php echo round($totalNutrition['carbohydrate'], 1); ?> 克</li>
                        </ul>
                        <?php if (!empty($nutritionNotes)): ?>
                            <div class="alert alert-warning mt-2">
                                <strong>注意：</strong>
                                <ul>
                                    <?php foreach ($nutritionNotes as $note): ?>
                                        <li><?php echo $note; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    </p>
                    <p class="card-text">
                        <strong>所需食材：</strong>
                        <?php if (empty($ingredients)): ?>
                            <small class="text-muted">尚未添加食材</small>
                        <?php else: ?>
                    <ul>
                        <?php foreach ($ingredients as $ing): ?>
                            <li>
                                <?php echo htmlspecialchars($ing['IngredientName']); ?>：
                                <?php echo round($ing['Quantity']); ?>
                                <?php echo htmlspecialchars($ing['Unit'] ?? ''); ?>
                                <?php if ($ing['isSeasoning']): ?>
                                    <span class="text-info"><i class="bi bi-info-circle"></i> 常見調味料（不列入購物清單）</span>
                                <?php elseif (isset($ing['hasEnough'])): ?>
                                    <?php if ($ing['hasEnough']): ?>
                                        <span class="text-success"><i class="bi bi-check-circle"></i> 已擁有</span>
                                        <?php if (isset($ing['unitNote'])): ?>
                                            <span class="text-info"><i class="bi bi-info-circle"></i> <?php echo $ing['unitNote']; ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="bi bi-x-circle"></i> 缺少 <?php echo round($ing['missing']); ?> <?php echo $ing['Unit'] ?? '未知單位'; ?></span>
                                        <?php if (isset($ing['unitNote'])): ?>
                                            <span class="text-info"><i class="bi bi-info-circle"></i> <?php echo $ing['unitNote']; ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($ing['conversionSuggestion'])): ?>
                                            <span class="text-secondary"><i class="bi bi-lightbulb"></i> <?php echo $ing['conversionSuggestion']; ?></span>
                                        <?php endif; ?>
                                        <?php if (isset($ing['alternative'])): ?>
                                            <?php
                                            $ingredientKey = $ing['IngredientName'];
                                            $alternativeStatus = isset($_SESSION['ingredient_alternatives'][$recipeId][$ingredientKey])
                                                ? $_SESSION['ingredient_alternatives'][$recipeId][$ingredientKey]
                                                : null;
                                            ?>
                                            <?php if ($alternativeStatus === 'accept'): ?>
                                                <span class="text-success"><i class="bi bi-check-circle-fill"></i> 已接受替代：<?php echo htmlspecialchars($ing['alternative']); ?></span>
                                            <?php elseif ($alternativeStatus === 'reject'): ?>
                                                <span class="text-secondary"><i class="bi bi-x-circle-fill"></i> 已拒絕替代：<?php echo htmlspecialchars($ing['alternative']); ?></span>
                                            <?php else: ?>
                                                <span class="text-secondary"><i class="bi bi-arrow-right-circle"></i> 可以用 <?php echo htmlspecialchars($ing['alternative']); ?> 替代</span>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="ingredient_name" value="<?php echo htmlspecialchars($ing['IngredientName']); ?>">
                                                    <button type="submit" name="action" value="accept" class="btn btn-sm btn-success ms-2">接受</button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger ms-1">拒絕</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-question-circle"></i> 請登入以查看庫存</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p><strong>瀏覽數：</strong> <?php echo htmlspecialchars($recipe['ViewCount']); ?></p>
                    <?php if (!empty($missingIngredients)): ?>
                        <form method="POST" class="mt-3" onsubmit="return confirmDeleteIngredients();">
                            <button type="submit" name="generate_shopping_list" class="btn custom-btn-light-blue">
                                生成購物清單（缺少的食材）
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger mt-3"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                </p>
                <?php if (!empty($steps)): ?>
                    <p class="card-text">
                        <strong>步驟：</strong>
                        <?php foreach ($steps as $step): ?>
                    <div class="row mb-3">
                        <?php if (!empty($step['StepImage'])): ?>
                            <div class="col-12">
                                <h6>步驟 <?php echo $step['StepOrder']; ?></h6>
                                <p><?php echo htmlspecialchars($step['StepDescription']); ?></p>
                                <img src="<?php echo htmlspecialchars($step['StepImage']); ?>" alt="步驟圖片"
                                    class="img-fluid mt-2" style="max-height: 300px;">
                            </div>
                        <?php else: ?>
                            <div class="col-12">
                                <h6>步驟 <?php echo $step['StepOrder']; ?></h6>
                                <p><?php echo htmlspecialchars($step['StepDescription']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </p>
            <?php endif; ?>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <!-- 左側：瀏覽數-->
                        <div class="d-flex align-items-center">
                            <div class="d-flex align-items-center me-3" style="color: #555; font-size: 1rem;">
                                <i class="bi bi-eye me-1"></i>
                                <span><?php echo htmlspecialchars($recipe['ViewCount'] ?? 0); ?></span>
                            </div>
                        </div>

                        <!-- 右側：喜歡收藏按鈕 -->
                        <div class="d-flex align-items-center">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <form id="like-form-<?php echo $recipe['RecipeId']; ?>" action="toggle_like.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="RecipeId" value="<?php echo $recipe['RecipeId']; ?>">
                                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] . '#like-form-' . $recipe['RecipeId']); ?>">
                                    <button type="submit" class="btn btn-link p-0" style="font-size: 1.5rem; color: <?php echo $liked ? 'red' : 'gray'; ?>;">
                                        <?php echo $liked ? '❤️' : '🤍'; ?>
                                    </button>
                                    <span style="margin-left: 5px; color: #555;"><?php echo $likeCount; ?></span>
                                </form>
                                <form id="favorite-form-<?php echo $recipe['RecipeId']; ?>" action="toggle_favorite.php" method="POST" style="margin-left: 10px;">
                                    <input type="hidden" name="RecipeId" value="<?php echo $recipe['RecipeId']; ?>">
                                    <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] . '#favorite-form-' . $recipe['RecipeId']); ?>">
                                    <button type="submit" class="btn btn-link favorite-btn">
                                        <i class="bi <?= $favorited ? 'bi-bookmark-fill' : 'bi-bookmark'; ?>"></i>
                                    </button>
                                </form>
                            <?php endif; ?>

                        </div>


                    </div>

                </div>
                <!-- 作者資訊區塊 -->
                <style>
    .author-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .author-card:hover {
        transform: scale(1.03);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="mt-4 p-3 bg-white rounded shadow-sm d-flex align-items-center author-card"
    onclick="window.location.href='user_profile.php?uid=<?= $recipe['uId'] ?>';"
    style="cursor: pointer;"
    title="查看作者的食譜">
    <img src="<?php echo htmlspecialchars($recipe['uImage'] ?? 'img/user-placeholder.jpg'); ?>"
        class="rounded-circle me-3" alt="頭像" width="50" height="50">
    <div>
        <span class="fs-5 fw-semibold d-block"><?php echo htmlspecialchars($recipe['uName']); ?></span>
        <small class="text-muted"><?php echo $authorRecipeCount; ?> 道食譜</small>
    </div>
</div>
                <div class="mt-4">
                    <h5>食譜評論</h5>
                    <div class="list-group">
                        <?php foreach ($reviews as $review): ?>
                            <div class="list-group-item position-relative" id="review-box-<?php echo $review['ReviewId']; ?>">
                                <div class="d-flex justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($review['uImage']); ?>" class="rounded-circle me-2" width="30" height="30" alt="頭像">
                                        <strong><?php echo htmlspecialchars($review['uName']); ?></strong>
                                    </div>
                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $review['UserId']): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light" type="button" id="dropdownMenu<?php echo $review['ReviewId']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenu<?php echo $review['ReviewId']; ?>">
                                                <li><button class="dropdown-item" onclick="toggleEditMode(<?php echo $review['ReviewId']; ?>)">編輯評論</button></li>
                                                <li>
                                                    <form action="delete_review.php" method="POST" onsubmit="return confirm('確定要刪除此評論嗎？');">
                                                        <input type="hidden" name="ReviewId" value="<?php echo $review['ReviewId']; ?>">
                                                        <input type="hidden" name="RecipeId" value="<?php echo $recipeId; ?>">
                                                        <button type="submit" class="dropdown-item text-danger">刪除評論</button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div id="review-view-<?php echo $review['ReviewId']; ?>">
                                    <div>
                                        <strong>評分：</strong>
                                        <?php for ($i = 0; $i < $review['Rating']; $i++): ?>
                                            <span class="text-warning">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="mt-2 mb-1"><?php echo nl2br(htmlspecialchars($review['Comment'])); ?></p>
                                    <div class="text-muted small"><?php echo date('Y-m-d H:i', strtotime($review['CreatedAt'])); ?></div>
                                </div>
                                <div id="review-edit-<?php echo $review['ReviewId']; ?>" style="display: none;">
                                    <form action="update_review.php" method="POST" class="mt-2">
                                        <input type="hidden" name="ReviewId" value="<?php echo $review['ReviewId']; ?>">
                                        <input type="hidden" name="RecipeId" value="<?php echo $recipeId; ?>">
                                        <input type="hidden" name="Rating" id="edit-rating-<?php echo $review['ReviewId']; ?>" value="<?php echo $review['Rating']; ?>">
                                        <div class="mb-2">
                                            <strong>評分：</strong>
                                            <span class="editable-stars" data-id="<?php echo $review['ReviewId']; ?>">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?php echo $i <= $review['Rating'] ? '-fill text-warning' : ''; ?>" data-value="<?php echo $i; ?>"></i>
                                                <?php endfor; ?>
                                            </span>
                                        </div>
                                        <textarea name="Comment" class="form-control mb-2"><?php echo htmlspecialchars($review['Comment']); ?></textarea>
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleEditMode(<?php echo $review['ReviewId']; ?>)">取消</button>
                                            <button type="submit" class="btn btn-primary btn-sm">確認</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <script>
                    function toggleEditMode(reviewId) {
                        const viewBox = document.getElementById('review-view-' + reviewId);
                        const editBox = document.getElementById('review-edit-' + reviewId);
                        viewBox.style.display = viewBox.style.display === 'none' ? 'block' : 'none';
                        editBox.style.display = editBox.style.display === 'none' ? 'block' : 'none';
                    }

                    document.addEventListener("DOMContentLoaded", function() {
                        const stars = document.querySelectorAll(".editable-stars i");
                        stars.forEach(star => {
                            star.style.cursor = "pointer";
                            star.addEventListener("click", function() {
                                const rating = this.getAttribute("data-value");
                                const reviewId = this.parentElement.getAttribute("data-id");
                                document.getElementById("edit-rating-" + reviewId).value = rating;

                                const starIcons = this.parentElement.querySelectorAll("i");
                                starIcons.forEach((s, index) => {
                                    if (index < rating) {
                                        s.classList.add("bi-star-fill", "text-warning");
                                        s.classList.remove("bi-star");
                                    } else {
                                        s.classList.remove("bi-star-fill", "text-warning");
                                        s.classList.add("bi-star");
                                    }
                                });
                            });
                        });
                    });

                    function confirmDeleteIngredients() {
                        return confirm('您要不要根據現有的食材庫存計算實際需要購買的數量？系統將自動計算所需數量減去現有庫存的差額。');
                    }
                </script>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="mt-4">
                        <h5>發表評論</h5>
                        <div class="p-4 bg-white rounded shadow-sm">
                            <form action="submit_review.php" method="POST">
                                <input type="hidden" name="RecipeId" value="<?php echo $recipeId; ?>">
                                <div class="mb-3">
                                    <label for="Rating" class="form-label">評分</label>
                                    <div class="mb-3">
                                        <div class="editable-stars" data-id="new">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star" data-value="<?php echo $i; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <input type="hidden" name="Rating" id="edit-rating-new" value="5">
                                    </div>
                                    <div class="mb-3">
                                        <label for="Comment" class="form-label">評論內容</label>
                                        <textarea name="Comment" id="Comment" class="form-control comment-box" rows="4" required></textarea>
                                    </div>
                                    <button type="submit" class="btn custom-btn-light-blue">提交評論</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="mt-3">請先登入才能發表評論。</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }

        .card-img-top {
            height: 300px;
            object-fit: cover;
        }

        .card-footer {
            background-color: #f8f9fa;
        }

        .custom-btn-light-blue {
            background-color: rgb(137, 215, 241);
            border-color: #ADD8E6;
            color: rgb(85, 87, 88);
            padding: 0.375rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 1rem;
            line-height: 1.5;
            transition: background-color 0.3s ease;
        }

        .custom-btn-light-blue:hover {
            background-color: rgb(109, 199, 235);
            border-color: #87CEEB;
        }

        .card-body ul {
            padding-left: 20px;
            margin-bottom: 0;
        }

        .card-body li {
            font-size: 0.9rem;
            color: #555;
        }

        .card-body .row {
            align-items: center;
        }

        .card-body h6 {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .card-body p {
            font-size: 0.9rem;
            color: #555;
            margin-bottom: 0;
        }

        .comment-box {
            background-color: #f8f9fa;
            border: 3px solid #ced4da;
            border-radius: 8px;
            padding: 10px;
            font-size: 1rem;
            resize: vertical;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const allStars = document.querySelectorAll(".editable-stars i");
            allStars.forEach(star => {
                star.style.cursor = "pointer";
                star.addEventListener("click", function() {
                    const rating = this.getAttribute("data-value");
                    const target = this.parentElement.getAttribute("data-id");
                    document.getElementById("edit-rating-" + target).value = rating;

                    const stars = this.parentElement.querySelectorAll("i");
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.add("bi-star-fill", "text-warning");
                            s.classList.remove("bi-star");
                        } else {
                            s.classList.remove("bi-star-fill", "text-warning");
                            s.classList.add("bi-star");
                        }
                    });
                });
            });
        });
    </script>
</body>

</html>
