<?php
session_start();

// 檢查用戶是否已登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 從 session 中獲取 uId
$uId = $_SESSION['user_id'];

// 查詢即將過期的食材（7天內）
$today = date('Y-m-d');
$expiryThreshold = date('Y-m-d', strtotime('+7 days'));
$sql = "SELECT IngredientId, IName, Quantity, Unit, ExpireDate, StoreType 
        FROM Ingredient 
        WHERE uId = ? AND ExpireDate IS NOT NULL 
        AND ((ExpireDate BETWEEN ? AND ?) OR (ExpireDate < ?)) 
        AND Quantity > 0 
        ORDER BY ExpireDate ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $uId, $today, $expiryThreshold, $today);
$stmt->execute();
$result = $stmt->get_result();

$expiringIngredients = [];
$expiredIngredients = [];

while ($row = $result->fetch_assoc()) {
    // 區分已過期和即將過期
    if (strtotime($row['ExpireDate']) < strtotime($today)) {
        $expiredIngredients[] = $row;
    } else {
        $expiringIngredients[] = $row;
    }
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>過期食材推薦餐點</title>
    <link rel="stylesheet" href="css/boot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .ingredient-list {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .expired {
            color: #dc3545;
        }
        
        .expiring {
            color: #fd7e14;
        }
        
        .meal-card {
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .meal-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .nutrition-info {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .nutrition-item {
            background-color: #f8f9fa;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.9rem;
        }
        
        .recipe-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        
        #loadingRecommend {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h1 class="mb-4">過期食材推薦餐點</h1>

        <div class="row">
            <div class="col-md-4">
                <div class="ingredient-list">
                    <h3>即將過期的食材</h3>
                    <?php if (empty($expiringIngredients)): ?>
                        <p>沒有即將過期的食材</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($expiringIngredients as $ingredient): 
                                $expireDate = new DateTime($ingredient['ExpireDate']);
                                $today = new DateTime();
                                $interval = $today->diff($expireDate);
                                $daysLeft = $interval->days;
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center expiring">
                                    <div class="form-check">
                                        <input class="form-check-input ingredient-checkbox" type="checkbox" 
                                               value="<?= $ingredient['IngredientId'] ?>" 
                                               id="ingredient-<?= $ingredient['IngredientId'] ?>" checked>
                                        <label class="form-check-label" for="ingredient-<?= $ingredient['IngredientId'] ?>">
                                            <?= htmlspecialchars($ingredient['IName']) ?>
                                            (<?= $ingredient['Quantity'] ?> <?= $ingredient['Unit'] ?: '個' ?>)
                                        </label>
                                    </div>
                                    <span class="badge bg-warning text-dark">
                                        還有 <?= $daysLeft ?> 天過期
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <div class="ingredient-list">
                    <h3>已過期的食材</h3>
                    <?php if (empty($expiredIngredients)): ?>
                        <p>沒有已過期的食材</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($expiredIngredients as $ingredient): 
                                $expireDate = new DateTime($ingredient['ExpireDate']);
                                $today = new DateTime();
                                $interval = $today->diff($expireDate);
                                $daysExpired = $interval->days;
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center expired">
                                    <div class="form-check">
                                        <input class="form-check-input ingredient-checkbox" type="checkbox" 
                                               value="<?= $ingredient['IngredientId'] ?>" 
                                               id="ingredient-<?= $ingredient['IngredientId'] ?>" checked>
                                        <label class="form-check-label" for="ingredient-<?= $ingredient['IngredientId'] ?>">
                                            <?= htmlspecialchars($ingredient['IName']) ?>
                                            (<?= $ingredient['Quantity'] ?> <?= $ingredient['Unit'] ?: '個' ?>)
                                        </label>
                                    </div>
                                    <span class="badge bg-danger">
                                        已過期 <?= $daysExpired ?> 天
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <?php if (!empty($expiringIngredients) || !empty($expiredIngredients)): ?>
                    <button id="getRecommendBtn" class="btn btn-primary w-100 mb-3">獲取推薦餐點</button>
                    <button id="getAnotherRecommendBtn" class="btn btn-outline-primary w-100 mb-3 d-none">換一個推薦</button>
                <?php else: ?>
                    <div class="alert alert-warning">
                        沒有即將過期或已過期的食材，無法提供推薦。
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <div id="loadingRecommend" class="d-none">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>正在分析食材並生成推薦餐點...</p>
                </div>

                <div id="recommendResult" class="d-none">
                    <div class="meal-card">
                        <div class="meal-header">
                            <h2 id="mealName"></h2>
                            <p id="mealDescription" class="text-muted"></p>
                        </div>

                        <div class="nutrition-info">
                            <div class="nutrition-item">
                                <i class="bi bi-fire"></i> <span id="mealCalorie"></span> 卡路里
                            </div>
                            <div class="nutrition-item">
                                <i class="bi bi-egg"></i> 蛋白質 <span id="mealProtein"></span>g
                            </div>
                            <div class="nutrition-item">
                                <i class="bi bi-droplet"></i> 脂肪 <span id="mealFat"></span>g
                            </div>
                            <div class="nutrition-item">
                                <i class="bi bi-circle"></i> 碳水 <span id="mealCarb"></span>g
                            </div>
                            <div class="nutrition-item">
                                <i class="bi bi-tree"></i> 纖維 <span id="mealFiber"></span>g
                            </div>
                        </div>
                        
                        <div class="ingredients-section mt-3">
                            <h4><i class="bi bi-basket"></i> 食材準備</h4>
                            <ul id="mealIngredients" class="list-group"></ul>
                        </div>

                        <div class="reason-section">
                            <h4><i class="bi bi-lightbulb"></i> 推薦理由</h4>
                            <p id="mealReason"></p>
                        </div>

                        <div class="recipe-section">
                            <h4><i class="bi bi-journal-text"></i> 食譜</h4>
                            <p id="mealRecipe"></p>
                        </div>

                        <div class="mt-4">
                            <button id="addToCalorieBtn" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> 加入今日熱量記錄
                            </button>
                        </div>
                    </div>
                </div>

                <div id="noIngredientsMessage" class="<?= (empty($expiringIngredients) && empty($expiredIngredients)) ? '' : 'd-none' ?>">
                    <div class="alert alert-info">
                        <h4><i class="bi bi-info-circle"></i> 沒有需要處理的食材</h4>
                        <p>目前沒有即將過期或已過期的食材需要處理。您可以前往<a href="IngredientManager.php">食材管理</a>頁面查看您的食材狀態。</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentRecommendation = null;

        document.getElementById('getRecommendBtn').addEventListener('click', function() {
            getRecommendation(false);
        });

        document.getElementById('getAnotherRecommendBtn').addEventListener('click', function() {
            getRecommendation(true);
        });

        function getRecommendation(isRetry) {
            document.getElementById('loadingRecommend').classList.remove('d-none');
            document.getElementById('recommendResult').classList.add('d-none');

            // 獲取選中的食材
            const selectedIngredients = [];
            document.querySelectorAll('.ingredient-checkbox:checked').forEach(checkbox => {
                selectedIngredients.push(checkbox.value);
            });
            
            if (selectedIngredients.length === 0) {
                alert('請至少選擇一個食材來獲取推薦');
                document.getElementById('loadingRecommend').classList.add('d-none');
                return;
            }
            
            // 構建 URL 參數
            const params = new URLSearchParams();
            if (isRetry) params.append('retry', '1');
            selectedIngredients.forEach(id => params.append('ingredients[]', id));
            
            const url = `expiring_food_recommend_api.php?${params.toString()}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingRecommend').classList.add('d-none');
                    
                    if (data.success) {
                        currentRecommendation = data.meal;
                        displayRecommendation(data.meal);
                        document.getElementById('recommendResult').classList.remove('d-none');
                        document.getElementById('getAnotherRecommendBtn').classList.remove('d-none');
                    } else {
                        alert(data.message || '無法生成推薦餐點');
                    }
                })
                .catch(error => {
                    document.getElementById('loadingRecommend').classList.add('d-none');
                    alert('發生錯誤：' + error.message);
                });
        }

        function displayRecommendation(meal) {
            document.getElementById('mealName').textContent = meal.meal_name;
            document.getElementById('mealDescription').textContent = meal.description;
            document.getElementById('mealCalorie').textContent = meal.calorie;
            document.getElementById('mealProtein').textContent = meal.protein;
            document.getElementById('mealFat').textContent = meal.fat;
            document.getElementById('mealCarb').textContent = meal.carb;
            document.getElementById('mealFiber').textContent = meal.fiber;
            document.getElementById('mealReason').textContent = meal.reason;
            document.getElementById('mealRecipe').textContent = meal.recipe;
            
            // 顯示食材準備
            const ingredientsList = document.getElementById('mealIngredients');
            ingredientsList.innerHTML = '';
            
            if (meal.ingredients_needed && Array.isArray(meal.ingredients_needed) && meal.ingredients_needed.length > 0) {
                meal.ingredients_needed.forEach(ingredient => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item';
                    li.innerHTML = `<i class="bi bi-check-circle-fill text-success me-2"></i>${ingredient}`;
                    ingredientsList.appendChild(li);
                });
            } else {
                const li = document.createElement('li');
                li.className = 'list-group-item text-muted';
                li.textContent = '未提供詳細食材清單';
                ingredientsList.appendChild(li);
            }
        }

        document.getElementById('addToCalorieBtn').addEventListener('click', function() {
            if (!currentRecommendation) return;
            
            // 創建一個表單來提交 POST 請求
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'add_calorie_record.php';
            form.style.display = 'none';
            
            // 添加所需的表單字段
            const fields = {
                'food_name': currentRecommendation.meal_name,
                'calorie': currentRecommendation.calorie,
                'protein': currentRecommendation.protein,
                'fat': currentRecommendation.fat,
                'carb': currentRecommendation.carb,
                'fiber': currentRecommendation.fiber
            };
            
            for (const key in fields) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
            
            // 使用 fetch 提交表單數據
            fetch('add_calorie_record.php', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => response.text())
            .then(data => {
                if (data === 'ok') {
                    // 直接消耗食材，不再檢查選擇框
                    consumeIngredients(true); // 傳遞參數表示成功後跳轉
                } else {
                    alert('加入熱量記錄失敗：' + data);
                }
            })
            .catch(error => {
                alert('發生錯誤：' + error.message);
            });
        });
        
        // 修改消耗食材的函數，添加更好的錯誤處理
        function consumeIngredients(redirectAfterSuccess = false) {
            // 獲取當前選中的食材 ID
            const selectedIngredients = [];
            document.querySelectorAll('.ingredient-checkbox:checked').forEach(checkbox => {
                selectedIngredients.push(checkbox.value);
            });
            
            // 發送請求到後端 API 消耗食材
            fetch('consume_ingredients.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ingredients: selectedIngredients.length > 0 ? selectedIngredients : 'all',
                    meal_name: currentRecommendation.meal_name
                })
            })
            .then(response => {
                // 檢查響應狀態
                if (!response.ok) {
                    throw new Error(`HTTP 錯誤! 狀態: ${response.status}`);
                }
                // 檢查內容類型
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // 如果不是 JSON，先獲取文本內容再拋出錯誤
                    return response.text().then(text => {
                        throw new Error(`預期 JSON 但收到: ${text.substring(0, 100)}...`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(`已自動消耗 ${data.consumed_count} 個食材！`);
                    
                    // 如果需要跳轉，則跳轉到熱量記錄頁面
                    if (redirectAfterSuccess) {
                        setTimeout(() => {
                            window.location.href = 'calorie_tracker.php';
                        }, 1000);
                    } else {
                        // 否則重新載入當前頁面
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    alert(data.message || '消耗食材失敗');
                }
            })
            .catch(error => {
                console.error('錯誤:', error);
                alert('發生錯誤：' + error.message);
                
                // 即使發生錯誤，如果用戶選擇了跳轉，也跳轉到熱量記錄頁面
                if (redirectAfterSuccess) {
                    setTimeout(() => {
                        window.location.href = 'calorie_tracker.php';
                    }, 1000);
                }
            });
        }
    </script>
</body>

</html>