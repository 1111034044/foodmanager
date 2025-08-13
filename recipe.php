<?php
$fromNavbar = true;
session_start(); // 啟動 session

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 從 session 中獲取 uId
$uId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// 處理搜尋關鍵字
$searchRecipeQuery = isset($_GET['search_recipe']) ? trim($_GET['search_recipe']) : ''; // 搜尋食譜名稱或類別
$searchIngredientQuery = isset($_GET['search_ingredient']) ? trim($_GET['search_ingredient']) : ''; // 搜尋食材名稱
$searchAuthorQuery = isset($_GET['search_author']) ? trim($_GET['search_author']) : ''; // 搜尋作者名稱
$searchType = isset($_GET['search_type']) ? $_GET['search_type'] : 'recipe'; // 預設搜尋食譜

// 處理排序條件
$sortQuery = isset($_GET['sort']) ? $_GET['sort'] : 'upload_date';  // 預設為上傳日期排序

// 查詢所有食譜資料，並聯表獲取上傳者的資訊
$query = "
    SELECT r.*, u.uName, u.uImage, IFNULL(l.LikeCount, 0) AS LikeCount
    FROM Recipe r
    JOIN user u ON r.uId = u.uId
    LEFT JOIN (
        SELECT RecipeId, COUNT(*) AS LikeCount
        FROM recipe_likes
        GROUP BY RecipeId
    ) l ON r.RecipeId = l.RecipeId
";

// 搜尋條件
$conditions = [];
if (!empty($searchRecipeQuery)) {
    $searchRecipeQuery = $conn->real_escape_string($searchRecipeQuery);
    $conditions[] = "(r.rName LIKE '%$searchRecipeQuery%')";
}

if (!empty($searchIngredientQuery)) {
    $searchIngredientQuery = $conn->real_escape_string($searchIngredientQuery);
    $conditions[] = "EXISTS (
        SELECT 1
        FROM recipeingredient ri
        WHERE ri.RecipeId = r.RecipeId AND ri.IngredientName LIKE '%$searchIngredientQuery%'
    )";
}

if (!empty($searchAuthorQuery)) {
    $searchAuthorQuery = $conn->real_escape_string($searchAuthorQuery);
    $conditions[] = "u.uName LIKE '%$searchAuthorQuery%'";
}

// 合併條件
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

// 排序條件
switch ($sortQuery) {
    case 'upload_date':
        $query .= " ORDER BY r.UploadDate DESC";
        break;
    case 'difficulty_level':
        $query .= " ORDER BY 
            CASE 
                WHEN r.DifficultyLevel = '簡單' THEN 1 
                WHEN r.DifficultyLevel = '中等' THEN 2 
                WHEN r.DifficultyLevel = '困難' THEN 3 
                ELSE 4 
            END ASC";
        break;
    case 'like_count':
        $query .= " ORDER BY LikeCount DESC";
        break;
}

$result = $conn->query($query);

