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
$conn->set_charset("utf8mb4"); // 設定資料庫連線字元集為 utf8mb4

// 從 session 中獲取 uId
$uId = $_SESSION['user_id'];
$recipe_id = null;

// --- 編輯模式：強制 RecipeId 並載入資料 ---
if (isset($_GET['RecipeId']) && is_numeric($_GET['RecipeId'])) {
    $recipe_id = (int) $_GET['RecipeId'];
} else {
    // 如果是 POST 請求，且 RecipeId 在 POST 資料中（用於表單提交後保持 RecipeId）
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['RecipeId']) && is_numeric($_POST['RecipeId'])) {
        $recipe_id = (int) $_POST['RecipeId'];
    } else {
        header("Location: recipe.php?error=norecipeid_edit");
        exit();
    }
}

$existing_recipe = null;
$existing_ingredients = [];
$existing_steps = [];
$existing_tags_str = '';

// 查詢食譜基本資料，並確保食譜屬於當前用戶
$stmt_recipe = $conn->prepare("SELECT * FROM Recipe WHERE RecipeId = ? AND uId = ?");
$stmt_recipe->bind_param("ii", $recipe_id, $uId);
$stmt_recipe->execute();
$result_recipe = $stmt_recipe->get_result();
if ($result_recipe->num_rows === 1) {
    $existing_recipe = $result_recipe->fetch_assoc();
} else {
    header("Location: recipe.php?error=recipenotfound_or_unauthorized_edit");
    exit();
}
$stmt_recipe->close();

// 查詢現有食材
$stmt_ing = $conn->prepare("SELECT IngredientName, Quantity, Unit FROM recipeingredient WHERE RecipeId = ? ORDER BY IngredientName");
if ($stmt_ing === false) {
    die("SQL prepare failed for ingredients: (" . $conn->errno . ") " . $conn->error);
}
$stmt_ing->bind_param("i", $recipe_id);
$stmt_ing->execute();
$result_ing = $stmt_ing->get_result();
while ($row_ing = $result_ing->fetch_assoc()) {
    $existing_ingredients[] = $row_ing;
}
$stmt_ing->close();

// 查詢現有步驟
$stmt_steps_db = $conn->prepare("SELECT StepOrder, StepDescription, StepImage FROM RecipeSteps WHERE RecipeId = ? ORDER BY StepOrder");
$stmt_steps_db->bind_param("i", $recipe_id);
$stmt_steps_db->execute();
$result_steps_db = $stmt_steps_db->get_result();
while ($row_steps_db = $result_steps_db->fetch_assoc()) {
    $existing_steps[] = $row_steps_db;
}
$stmt_steps_db->close();

// 查詢現有標籤
$stmt_tags_db = $conn->prepare("SELECT Tag FROM RecipeTags WHERE RecipeId = ?");
$stmt_tags_db->bind_param("i", $recipe_id);
$stmt_tags_db->execute();
$result_tags_db = $stmt_tags_db->get_result();
$tags_array = [];
while ($row_tags_db = $result_tags_db->fetch_assoc()) {
    $tags_array[] = $row_tags_db['Tag'];
}
$existing_tags_str = implode(',', $tags_array);
$stmt_tags_db->close();
// --- 結束編輯模式資料載入 ---

// 查詢 nutrition_facts 表的食材名稱 (用於食材 datalist)
$stmt_all_ing = $conn->prepare("SELECT DISTINCT sample_name FROM nutrition_facts");
$stmt_all_ing->execute();
$result_all_ing = $stmt_all_ing->get_result();

$allIngredients = [];
while ($row_all_ing = $result_all_ing->fetch_assoc()) {
    $allIngredients[] = $row_all_ing['sample_name'];
}
$stmt_all_ing->close();

