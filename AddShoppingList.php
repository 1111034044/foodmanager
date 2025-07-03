<?php
session_start();

// 檢查用戶是否登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

// 處理新購物清單的添加
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_shopping_list'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "CSRF 驗證失敗，請重試！";
        goto display_form;
    }

    $listName = trim($_POST['listName']);
    $ingredientNames = $_POST['ingredientNames'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $units = $_POST['units'] ?? [];
    $prices = $_POST['prices'] ?? [];

    // 驗證表單數據
    if (empty($listName)) {
        $error_message = "請輸入購物清單名稱！";
        goto display_form;
    }
    if (empty($ingredientNames) || !array_filter($ingredientNames)) {
        $error_message = "請至少輸入或選擇一個食材名稱！";
        goto display_form;
    }

    // 插入 ShoppingList 表
    // 插入 ShoppingList 表
    $stmt = $conn->prepare("INSERT INTO ShoppingList (uId, ListName, CreateDate, IsCompleted) VALUES (?, ?, CURDATE(), 0)");
    if ($stmt === false) {
        $error_message = "SQL 準備失敗 (ShoppingList): " . $conn->error;
        goto display_form;
    }
    $stmt->bind_param("is", $uId, $listName);
    if (!$stmt->execute()) {
        $error_message = "插入 ShoppingList 失敗: " . $stmt->error;
        $stmt->close();
        goto display_form;
    }
    $shoppingId = $conn->insert_id;
    $stmt->close();
    
    // 添加通知
    $role = $_SESSION['user_role'] ?? '未指定';
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, content, item_id, item_name, role) VALUES (?, 'shopping', '新增了購物清單', ?, ?, ?)");
    $stmt->bind_param("iiss", $uId, $shoppingId, $listName, $role);
    $stmt->execute();
    $stmt->close();
    
    // 批量插入 ShoppingItem 表
    $stmt = $conn->prepare("INSERT INTO ShoppingItem (ShoppingId, IngredientName, Quantity, Price, Unit) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        $error_message = "SQL 準備失敗 (ShoppingItem): " . $conn->error;
        goto display_form;
    }

    $success = true;
    $validUnits = ['個', '克', '毫升', '瓶', '包', '公斤', '']; // 允許空值
    for ($i = 0; $i < count($ingredientNames); $i++) {
        if (empty($ingredientNames[$i])) continue;

        $ingredientName = trim($ingredientNames[$i]);
        if (empty($ingredientName)) {
            $error_message = "食材名稱不得為空！";
            $success = false;
            break;
        }

        $quantity = !empty($quantities[$i]) ? (int)$quantities[$i] : NULL;
        $price = !empty($prices[$i]) ? (int)$prices[$i] : NULL;
        $unit = !empty($units[$i]) && in_array($units[$i], $validUnits) ? $units[$i] : NULL;

        $stmt->bind_param("isiss", $shoppingId, $ingredientName, $quantity, $price, $unit);
        if (!$stmt->execute()) {
            $error_message = "插入 ShoppingItem 失敗: " . $stmt->error;
            $success = false;
            break;
        }
    }
    $stmt->close();

    if ($success) {
        header("Location: ShoppingList.php");
        exit();
    }
}

// 查詢 nutrition_facts 表的食材名稱
$stmt = $conn->prepare("SELECT DISTINCT sample_name FROM nutrition_facts");
$stmt->execute();
$result = $stmt->get_result();

$allIngredients = [];
while ($row = $result->fetch_assoc()) {
    $allIngredients[] = $row['sample_name'];
}
$stmt->close();

$conn->close();

display_form:
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增購物清單</title>
    <link rel="stylesheet" href="css/boot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #e9f1f6;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin-top: 50px;
        }
        .form-card {
            background-color: #ffffff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .btn-custom {
            background-color: #007bff;
            color: #fff;
            border-radius: 8px;
        }
        .btn-custom:hover {
            background-color: #0056b3;
        }
        .btn-secondary, .btn-danger {
            border-radius: 8px;
        }
        .ingredient-row {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <div class="form-card">
            <h2 class="text-center mb-4">新增購物清單</h2>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>
            <form method="POST" id="addShoppingListForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-3">
                    <label for="listName" class="form-label">購物清單名稱</label>
                    <input type="text" class="form-control" name="listName" placeholder="輸入購物清單名稱" required>
                </div>
                <div id="ingredientsContainer">
                    <div class="input-group ingredient-row">
                        <input list="ingredientList" class="form-control" name="ingredientNames[]" placeholder="輸入或選擇食材名稱" required>
                        <datalist id="ingredientList">
                            <?php foreach ($allIngredients as $name): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <input type="number" class="form-control" name="quantities[]" placeholder="輸入數量">
                        <select class="form-control" name="units[]">
                            <option value="">選擇單位</option>
                            <option value="個">個</option>
                            <option value="克">克</option>
                            <option value="毫升">毫升</option>
                            <option value="瓶">瓶</option>
                            <option value="包">包</option>
                            <option value="公斤">公斤</option>
                        </select>
                        <input type="number" class="form-control" name="prices[]" placeholder="輸入價格">
                        <button type="button" class="btn btn-danger remove-ingredient"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <div class="mb-3">
                    <button type="button" class="btn btn-custom" id="addIngredientBtn">+ 添加更多食材</button>
                </div>
                <div class="text-center">
                    <button type="submit" name="add_shopping_list" class="btn btn-custom">新增清單</button>
                    <a href="ShoppingList.php" class="btn btn-secondary">返回購物清單</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('addIngredientBtn').addEventListener('click', function() {
            const container = document.getElementById('ingredientsContainer');
            const newRow = document.createElement('div');
            newRow.className = 'input-group ingredient-row';
            newRow.innerHTML = `
                <input list="ingredientList" class="form-control" name="ingredientNames[]" placeholder="輸入或選擇食材名稱" required>
                <datalist id="ingredientList">
                    <?php foreach ($allIngredients as $name): ?>
                        <option value="<?php echo htmlspecialchars($name); ?>">
                    <?php endforeach; ?>
                </datalist>
                <input type="number" class="form-control" name="quantities[]" placeholder="輸入數量">
                <select class="form-control" name="units[]">
                    <option value="">選擇單位</option>
                    <option value="個">個</option>
                    <option value="克">克</option>
                    <option value="毫升">毫升</option>
                    <option value="瓶">瓶</option>
                    <option value="包">包</option>
                    <option value="公斤">公斤</option>
                </select>
                <input type="number" class="form-control" name="prices[]" placeholder="輸入價格">
                <button type="button" class="btn btn-danger remove-ingredient"><i class="bi bi-trash"></i></button>
            `;
            container.appendChild(newRow);
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-ingredient')) {
                const rows = document.querySelectorAll('.ingredient-row');
                if (rows.length > 1) {
                    e.target.closest('.ingredient-row').remove();
                } else {
                    alert('至少需要保留一個食材項目！');
                }
            }
        });
    </script>
</body>
</html>