$recipes = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recipes[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>食材管理與購物清單</title>
    <link rel="stylesheet" href="css/boot.css">
    <link rel="stylesheet" href="css/recipe_like.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>

<body>
    <!-- 導覽列 -->
    <?php include 'navbar.php'; ?>
    <!-- 側邊選單 -->
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">搜尋食譜</h2>

            <div class="mb-3 d-flex justify-content-start align-items-center gap-3">
                <form method="GET" action="recipe.php" class="d-flex mb-3">
                    <!-- 下拉選單：選擇搜尋類型（食譜或作者） -->
                    <div class="input-group">
                        <select name="search_type" class="form-select me-2" style="width: 10px; overflow: hidden; text-overflow: ellipsis;" onchange="this.form.submit()">
                            <option value="recipe" <?php echo ($searchType == 'recipe') ? 'selected' : ''; ?>>搜尋食譜</option>
                            <option value="author" <?php echo ($searchType == 'author') ? 'selected' : ''; ?>>搜尋作者</option>
                        </select>
                    </div>

                    <!-- 根據選擇搜尋類型顯示相應的搜尋框 -->
                    <?php if ($searchType == 'recipe'): ?>
                        <!-- 搜尋食譜名稱或類別 -->
                        <input type="text" name="search_recipe" class="form-control me-2" style="width: 300px;" placeholder="搜尋食譜名稱" value="<?php echo htmlspecialchars($searchRecipeQuery); ?>">
                    <?php elseif ($searchType == 'author'): ?>
                        <!-- 搜尋作者 -->
                        <input type="text" name="search_author" class="form-control me-2" style="width: 300px;" placeholder="搜尋作者" value="<?php echo htmlspecialchars($searchAuthorQuery); ?>">
                    <?php endif; ?>

                    <!-- 右邊的搜尋欄：搜尋食材名稱 -->
                    <input type="text" name="search_ingredient" class="form-control me-2" style="width: 300px;" placeholder="搜尋食材" value="<?php echo htmlspecialchars($searchIngredientQuery); ?>">

                    <select name="sort" class="form-control me-2" style="width: 180px;">
                        <option value="upload_date" <?php echo ($sortQuery == 'upload_date') ? 'selected' : ''; ?>>▼按上傳日期排序</option>
                        <option value="difficulty_level" <?php echo ($sortQuery == 'difficulty_level') ? 'selected' : ''; ?>>按難度排序</option>
                        <option value="like_count" <?php echo ($sortQuery == 'like_count') ? 'selected' : ''; ?>>按愛心數排序</option>
                    </select>

                    <button type="submit" class="btn custom-btn-light-blue">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
                <a href="add_recipe.php" class="btn custom-btn-upload mb-3">
                    <i class="bi bi-plus-circle me-2"></i>上傳食譜
                </a>
            </div>

        <div class="row">
            <?php if (empty($recipes)): ?>
                <div class="col-12">
                    <p>尚未有任何食譜或找不到符合條件的食譜。</p>
                </div>
            <?php else: ?>
                <?php foreach ($recipes as $recipe):
                    $liked = false;
                    $favorited = false;
                    $likeCount = isset($recipe['LikeCount']) ? (int)$recipe['LikeCount'] : 0;

                    if (isset($_SESSION['user_id'], $recipe['RecipeId']) && $conn) {
                        $uid = (int)$_SESSION['user_id'];
                        $rid = (int)$recipe['RecipeId'];

                        // 檢查是否已按讚
                        $stmt_like = $conn->prepare("SELECT 1 FROM recipe_likes WHERE RecipeId = ? AND UserId = ?");
                        if ($stmt_like) {
                            $stmt_like->bind_param("ii", $rid, $uid);
                            $stmt_like->execute();
                            $stmt_like->store_result();
                            $liked = $stmt_like->num_rows > 0;
                            $stmt_like->close();
                        }

                        // 檢查是否已收藏
                        $stmt_fav = $conn->prepare("SELECT 1 FROM recipe_favorites WHERE RecipeId = ? AND UserId = ?");
                        if ($stmt_fav) {
                            $stmt_fav->bind_param("ii", $rid, $uid);
                            $stmt_fav->execute();
                            $stmt_fav->store_result();
                            $favorited = $stmt_fav->num_rows > 0;
                            $stmt_fav->close();
                        }
                    }
                ?>

                    <div class="col-md-4">
                        <div class="card">
                            <a href="recipe_detail.php?RecipeId=<?php echo $recipe['RecipeId']; ?>" class="text-decoration-none">
                                <?php if ($recipe['CoverImage']): ?>
                                    <img src="<?php echo htmlspecialchars($recipe['CoverImage']); ?>" class="card-img-top" alt="食譜封面">
                                <?php else: ?>
                                    <img src="img/recipe-placeholder.jpg" class="card-img-top" alt="預設封面">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($recipe['rName']); ?></h5>
                                    <!-- 顯示敘述（如果有） -->
                                    <?php if (!empty($recipe['Description'])): ?>
                                        <div class="mb-3">
                                            <p><?php echo htmlspecialchars($recipe['Description']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            簡單料理程度：<?php echo htmlspecialchars($recipe['DifficultyLevel'] ?? '未指定'); ?>
                                        </small>
                                    </p>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            預計用時：<?php echo $recipe['cooktime'] ? $recipe['cooktime'] . ' 分鐘' : '未指定'; ?>
                                        </small>
                                    </p>
                                </div>
                            </a>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <div class="card-footer d-flex justify-content-between align-items-center">
                                    <!-- 左邊：瀏覽數 -->
                                    <div class="d-flex align-items-center" style="color: #555; font-size: 1.2rem;">
                                        <i class="bi bi-eye"></i>
                                        <span style="margin-left: 5px;"><?php echo htmlspecialchars($recipe['ViewCount'] ?? 0); ?></span>
                                    </div>

                                    <!-- 右邊：喜歡和收藏 -->
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
                                        <?php endif; ?>
                                        <form id="favorite-form-<?php echo $recipe['RecipeId']; ?>" action="toggle_favorite.php" method="POST" style="margin-left: 10px;">
                                            <input type="hidden" name="RecipeId" value="<?php echo $recipe['RecipeId']; ?>">
                                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] . '#favorite-form-' . $recipe['RecipeId']); ?>">
                                            <button type="submit" class="btn btn-link favorite-btn">
                                                <i class="bi <?= $favorited ? 'bi-bookmark-fill' : 'bi-bookmark'; ?>"></i>
                                            </button>
                                        </form>
                                    </div>

                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }

        .card:hover {
            transform: scale(1.05);
            /* 放大 5% */
        }

        .card-img-top {
            height: 200px;
            object-fit: cover;
        }

        .card-footer {
            background-color: #f8f9fa;
        }

        /* 確保按鈕點擊時不會觸發卡片的跳轉 */
        .card-footer form {
            display: inline-block;
        }

        /* 自訂淺藍色按鈕 */
        .custom-btn-light-blue {
            background-color: rgb(137, 215, 241);
            /* 淺藍色背景 */
            border-color: #ADD8E6;
            /* 淺藍色邊框 */
            color: rgb(85, 87, 88);
            padding: 0.375rem 0.75rem;
            /* 與Bootstrap btn 相同的內邊距 */
            border-radius: 0.25rem;
            /* 與Bootstrap btn 相同的圓角 */
            font-size: 1rem;
            /* 與Bootstrap btn 相同的字體大小 */
            line-height: 1.5;
            /* 與Bootstrap btn 相同的行高 */
            transition: background-color 0.3s ease;
            /* 背景色過渡效果 */
        }

        .custom-btn-light-blue:hover {
            background-color: rgb(109, 199, 235);
            /* 懸停時略深的淺藍色 */
            border-color: #87CEEB;
            /* 懸停時邊框也變色 */
        }

        /* 上傳食譜按鈕樣式 */
        .custom-btn-upload {
            background-color: #28a745;
            /* 綠色背景 */
            border-color: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .custom-btn-upload:hover {
            background-color: #218838;
            border-color: #1e7e34;
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }

        form.d-flex button {
            display: inline-flex;
            /* 使按鈕的內容呈現橫向排列 */
            align-items: center;
            /* 垂直居中對齊按鈕內的內容 */
            justify-content: center;
            /* 讓按鈕文字水平居中 */
            white-space: nowrap;
            /* 防止文字換行 */
        }
    </style>
</body>

</html>
<?php $conn->close(); ?>