// 處理食譜的更新
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_recipe'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "CSRF 驗證失敗，請重試！";
        goto display_form;
    }

    // 確認提交的 RecipeId 與當前頁面的 recipe_id 一致
    if (!isset($_POST['RecipeId']) || (int) $_POST['RecipeId'] !== $recipe_id) {
        $error_message = "食譜 ID 不匹配，更新操作被中止。";
        goto display_form;
    }

    $rName = trim($_POST['rName']);
    $cooktime = $_POST['cooktime'] ? (int) $_POST['cooktime'] : NULL;
    $difficultyLevel = $_POST['difficultyLevel'] ?: NULL;
    $description = $_POST['description'] ?: NULL;

    // --- 封面圖片處理 --- Start ---
    $finalCoverImage = NULL; // 初始化最終用於資料庫的圖片路徑變數
    $currentDbCoverImage = $existing_recipe['CoverImage']; // 獲取當前資料庫中儲存的圖片路徑

    // 情況 1: 有新圖片上傳
    if (isset($_FILES['coverImage']) && $_FILES['coverImage']['error'] == UPLOAD_ERR_OK) {
        $targetDir = "Uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $fileName = basename($_FILES['coverImage']['name']);
        $targetFile = $targetDir . time() . "_" . $fileName; // 為新檔案產生唯一路徑

        if (move_uploaded_file($_FILES['coverImage']['tmp_name'], $targetFile)) {
            // 新圖片上傳成功
            // 如果之前有封面圖片，且與新圖片不同，則刪除舊的實體檔案
            if ($currentDbCoverImage && file_exists($currentDbCoverImage) && $currentDbCoverImage !== $targetFile) {
                unlink($currentDbCoverImage);
            }
            $finalCoverImage = $targetFile; // 更新資料庫欄位為新圖片路徑
        } else {
            // 新圖片上傳失敗
            $error_message = "封面圖片上傳失敗，請檢查 uploads/ 資料夾權限！";
            // 保留現有的圖片，因為上傳操作未成功
            $finalCoverImage = $currentDbCoverImage;
            goto display_form; // 中斷後續操作，顯示錯誤訊息
        }
    }
    // 情況 2: 沒有新圖片上傳，但使用者請求刪除現有圖片
    elseif (isset($_POST['remove_cover_image']) && $_POST['remove_cover_image'] == '1') {
        // 如果確實有舊圖片存在於伺服器上，則刪除它
        if ($currentDbCoverImage && file_exists($currentDbCoverImage)) {
            unlink($currentDbCoverImage);
        }
        $finalCoverImage = NULL; // 將資料庫欄位設為 NULL，表示沒有圖片
    }
    // 情況 3: 沒有新圖片上傳，也沒有請求刪除 (即不對封面圖片做任何更改)
    else {
        $finalCoverImage = $currentDbCoverImage; // 保持資料庫欄位為原有的圖片路徑
    }
    // --- 封面圖片處理 --- End ---

    if (empty($rName)) {
        $error_message = "食譜名稱不得為空！";
        goto display_form;
    }

    // 更新 Recipe 資料
    $stmt_update_recipe = $conn->prepare("
        UPDATE Recipe SET rName = ?, cooktime = ?, DifficultyLevel = ?, Description = ?, CoverImage = ?
        WHERE RecipeId = ? AND uId = ?
    ");
    $stmt_update_recipe->bind_param("sisssii", $rName, $cooktime, $difficultyLevel, $description, $finalCoverImage, $recipe_id, $uId);

    if (!$stmt_update_recipe->execute()) {
        $error_message = "更新食譜基本資料失敗: " . $stmt_update_recipe->error;
        $stmt_update_recipe->close();
        goto display_form;
    }
    $stmt_update_recipe->close();

    // 更新標籤（RecipeTags）- 先刪除舊的，再插入新的
    $stmtDelTags = $conn->prepare("DELETE FROM RecipeTags WHERE RecipeId = ?");
    $stmtDelTags->bind_param("i", $recipe_id);
    $stmtDelTags->execute();
    $stmtDelTags->close();

    if (!empty($_POST['tags'])) {
        $tags = explode(',', $_POST['tags']);
        $stmtTag = $conn->prepare("INSERT INTO RecipeTags (RecipeId, Tag) VALUES (?, ?)");
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if ($tag !== '') {
                $stmtTag->bind_param("is", $recipe_id, $tag);
                if (!$stmtTag->execute()) {
                    $error_message = "更新標籤失敗: " . $stmtTag->error;
                    goto display_form;
                }
            }
        }
        $stmtTag->close();
    }

    // 更新食材（RecipeIngredients）- 先刪除舊的，再插入新的
    $stmtDelIng = $conn->prepare("DELETE FROM recipeingredient WHERE RecipeId = ?");
    $stmtDelIng->bind_param("i", $recipe_id);
    $stmtDelIng->execute();
    $stmtDelIng->close();

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

            $stmtIngredient->bind_param("isss", $recipe_id, $ingredientName, $quantity, $unit);
            if (!$stmtIngredient->execute()) {
                $error_message = "更新食材失敗: " . $stmtIngredient->error;
                goto display_form;
            }
        }
        $stmtIngredient->close();
    } else {
        $error_message = "請至少提供一個有效食材！";
        goto display_form;
    }

    // 更新 RecipeSteps（步驟）- 先刪除舊步驟圖片和步驟，再插入新步驟
    // 1. 獲取所有舊的步驟圖片檔案路徑
    $old_step_image_files_from_db = [];
    $stmt_old_step_images = $conn->prepare("SELECT StepImage FROM RecipeSteps WHERE RecipeId = ? AND StepImage IS NOT NULL AND StepImage != ''");
    $stmt_old_step_images->bind_param("i", $recipe_id);
    $stmt_old_step_images->execute();
    $result_old_step_images = $stmt_old_step_images->get_result();
    while ($row_img = $result_old_step_images->fetch_assoc()) {
        $old_step_image_files_from_db[] = $row_img['StepImage'];
    }
    $stmt_old_step_images->close();

    // 2. 從資料庫刪除所有舊步驟
    $stmtDelSteps = $conn->prepare("DELETE FROM RecipeSteps WHERE RecipeId = ?");
    $stmtDelSteps->bind_param("i", $recipe_id);
    $stmtDelSteps->execute();
    $stmtDelSteps->close();

    // 3. 處理提交的步驟並收集最終要保存的圖片路徑
    $final_step_image_paths_for_db = []; // 將在此收集實際寫入 DB 的圖片路徑

    if (isset($_POST['steps']['description']) && !empty(array_filter($_POST['steps']['description']))) {
        $stmtStepInsert = $conn->prepare("
            INSERT INTO RecipeSteps (RecipeId, StepOrder, StepDescription, StepImage)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($_POST['steps']['description'] as $index => $stepDescription) {
            $stepDescription = trim($stepDescription);
            if ($stepDescription === '') {
                continue;
            }

            $stepImageForDb = NULL; // 初始化此步驟的圖片路徑

            // 檢查是否有新圖片上傳
            if (isset($_FILES['steps']['name']['image'][$index]) && $_FILES['steps']['error']['image'][$index] == 0) {
                $stepDir = "Uploads/steps/";
                if (!is_dir($stepDir)) {
                    mkdir($stepDir, 0755, true);
                }
                $stepFileName = basename($_FILES['steps']['name']['image'][$index]);
                // 確保檔名唯一性
                $stepFilePath = $stepDir . time() . "_" . uniqid() . "_" . $stepFileName;

                if (move_uploaded_file($_FILES['steps']['tmp_name']['image'][$index], $stepFilePath)) {
                    $stepImageForDb = $stepFilePath;
                    // 如果這個索引之前有一個 existing_image，那個舊檔案需要被清理 (下一步驟處理)
                    if (isset($_POST['steps']['existing_image'][$index]) && !empty($_POST['steps']['existing_image'][$index])) {
                        // 標記此 existing_image，如果它在 $old_step_image_files_from_db 中，它將被刪除，因為已被新圖片取代
                    }
                } else {
                    $error_message = "步驟 " . ($index + 1) . " 圖片上傳失敗，請檢查 uploads/steps/ 資料夾權限！";
                    goto display_form;
                }
            } elseif (isset($_POST['steps']['existing_image'][$index]) && !empty($_POST['steps']['existing_image'][$index])) {
                // 如果沒有新圖片上傳，但表單中提交了該步驟原有的圖片路徑 (steps[existing_image][index] 有值)
                // 這表示使用者未對此步驟的圖片進行修改（沒有上傳新檔，也沒有清空）
                // 因此，我們直接使用這個從表單傳回的、代表「編輯前狀態」的圖片路徑。
                $stepImageForDb = $_POST['steps']['existing_image'][$index];
            }

            if ($stepImageForDb) {
                $final_step_image_paths_for_db[] = $stepImageForDb;
            }

            $stepOrder = $index + 1;
            $stmtStepInsert->bind_param("iiss", $recipe_id, $stepOrder, $stepDescription, $stepImageForDb);
            if (!$stmtStepInsert->execute()) {
                $error_message = "更新步驟 {$stepOrder} 失敗: " . $stmtStepInsert->error;
                goto display_form;
            }
        }
        $stmtStepInsert->close();
    } else {
        $error_message = "請至少提供一個有效步驟！";
        goto display_form;
    }

    // 4. 清理不再被引用的舊步驟圖片檔案
    foreach ($old_step_image_files_from_db as $old_file_path) {
        if (file_exists($old_file_path) && !in_array($old_file_path, $final_step_image_paths_for_db)) {
            // 檢查此 $old_file_path 是否是某個被新圖片取代的 existing_image
            $was_replaced = false;
            if (isset($_POST['steps']['existing_image'])) {
                foreach ($_POST['steps']['existing_image'] as $idx => $existing_path) {
                    if ($existing_path === $old_file_path && isset($_FILES['steps']['name']['image'][$idx]) && $_FILES['steps']['error']['image'][$idx] == 0) {
                        $was_replaced = true;
                        break;
                    }
                }
            }
            if (!$was_replaced) { // 只有當它不是被直接替換的舊圖時才刪除 (因為被替換的已經在新圖上傳成功後隱含處理)
                unlink($old_file_path);
            } else if ($was_replaced && file_exists($old_file_path)) { // 如果是被替換的，也刪除它
                unlink($old_file_path);
            }
        }
    }


    header("Location: recipe_detail.php?RecipeId=" . $recipe_id . "&status=updated_edit"); // Redirect after successful update
    exit();
}

