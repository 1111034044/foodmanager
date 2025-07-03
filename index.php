<?php
session_start();

// 檢查是否登入，允許訪客使用隨機食譜功能
$near_expiry_ingredients = [];
$recommended_recipes = [];

// 資料庫連線
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'foodmanager';

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("資料庫連線失敗: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 登入使用者才查詢即將過期食材
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $today = date('Y-m-d');
    $expiry_threshold = date('Y-m-d', strtotime('+30 days'));

    $sql = "SELECT TRIM(i.IName) AS IName, i.ExpireDate 
            FROM ingredient i 
            WHERE i.uId = ? AND i.ExpireDate IS NOT NULL 
            AND i.ExpireDate BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $uid, $today, $expiry_threshold);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $near_expiry_ingredients[] = [
            'name' => $row['IName'],
            'expire_date' => $row['ExpireDate']
        ];
    }
    $stmt->close();

    // 推薦食譜
    if (!empty($near_expiry_ingredients)) {
        $ingredient_names = array_column($near_expiry_ingredients, 'name');
        $sql = "SELECT DISTINCT r.RecipeId, r.rName, r.CoverImage, r.DifficultyLevel, r.cooktime
                FROM recipe r
                JOIN recipeingredient ri ON r.RecipeId = ri.RecipeId
                WHERE ";
        $conditions = [];
        $params = [];
        foreach ($ingredient_names as $name) {
            $conditions[] = "TRIM(ri.IngredientName) LIKE ?";
            $params[] = "%$name%";
        }
        $sql .= implode(" OR ", $conditions) . " LIMIT 3";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(str_repeat("s", count($params)), ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $recommended_recipes[] = $row;
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>食材管理系統</title>
    <link rel="stylesheet" href="css/boot.css">
    <link rel="stylesheet" href="css/get_random_recipes.css">
    <link rel="stylesheet" href="css/index.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .recipe-card {
            background-color: #f8f9fa !important; /* 淺灰色背景 */
            border: 1px solid #dee2e6 !important; /* 輕微邊框 */
            border-radius: 0.75rem !important; /* 增大圓角範圍 */
            transition: transform 0.2s;
        }

        .recipe-card:hover {
            transform: scale(1.05);
        }

        .recipe-card .card-body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 1rem;
            color: #333 !important; /* 深灰色文字，確保可見 */
        }

        .recipe-card .card-body h5 {
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }

        .recipe-card .card-body p {
            margin-bottom: 0;
            font-size: 1rem;
        }

        .recipe-card .card-body hr {
            width: 50%;
            margin: 0.5rem 0;
            border: 0;
            border-top: 1px solid #dee2e6; /* 分隔線 */
        }

        /* 動態調整寬度 */
        .near-expiry-section .recipe-card {
            min-width: 200px;
            max-width: 300px;
            width: auto;
            word-wrap: break-word;
        }

        /* 確保響應式佈局 */
        .near-expiry-section .row {
            justify-content: center;
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in forwards !important; /* 確保動畫執行 */
            opacity: 0; /* 初始狀態為透明 */
        }

        /* 添加瀏覽器前綴以提高兼容性 */
        @-webkit-keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* 即將過期食材卡片容器樣式 */
        .expiry-ingredients-card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }

        /* 即將過期食材項目樣式 */
        .expiry-ingredient-item {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: transform 0.2s;
            border: 1px solid #dee2e6;
            text-align: center;
        }

        .expiry-ingredient-item:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .expiry-ingredient-item h5 {
            margin-bottom: 8px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .expiry-ingredient-item p {
            margin-bottom: 0;
            font-size: 0.9rem;
            color: #6c757d;
        }

        /* 即將過期食材區域的標題樣式 */
        .expiry-ingredients-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <!-- 輪播區域 -->
    <div class="carousel-section">
        <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
            <!-- 輪播指示器 -->
            <div class="carousel-indicators">
                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
            </div>

            <!-- 輪播內容 -->
            <div class="carousel-inner">
                <div class="carousel-item active" style="background-image: url('img/kitchen.jpg');" data-bs-interval="5000">
                    <div class="d-flex justify-content-center align-items-center h-100">
                        <div class="carousel-content">
                            <h2>歡迎使用食材管理系統</h2>
                            <p>輕鬆管理您的食材庫存，讓每一天的飲食更加健康。</p>
                        </div>
                    </div>
                </div>
                <div class="carousel-item" style="background-image: url('img/original.jpg');" data-bs-interval="5000">
                    <div class="d-flex justify-content-center align-items-center h-100">
                        <div class="carousel-content">
                            <h2>探索美味食譜</h2>
                            <p>根據您的食材，發現無限可能的料理靈感。</p>
                        </div>
                    </div>
                </div>
                <div class="carousel-item" style="background-image: url('img/list.jpg');" data-bs-interval="5000">
                    <div class="d-flex justify-content-center align-items-center h-100">
                        <div class="carousel-content">
                            <h2>高效購物清單</h2>
                            <p>一鍵生成所需食材，讓採購變得簡單快捷。</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 輪播控制按鈕 -->
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">上一頁</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">下一頁</span>
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- 即將過期食材 -->
    <div class="near-expiry-section container mt-5">
        <div class="expiry-ingredients-card">
            <h2 class="expiry-ingredients-title">即將過期食材</h2>
            <?php if (empty($near_expiry_ingredients)): ?>
                <p class="text-center text-warning">目前沒有即將過期的食材。</p>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-3">
                    <?php 
                    // 僅顯示最多 10 個食材
                    $display_ingredients = array_slice($near_expiry_ingredients, 0, 10);
                    foreach ($display_ingredients as $ingredient): 
                    ?>
                        <div class="col fade-in">
                            <div class="expiry-ingredient-item">
                                <h5><?= htmlspecialchars($ingredient['name']) ?></h5>
                                <p>到期日: <?= htmlspecialchars($ingredient['expire_date']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($near_expiry_ingredients) > 10): ?>
                <div class="text-end mt-3">
                    <a href="ingredients.php" class="btn btn-outline-primary btn-sm">查看所有即將過期食材</a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 即將過期推薦 -->
    <div class="expiry-recipes-section container mt-5">
        <h2>即將過期食材推薦食譜</h2>
        <div class="row" id="expiry-recipes">
            <?php if (empty($recommended_recipes)): ?>
            <p class="text-center text-warning">目前沒有即將過期的食材或推薦食譜。</p>
            <?php else: ?>
            <?php foreach (array_slice($recommended_recipes, 0, 3) as $recipe): ?>
            <div class="col-sm-6 col-md-4 fade-in mb-4">
                <div class="recipe-card">
                    <a href="recipe_detail.php?RecipeId=<?= $recipe['RecipeId'] ?>" class="text-decoration-none">
                        <img src="<?= $recipe['CoverImage'] ?: 'images/recipe-placeholder.jpg' ?>" alt="封面">
                        <div class="card-body">
                            <h5><?= htmlspecialchars($recipe['rName']) ?></h5>
                            <p>簡單程度：<?= $recipe['DifficultyLevel'] ?: '未指定' ?></p>
                            <p>預計用時：<?= $recipe['cooktime'] ? $recipe['cooktime'] . ' 分鐘' : '未指定' ?></p>
                        </div>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (count($recommended_recipes) > 3): ?>
        <div class="text-end">
            <a href="near_expiry_recipes.php" class="btn btn-outline-primary">查看更多推薦食譜</a>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 隨機推薦 -->
    <div class="random-recipes-section container mt-5">
        <h2>隨機推薦食譜</h2>
        <button class="btn btn-primary mb-3" onclick="fetchRandomRecipes()">好手氣！</button>
        <div class="row" id="random-recipes"></div>
    </div>

    <script>
    // 初次載入
    fetchRandomRecipes();

    function fetchRandomRecipes() {
        fetch('get_random_recipes.php')
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('random-recipes');
                if (container) {
                    container.innerHTML = ''; // 清空現有內容
                    data.forEach(recipe => {
                        // 使用模板字符串生成卡片，並添加 fade-in 類別
                        const card = document.createElement('div');
                        card.className = 'col-md-4 fade-in mb-4';
                        card.innerHTML = `
                            <div class="recipe-card">
                                <a href="recipe_detail.php?RecipeId=${recipe.RecipeId}" class="text-decoration-none">
                                    <img src="${recipe.CoverImage || 'img/recipe-placeholder.jpg'}" alt="封面">
                                    <div class="card-body">
                                        <h5>${recipe.rName}</h5>
                                        <p>難度：${recipe.DifficultyLevel || '未指定'}</p>
                                        <p>烹飪時間：${recipe.cooktime || '未指定'} 分鐘</p>
                                    </div>
                                </a>
                            </div>`;
                        // 強制觸發重新渲染以啟動動畫
                        setTimeout(() => {
                            container.appendChild(card);
                            card.style.opacity = '0'; // 初始透明
                            setTimeout(() => {
                                card.classList.add('fade-in'); // 觸發動畫
                            }, 10); // 短延遲確保 DOM 更新
                        }, 0);
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching random recipes:', error);
            });
    }

    // 調試：檢查 near-expiry-ingredients 是否存在並觸發動畫
    document.addEventListener('DOMContentLoaded', () => {
        const nearExpiryContainer = document.getElementById('near-expiry-ingredients');
        if (nearExpiryContainer) {
            console.log('Near expiry ingredients container loaded:', nearExpiryContainer.children.length, 'items');
            // 確保卡片動畫和圓角生效
            const cards = nearExpiryContainer.querySelectorAll('.recipe-card');
            cards.forEach(card => {
                card.classList.add('fade-in');
                card.style.borderRadius = '0.75rem'; // 確保圓角
                // 強制觸發重新渲染
                setTimeout(() => {
                    card.style.opacity = '0';
                    setTimeout(() => {
                        card.classList.add('fade-in'); // 再次觸發動畫
                    }, 10);
                }, 0);
            });
        } else {
            console.error('Near expiry ingredients container not found');
        }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<head>
    <!-- 現有的 head 內容 -->
    <style>
        /* 現有的樣式 */
        
        /* 通知樣式 */
        .notification-dropdown {
            padding: 0;
        }
        
        .notification-dropdown .dropdown-header {
            background-color: #f8f9fa;
            padding: 10px 15px;
            font-weight: bold;
        }
        
        .notification-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
</body>
</html>