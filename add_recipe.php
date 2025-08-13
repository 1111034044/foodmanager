<?php
session_start(); // 啟動 session
// 檢查用戶是否已登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // 未登入則跳轉到登入頁面
    exit();
}

// 生成 CSRF 令牌
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 從 session 中獲取 uId
$uId = $_SESSION['user_id'];

// 查詢 nutrition_facts 表的食材名稱
$stmt = $conn->prepare("SELECT DISTINCT sample_name FROM nutrition_facts");
$stmt->execute();
$result = $stmt->get_result();

$allIngredients = [];
while ($row = $result->fetch_assoc()) {
    $allIngredients[] = $row['sample_name'];
}
$stmt->close();

// 處理新食譜的上傳
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_recipe'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "CSRF 驗證失敗，請重試！";
        goto display_form;
    }

    $rName = trim($_POST['rName']);
    $cooktime = $_POST['cooktime'] ? (int)$_POST['cooktime'] : NULL;
    $difficultyLevel = $_POST['difficultyLevel'] ?: NULL;
    $description = $_POST['description'] ?: NULL;
    $coverImage = NULL;

    if (empty($rName)) {
        $error_message = "食譜名稱不得為空！";
        goto display_form;
    }

    // 處理封面圖片上傳
    if (isset($_FILES['coverImage']) && $_FILES['coverImage']['error'] == 0) {
        $targetDir = "Uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $fileName = basename($_FILES['coverImage']['name']);
        $targetFile = $targetDir . time() . "_" . $fileName;
        if (!move_uploaded_file($_FILES['coverImage']['tmp_name'], $targetFile)) {
            $error_message = "封面圖片上傳失敗，請檢查 uploads/ 資料夾權限！";
            goto display_form;
        }
        $coverImage = $targetFile;
    } elseif (isset($_FILES['coverImage']) && $_FILES['coverImage']['error'] != UPLOAD_ERR_NO_FILE) {
        $error_message = "封面圖片上傳錯誤，錯誤碼：" . $_FILES['coverImage']['error'];
        goto display_form;
    }

    // 插入 Recipe 資料
    $stmt = $conn->prepare("
        INSERT INTO Recipe (rName, cooktime, DifficultyLevel, Description, uId, CoverImage)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sissis", $rName, $cooktime, $difficultyLevel, $description, $uId, $coverImage);

    if (!$stmt->execute()) {
        $error_message = "插入食譜失敗: " . $stmt->error;
        $stmt->close();
        goto display_form;
    }

    $recipeId = $stmt->insert_id;
    $stmt->close();

    // 添加通知
    $role = $_SESSION['user_role'] ?? '未指定';
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, content, item_id, item_name, role) VALUES (?, 'recipe', '新增了食譜', ?, ?, ?)");
    $stmt->bind_param("iiss", $uId, $recipeId, $rName, $role);
    $stmt->execute();
    $stmt->close();

    // 插入標籤（RecipeTags）
    if (!empty($_POST['tags'])) {
        $tags = explode(',', $_POST['tags']);
        $stmtTag = $conn->prepare("INSERT INTO RecipeTags (RecipeId, Tag) VALUES (?, ?)");
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag !== '') {
                $stmtTag->bind_param("is", $recipeId, $tag);
                if (!$stmtTag->execute()) {
                    $error_message = "插入標籤失敗: " . $stmtTag->error;
                    $stmtTag->close();
                    goto display_form;
                }
            }
        }
        $stmtTag->close();
    }

    // 插入食材（RecipeIngredients）
    if (isset($_POST['ingredients']['name']) && !empty(array_filter($_POST['ingredients']['name']))) {
        $validUnits = ['個', '克', '毫升', '瓶', '包', '公斤', ''];
        $stmtIngredient = $conn->prepare("
            INSERT INTO recipeingredient (RecipeId, IngredientName, Quantity, Unit)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($_POST['ingredients']['name'] as $index => $ingredientName) {
            $ingredientName = trim($ingredientName);
            if ($ingredientName === '') {
                continue;
            }

            $quantity = isset($_POST['ingredients']['quantity'][$index]) ? trim($_POST['ingredients']['quantity'][$index]) : '';
            $unit = isset($_POST['ingredients']['unit'][$index]) && in_array($_POST['ingredients']['unit'][$index], $validUnits) ? $_POST['ingredients']['unit'][$index] : NULL;

            $stmtIngredient->bind_param("isss", $recipeId, $ingredientName, $quantity, $unit);
            if (!$stmtIngredient->execute()) {
                $error_message = "插入食材失敗: " . $stmtIngredient->error;
                $stmtIngredient->close();
                goto display_form;
            }
        }
        $stmtIngredient->close();
    } else {
        $error_message = "請至少提供一個有效食材！";
        goto display_form;
    }

    // 插入 RecipeSteps（步驟）
    if (isset($_POST['steps']['description']) && !empty(array_filter($_POST['steps']['description']))) {
        $stmtStep = $conn->prepare("
            INSERT INTO RecipeSteps (RecipeId, StepOrder, StepDescription, StepImage)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($_POST['steps']['description'] as $index => $stepDescription) {
            $stepDescription = trim($stepDescription);
            if ($stepDescription === '') {
                continue;
            }

            $stepImage = NULL;
            if (
                isset($_FILES['steps']['tmp_name']['image'][$index]) &&
                is_uploaded_file($_FILES['steps']['tmp_name']['image'][$index])
            ) {
                $stepDir = "Uploads/steps/";
                if (!is_dir($stepDir)) {
                    mkdir($stepDir, 0755, true);
                }
                $stepFileName = basename($_FILES['steps']['name']['image'][$index]);
                $stepFilePath = $stepDir . time() . "_" . $stepFileName;

                if (!move_uploaded_file($_FILES['steps']['tmp_name']['image'][$index], $stepFilePath)) {
                    $error_message = "步驟圖片上傳失敗，請檢查 uploads/steps/ 資料夾權限！";
                    $stmtStep->close();
                    goto display_form;
                }
                $stepImage = $stepFilePath;
            }

            $stepOrder = $index + 1;
            $stmtStep->bind_param("iiss", $recipeId, $stepOrder, $stepDescription, $stepImage);
            if (!$stmtStep->execute()) {
                $error_message = "插入步驟失敗: " . $stmtStep->error;
                $stmtStep->close();
                goto display_form;
            }
        }
        $stmtStep->close();
    } 

    header("Location: recipe_detail.php?RecipeId=" . $recipeId . "&status=created");
    exit();
}

$conn->close();

display_form:
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增食譜</title>
    <link rel="stylesheet" href="css/boot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
    <style>
        .ingredients-container {
            background-color: #ffffff;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            margin: 0 auto 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2>新增食譜</h2>
        <div class="bg-white p-4 rounded shadow-sm">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-3">
                    <label for="rName" class="form-label">食譜名稱</label>
                    <input type="text" class="form-control border-secondary" placeholder="輸入食譜名稱" id="rName" name="rName" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">簡介</label>
                    <textarea class="form-control border-secondary" placeholder="輸入簡介" id="description" name="description" rows="3"></textarea>
                </div>
                <div class="mb-3 d-flex align-items-center">
                    <!-- 烹煮時間 -->
                    <div class="d-flex flex-column" style="flex: 1;">
                        <label for="cooktime" class="form-label">烹煮時間（分鐘）</label>
                        <input type="number" class="form-control border-secondary" placeholder="輸入烹煮時間" id="cooktime" name="cooktime">
                    </div>

                    <!-- 垂直分隔線 -->
                    <div class="vr mx-3" style="height: 70px;"></div>

                    <!-- 難度等級 -->
                    <div class="d-flex flex-column" style="flex: 1;">
                        <label for="difficultyLevel" class="form-label">難度等級</label>
                        <select class="form-control border-secondary" id="difficultyLevel" name="difficultyLevel">
                            <option value="">選擇難度</option>
                            <option value="簡單">簡單</option>
                            <option value="中等">中等</option>
                            <option value="困難">困難</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="tags" class="form-label">標籤</label>
                    <input id="tags" name="tags" class="form-control border-secondary" placeholder="輸入標籤並按 Enter 或逗號" />
                </div>
                <div class="mb-3">
                    <label for="coverImage" class="form-label">封面圖片</label>
                    <input type="file" class="form-control border-secondary" id="coverImage" name="coverImage" accept="image/*">
                </div>
                <hr>
                <!-- 食材區段 -->
                <div class="mb-4">
                    <h4>食材清單</h4>
                    <div id="ingredients-container">
                        <div class="ingredient-group mb-3 p-3 border rounded">
                            <div class="row">
                                <div class="col-md-5 mb-2">
                                    <label class="form-label">食材名稱</label>
                                    <input list="ingredientList" class="form-control border-secondary" name="ingredients[name][]" placeholder="輸入或選擇食材名稱" required>
                                    <datalist id="ingredientList">
                                        <?php foreach ($allIngredients as $name): ?>
                                            <option value="<?php echo htmlspecialchars($name); ?>">
                                            <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">數量</label>
                                    <input type="number" class="form-control border-secondary" name="ingredients[quantity][]" min="0" placeholder="例如：2">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">單位</label>
                                    <select class="form-control border-secondary" name="ingredients[unit][]">
                                        <option value="">選擇單位</option>
                                        <option value="個">個</option>
                                        <option value="克">克</option>
                                        <option value="毫升">毫升</option>
                                        <option value="瓶">瓶</option>
                                        <option value="包">包</option>
                                        <option value="公斤">公斤</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2 d-flex align-items-end">
                                    <div class="d-flex gap-2 w-100">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeIngredient(this)">刪除</button>
                                        <button type="button" class="btn btn-success btn-sm" onclick="addIngredient()">新增</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <!-- 製作步驟區段 -->
                <div class="mb-4">
                    <h4>製作步驟</h4>
                    <div id="steps-container">
                        <div class="step-group mb-3 p-3 border rounded">
                            <label class="form-label">製作步驟 1</label>
                            <textarea class="form-control border-secondary" name="steps[description][]" rows="2"></textarea>
                            <div class="mt-2">
                                <label class="form-label">步驟圖片（可選）</label>
                                <input type="file" class="form-control border-secondary" name="steps[image][]" accept="image/*">
                            </div>
                            <div class="mt-2 d-flex gap-2">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeStep(this)">刪除步驟</button>
                                <button type="button" class="btn btn-success btn-sm" onclick="addStep()">新增步驟</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 驗證結果顯示區 -->
                <div id="validation-results" class="mb-4" style="display: none;">
                    <h5>AI 驗證結果</h5>
                    <div id="validation-content" class="border rounded p-3 bg-light">
                        <!-- 驗證結果將在這裡顯示 -->
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" id="validate-recipe" class="btn btn-warning">AI 驗證食譜</button>
                    <button type="submit" name="add_recipe" class="btn btn-primary">提交食譜</button>
                    <a href="recipe.php" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 食材相關功能
        function updateIngredientButtons() {
            const ingredientGroups = document.querySelectorAll('#ingredients-container .ingredient-group');
            ingredientGroups.forEach((group, index) => {
                const addBtn = group.querySelector('.btn-success');
                if (addBtn) {
                    addBtn.style.display = (index === ingredientGroups.length - 1) ? 'inline-block' : 'none';
                }
            });
        }

        function addIngredient() {
            const ingredientContainer = document.getElementById('ingredients-container');
            const ingredientDiv = document.createElement('div');
            ingredientDiv.className = 'ingredient-group mb-3 p-3 border rounded';
            ingredientDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-5 mb-2">
                        <label class="form-label">食材名稱</label>
                        <input list="ingredientList" class="form-control border-secondary" name="ingredients[name][]" placeholder="輸入或選擇食材名稱" required>
                        <datalist id="ingredientList">
                            <?php foreach ($allIngredients as $name): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">數量</label>
                        <input type="text" class="form-control border-secondary" name="ingredients[quantity][]" placeholder="例如：2">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">單位</label>
                        <select class="form-control border-secondary" name="ingredients[unit][]">
                            <option value="">選擇單位</option>
                            <option value="個">個</option>
                            <option value="克">克</option>
                            <option value="毫升">毫升</option>
                            <option value="瓶">瓶</option>
                            <option value="包">包</option>
                            <option value="公斤">公斤</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeIngredient(this)">刪除</button>
                            <button type="button" class="btn btn-success btn-sm" onclick="addIngredient()">新增</button>
                        </div>
                    </div>
                </div>
            `;
            ingredientContainer.appendChild(ingredientDiv);
            updateIngredientButtons();
        }

        function removeIngredient(btn) {
            const ingredientContainer = document.getElementById('ingredients-container');
            const allIngredients = ingredientContainer.querySelectorAll('.ingredient-group');
            if (allIngredients.length <= 1) {
                alert("至少需要一個食材！");
                return;
            }
            const ingredientToRemove = btn.closest('.ingredient-group');
            ingredientContainer.removeChild(ingredientToRemove);
            updateIngredientButtons();
        }

        // 步驟相關功能
        function updateStepLabels() {
            const stepGroups = document.querySelectorAll('#steps-container .step-group');
            stepGroups.forEach((group, index) => {
                const label = group.querySelector('label');
                label.textContent = `製作步驟 ${index + 1}`;
            });
        }

        function updateStepButtons() {
            const stepGroups = document.querySelectorAll('#steps-container .step-group');
            stepGroups.forEach((group, index) => {
                const addBtn = group.querySelector('.btn-success');
                if (addBtn) {
                    addBtn.style.display = (index === stepGroups.length - 1) ? 'inline-block' : 'none';
                }
            });
        }

        function addStep() {
            const stepContainer = document.getElementById('steps-container');
            const stepCount = stepContainer.querySelectorAll('.step-group').length;
            const stepDiv = document.createElement('div');
            stepDiv.className = 'step-group mb-3 p-3 border rounded';
            stepDiv.innerHTML = `
                <label class="form-label">製作步驟 ${stepCount + 1}</label>
                <textarea class="form-control border-secondary" name="steps[description][]" rows="2" required></textarea>
                <div class="mt-2">
                    <label class="form-label">步驟圖片（可選）</label>
                    <input type="file" class="form-control border-secondary" name="steps[image][]" accept="image/*">
                </div>
                <div class="mt-2 d-flex gap-2">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeStep(this)">刪除步驟</button>
                    <button type="button" class="btn btn-success btn-sm" onclick="addStep()">新增步驟</button>
                </div>
            `;
            stepContainer.appendChild(stepDiv);
            updateStepLabels();
            updateStepButtons();
        }

        function removeStep(btn) {
            const stepContainer = document.getElementById('steps-container');
            const allSteps = stepContainer.querySelectorAll('.step-group');
            if (allSteps.length <= 1) {
                alert("至少需要一個步驟！");
                return;
            }
            const stepToRemove = btn.closest('.step-group');
            stepContainer.removeChild(stepToRemove);
            updateStepLabels();
            updateStepButtons();
        }

        // 食譜驗證功能
        async function validateRecipe() {
            const validateBtn = document.getElementById('validate-recipe');
            const originalText = validateBtn.textContent;
            
            // 顯示載入狀態
            validateBtn.textContent = '驗證中...';
            validateBtn.disabled = true;
            
            try {
                // 收集表單資料
                const formData = collectFormData();
                
                // 發送驗證請求
                const response = await fetch('recipe_validation_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displayValidationResults(result.validation_result);
                } else {
                    showValidationError(result.error || '驗證失敗');
                }
                
            } catch (error) {
                console.error('驗證錯誤:', error);
                showValidationError('驗證過程中發生錯誤，請重試');
            } finally {
                // 恢復按鈕狀態
                validateBtn.textContent = originalText;
                validateBtn.disabled = false;
            }
        }
        
        // 收集表單資料
        function collectFormData() {
            const recipeName = document.getElementById('rName').value;
            const cookTime = document.getElementById('cooktime').value;
            const difficulty = document.getElementById('difficultyLevel').value;
            const description = document.getElementById('description').value;
            
            // 收集食材資料
            const ingredients = [];
            document.querySelectorAll('input[name="ingredients[name][]"]').forEach((input, index) => {
                const name = input.value.trim();
                const quantity = document.querySelectorAll('input[name="ingredients[quantity][]"]')[index]?.value.trim() || '';
                const unit = document.querySelectorAll('select[name="ingredients[unit][]"]')[index]?.value || '';
                
                if (name) {
                    ingredients.push(`${name} ${quantity} ${unit}`.trim());
                }
            });
            
            // 收集步驟資料
            const steps = [];
            document.querySelectorAll('textarea[name="steps[description][]"]').forEach((textarea, index) => {
                const step = textarea.value.trim();
                if (step) {
                    steps.push(`步驟${index + 1}: ${step}`);
                }
            });
            
            return {
                recipe_name: recipeName,
                cook_time: cookTime || 0,
                difficulty: difficulty || '未指定',
                description: description || '',
                ingredients: ingredients.join(', '),
                steps: steps.join('\n')
            };
        }
        
        // 顯示驗證結果
        function displayValidationResults(validation) {
            const resultsDiv = document.getElementById('validation-results');
            const contentDiv = document.getElementById('validation-content');
            
            let html = `
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="text-${getScoreColor(validation.score)}">${validation.score}/10</h3>
                            <small class="text-muted">合理性評分</small>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="mb-3">
                            <h6 class="text-danger">發現的問題：</h6>
                            <ul class="mb-0">
                                ${(validation.issues || []).map(issue => `<li>${issue}</li>`).join('')}
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-primary">修正建議：</h6>
                            <ul class="mb-0">
                                ${(validation.suggestions || []).map(suggestion => `<li>${suggestion}</li>`).join('')}
                            </ul>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-info">烹煮時間建議：</h6>
                                <p class="mb-2">${validation.cook_time_suggestion || '無特殊建議'}</p>
                                
                                <h6 class="text-info">難度等級建議：</h6>
                                <p class="mb-2">${validation.difficulty_suggestion || '無特殊建議'}</p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-info">食材搭配建議：</h6>
                                <ul class="mb-2">
                                    ${(validation.ingredient_suggestions || []).map(suggestion => `<li>${suggestion}</li>`).join('')}
                                </ul>
                                
                                <h6 class="text-info">步驟優化建議：</h6>
                                <ul class="mb-0">
                                    ${(validation.step_suggestions || []).map(suggestion => `<li>${suggestion}</li>`).join('')}
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            contentDiv.innerHTML = html;
            resultsDiv.style.display = 'block';
            
            // 滾動到驗證結果
            resultsDiv.scrollIntoView({ behavior: 'smooth' });
        }
        
        // 根據評分獲取顏色
        function getScoreColor(score) {
            if (score >= 8) return 'success';
            if (score >= 6) return 'warning';
            return 'danger';
        }
        
        // 顯示驗證錯誤
        function showValidationError(message) {
            const resultsDiv = document.getElementById('validation-results');
            const contentDiv = document.getElementById('validation-content');
            
            contentDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
            `;
            
            resultsDiv.style.display = 'block';
            resultsDiv.scrollIntoView({ behavior: 'smooth' });
        }
        
        // 初始化
        document.addEventListener("DOMContentLoaded", function() {
            updateStepButtons();
            updateIngredientButtons();
            
            // 綁定驗證按鈕事件
            document.getElementById('validate-recipe').addEventListener('click', validateRecipe);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <script>
        // 初始化 Tagify
        const input = document.querySelector('input[name=tags]');
        new Tagify(input, {
            maxTags: 10,
            maxCharacters: 20,
            dropdown: {
                enabled: 0
            }
        });
    </script>
</body>

</html>