// $conn->close(); // Defer closing connection until end of script or before display_form

display_form:
// If error, $existing_recipe etc. might not be fully populated from DB if error happened before load.
// But for edit page, we assume $recipe_id is valid and $existing_recipe is loaded if we reach here without POST error.
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯食譜</title>
    <link rel="stylesheet" href="css/boot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2>編輯食譜</h2>
        <div class="bg-white p-4 rounded shadow-sm">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data"
                action="edit_recipe.php?RecipeId=<?php echo $recipe_id; // Changed from recipe_id to RecipeId ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="RecipeId"
                    value="<?php echo $recipe_id; // Changed from recipe_id to RecipeId, Crucial for POST context ?>">

                <div class="mb-3">
                    <label for="rName" class="form-label">食譜名稱</label>
                    <input type="text" class="form-control" id="rName" name="rName" required
                        value="<?php echo htmlspecialchars($existing_recipe['rName'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">簡介</label>
                    <textarea class="form-control" id="description" name="description"
                        rows="3"><?php echo htmlspecialchars($existing_recipe['Description'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3 d-flex align-items-center">
                    <div class="d-flex flex-column" style="flex: 1;">
                        <label for="cooktime" class="form-label">烹煮時間（分鐘）</label>
                        <input type="number" class="form-control" id="cooktime" name="cooktime"
                            value="<?php echo htmlspecialchars($existing_recipe['cooktime'] ?? ''); ?>">
                    </div>
                    <div class="vr mx-3" style="height: 70px;"></div>
                    <div class="d-flex flex-column" style="flex: 1;">
                        <label for="difficultyLevel" class="form-label">難度等級</label>
                        <select class="form-control" id="difficultyLevel" name="difficultyLevel">
                            <option value="">選擇難度</option>
                            <option value="簡單" <?php echo (($existing_recipe['DifficultyLevel'] ?? '') === '簡單') ? 'selected' : ''; ?>>簡單</option>
                            <option value="中等" <?php echo (($existing_recipe['DifficultyLevel'] ?? '') === '中等') ? 'selected' : ''; ?>>中等</option>
                            <option value="困難" <?php echo (($existing_recipe['DifficultyLevel'] ?? '') === '困難') ? 'selected' : ''; ?>>困難</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="tags" class="form-label">標籤</label>
                    <input id="tags" name="tags" class="form-control" placeholder="輸入標籤並按 Enter 或逗號"
                        value="<?php echo htmlspecialchars($existing_tags_str); ?>">
                </div>
                <div class="mb-3">
                    <label for="coverImage" class="form-label">封面圖片</label>
                    <input type="file" class="form-control" id="coverImage" name="coverImage" accept="image/*">
                    <?php if (!empty($existing_recipe['CoverImage']) && file_exists($existing_recipe['CoverImage'])): ?>
                        <div class="mt-2">
                            <p>目前封面:</p>
                            <img src="<?php echo htmlspecialchars($existing_recipe['CoverImage']); ?>" alt="目前封面"
                                style="max-width: 200px; max-height: 200px; display: block; margin-bottom: 10px;">
                            <label><input type="checkbox" name="remove_cover_image" value="1"> 刪除目前封面圖片</label>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 食材區段 -->
                <div class="mb-4">
                    <h4>食材清單</h4>
                    <div id="ingredients-container">
                        <?php if (!empty($existing_ingredients)): ?>
                            <?php foreach ($existing_ingredients as $index_ing => $ingredient): ?>
                                <div class="ingredient-group mb-3 p-3 border rounded">
                                    <div class="row">
                                        <div class="col-md-5 mb-2">
                                            <label class="form-label">食材名稱</label>
                                            <input list="ingredientList_<?php echo $index_ing; ?>" class="form-control"
                                                name="ingredients[name][]" placeholder="輸入或選擇食材名稱" required
                                                value="<?php echo htmlspecialchars($ingredient['IngredientName']); ?>">
                                            <datalist id="ingredientList_<?php echo $index_ing; ?>">
                                                <?php foreach ($allIngredients as $name): ?>
                                                    <option value="<?php echo htmlspecialchars($name); ?>">
                                                    <?php endforeach; ?>
                                            </datalist>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">數量</label>
                                            <input type="text" class="form-control" name="ingredients[quantity][]"
                                                placeholder="例如：2"
                                                value="<?php echo htmlspecialchars($ingredient['Quantity']); ?>">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">單位</label>
                                            <select class="form-control" name="ingredients[unit][]">
                                                <option value="">選擇單位</option>
                                                <option value="個" <?php echo ($ingredient['Unit'] === '個') ? 'selected' : ''; ?>>個
                                                </option>
                                                <option value="克" <?php echo ($ingredient['Unit'] === '克') ? 'selected' : ''; ?>>克
                                                </option>
                                                <option value="毫升" <?php echo ($ingredient['Unit'] === '毫升') ? 'selected' : ''; ?>>毫升</option>
                                                <option value="瓶" <?php echo ($ingredient['Unit'] === '瓶') ? 'selected' : ''; ?>>瓶
                                                </option>
                                                <option value="包" <?php echo ($ingredient['Unit'] === '包') ? 'selected' : ''; ?>>包
                                                </option>
                                                <option value="公斤" <?php echo ($ingredient['Unit'] === '公斤') ? 'selected' : ''; ?>>公斤</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-2 d-flex align-items-end">
                                            <div class="d-flex gap-2 w-100">
                                                <button type="button" class="btn btn-danger btn-sm"
                                                    onclick="removeIngredient(this)">刪除</button>
                                                <button type="button" class="btn btn-success btn-sm" onclick="addIngredient()"
                                                    style="<?php echo ($index_ing === count($existing_ingredients) - 1) ? '' : 'display:none;'; ?>">新增</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: // 如果沒有現有食材，則顯示一個空的 ?>
                            <div class="ingredient-group mb-3 p-3 border rounded">
                                <div class="row">
                                    <div class="col-md-5 mb-2">
                                        <label class="form-label">食材名稱</label>
                                        <input list="ingredientList_new_0" class="form-control" name="ingredients[name][]"
                                            placeholder="輸入或選擇食材名稱" required>
                                        <datalist id="ingredientList_new_0">
                                            <?php foreach ($allIngredients as $name): ?>
                                                <option value="<?php echo htmlspecialchars($name); ?>">
                                                <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label">數量</label>
                                        <input type="text" class="form-control" name="ingredients[quantity][]"
                                            placeholder="例如：2">
                                    </div>
                                    <div class="col-md-2 mb-2">
                                        <label class="form-label">單位</label>
                                        <select class="form-control" name="ingredients[unit][]">
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
                                            <button type="button" class="btn btn-danger btn-sm"
                                                onclick="removeIngredient(this)" disabled>刪除</button>
                                            <button type="button" class="btn btn-success btn-sm"
                                                onclick="addIngredient()">新增</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- 製作步驟區段 -->
                <div class="mb-4">
                    <h4>製作步驟</h4>
                    <div id="steps-container">
                        <?php if (!empty($existing_steps)): ?>
                            <?php foreach ($existing_steps as $index_step => $step): ?>
                                <div class="step-group mb-3 p-3 border rounded">
                                    <label class="form-label step-label">製作步驟 <?php echo $index_step + 1; ?></label>
                                    <textarea class="form-control" name="steps[description][]" rows="2"
                                        required><?php echo htmlspecialchars($step['StepDescription']); ?></textarea>
                                    <input type="hidden" name="steps[existing_image][<?php echo $index_step; ?>]"
                                        value="<?php echo htmlspecialchars($step['StepImage'] ?? ''); ?>">
                                    <div class="mt-2">
                                        <label class="form-label">步驟圖片（可選，上傳新圖片將取代目前圖片）</label>
                                        <input type="file" class="form-control" name="steps[image][<?php echo $index_step; ?>]"
                                            accept="image/*"> <!-- Name attribute needs index for specific step -->
                                        <?php if (!empty($step['StepImage']) && file_exists($step['StepImage'])): ?>
                                            <div class="mt-1">
                                                <p>目前圖片: <a href="<?php echo htmlspecialchars($step['StepImage']); ?>"
                                                        target="_blank">查看</a></p>
                                                <img src="<?php echo htmlspecialchars($step['StepImage']); ?>" alt="步驟圖片"
                                                    style="max-width: 100px; max-height: 100px; display: block; margin-bottom: 5px;">
                                                <!-- No explicit delete for individual step image, new upload replaces -->
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 d-flex gap-2">
                                        <button type="button" class="btn btn-danger btn-sm"
                                            onclick="removeStep(this)">刪除步驟</button>
                                        <button type="button" class="btn btn-success btn-sm" onclick="addStep()"
                                            style="<?php echo ($index_step === count($existing_steps) - 1) ? '' : 'display:none;'; ?>">新增步驟</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: // 如果沒有現有步驟，則顯示一個空的 ?>
                            <div class="step-group mb-3 p-3 border rounded">
                                <label class="form-label step-label">製作步驟 1</label>
                                <textarea class="form-control" name="steps[description][]" rows="2" required></textarea>
                                <input type="hidden" name="steps[existing_image][0]" value="">
                                <div class="mt-2">
                                    <label class="form-label">步驟圖片（可選）</label>
                                    <input type="file" class="form-control" name="steps[image][0]" accept="image/*">
                                </div>
                                <div class="mt-2 d-flex gap-2">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeStep(this)"
                                        disabled>刪除步驟</button>
                                    <button type="button" class="btn btn-success btn-sm" onclick="addStep()">新增步驟</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="update_recipe" class="btn btn-primary">更新食譜</button>
                    <a href="recipe.php" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
    </div>
    <?php $conn->close(); // Close connection here before script tags ?>
    <script>
        // 食材相關功能
        function updateIngredientButtons() {
            const ingredientGroups = document.querySelectorAll('#ingredients-container .ingredient-group');
            ingredientGroups.forEach((group, index) => {
                const addBtn = group.querySelector('.btn-success');
                const removeBtn = group.querySelector('.btn-danger');
                if (addBtn) {
                    addBtn.style.display = (index === ingredientGroups.length - 1) ? 'inline-block' : 'none';
                }
                if (removeBtn) {
                    removeBtn.disabled = (ingredientGroups.length <= 1);
                }
            });
        }

        function addIngredient() {
            const ingredientContainer = document.getElementById('ingredients-container');
            const newIndex = ingredientContainer.querySelectorAll('.ingredient-group').length;
            const ingredientDiv = document.createElement('div');
            ingredientDiv.className = 'ingredient-group mb-3 p-3 border rounded';

            // Get the datalist HTML from the first existing datalist if available, or an empty string.
            let datalistHTML = '';
            const firstDatalist = document.querySelector('#ingredients-container datalist');
            if (firstDatalist) {
                datalistHTML = firstDatalist.outerHTML;
            } else {
                // Fallback if no datalist exists (e.g., if allIngredients is empty or initial form is different)
                // This case should ideally not happen if $allIngredients is populated.
                // Create a new datalist with a unique ID for the new input.
                datalistHTML = `<datalist id="ingredientList_new_\${newIndex}"></datalist>`;
                // If $allIngredients was passed to JS, you could populate it here.
            }
            // Ensure the new input uses a unique list attribute for its datalist.
            const newDatalistId = `ingredientList_dyn_\${newIndex}`;
            const inputListAttribute = `list="\${newDatalistId}"`;
            // Modify datalistHTML to use the new unique ID.
            if (firstDatalist) { // only if we copied an existing datalist
                datalistHTML = datalistHTML.replace(/id=".*?"/, `id="\${newDatalistId}"`);
            }


            ingredientDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-5 mb-2">
                        <label class="form-label">食材名稱</label>
                        <input ${inputListAttribute} class="form-control" name="ingredients[name][]" placeholder="輸入或選擇食材名稱" required>
                        ${datalistHTML}
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">數量</label>
                        <input type="text" class="form-control" name="ingredients[quantity][]" placeholder="例如：2">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">單位</label>
                        <select class="form-control" name="ingredients[unit][]">
                            <option value="">選擇單位</option>
                            <option value="個">個</option><option value="克">克</option><option value="毫升">毫升</option><option value="瓶">瓶</option><option value="包">包</option><option value="公斤">公斤</option>
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
            if (ingredientContainer.querySelectorAll('.ingredient-group').length <= 1) {
                alert("至少需要一個食材！");
                return;
            }
            const ingredientToRemove = btn.closest('.ingredient-group');
            ingredientContainer.removeChild(ingredientToRemove);
            updateIngredientButtons();
        }

        // 步驟相關功能
        function updateStepControls() {
            const stepGroups = document.querySelectorAll('#steps-container .step-group');
            stepGroups.forEach((group, index) => {
                const label = group.querySelector('.step-label');
                if (label) {
                    label.textContent = `製作步驟 ${index + 1}`;
                }
                const addBtn = group.querySelector('.btn-success');
                const removeBtn = group.querySelector('.btn-danger');

                const descriptionTextarea = group.querySelector('textarea[name^="steps[description]"]');
                if (descriptionTextarea) descriptionTextarea.name = `steps[description][${index}]`;

                const fileInput = group.querySelector('input[type="file"]');
                if (fileInput) fileInput.name = `steps[image][${index}]`;

                const hiddenInput = group.querySelector('input[type="hidden"][name^="steps[existing_image]"]');
                if (hiddenInput) hiddenInput.name = `steps[existing_image][${index}]`;

                if (addBtn) {
                    addBtn.style.display = (index === stepGroups.length - 1) ? 'inline-block' : 'none';
                }
                if (removeBtn) {
                    removeBtn.disabled = (stepGroups.length <= 1);
                }
            });
        }

        function addStep() {
            const stepContainer = document.getElementById('steps-container');
            const newIndex = stepContainer.querySelectorAll('.step-group').length;
            const stepDiv = document.createElement('div');
            stepDiv.className = 'step-group mb-3 p-3 border rounded';
            stepDiv.innerHTML = `
                <label class="form-label step-label">製作步驟 ${newIndex + 1}</label>
                <textarea class="form-control" name="steps[description][${newIndex}]" rows="2" required></textarea>
                <input type="hidden" name="steps[existing_image][${newIndex}]" value="">
                <div class="mt-2">
                    <label class="form-label">步驟圖片（可選）</label>
                    <input type="file" class="form-control" name="steps[image][${newIndex}]" accept="image/*">
                </div>
                <div class="mt-2 d-flex gap-2">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeStep(this)">刪除步驟</button>
                    <button type="button" class="btn btn-success btn-sm" onclick="addStep()">新增步驟</button>
                </div>
            `;
            stepContainer.appendChild(stepDiv);
            updateStepControls();
        }

        function removeStep(btn) {
            const stepContainer = document.getElementById('steps-container');
            if (stepContainer.querySelectorAll('.step-group').length <= 1) {
                alert("至少需要一個步驟！");
                return;
            }
            const stepToRemove = btn.closest('.step-group');
            stepContainer.removeChild(stepToRemove);
            updateStepControls();
        }

        // 初始化
        document.addEventListener("DOMContentLoaded", function () {
            updateIngredientButtons();
            updateStepControls();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <script>
        // 初始化 Tagify
        const input = document.querySelector('input[name=tags]');
        if (input) { // Ensure input exists before initializing
            new Tagify(input, {
                maxTags: 10,
                maxCharacters: 20,
                dropdown: {
                    enabled: 0
                }
            });
        }
    </script>
</body>

</html>