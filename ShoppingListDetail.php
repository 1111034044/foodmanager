<?php
session_start();

// 檢查用戶是否已登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 生成 CSRF 令牌
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 檢查是否有 ShoppingId 參數
if (!isset($_GET['ShoppingId'])) {
    header("Location: ShoppingList.php");
    exit();
}

$shoppingId = (int)$_GET['ShoppingId'];

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 從 session 中獲取 uId
$uId = $_SESSION['user_id'];

// 檢查該購物清單是否屬於當前用戶
$stmt = $conn->prepare("SELECT ShoppingId, ListName, CreateDate, IsCompleted FROM ShoppingList WHERE ShoppingId = ? AND uId = ?");
$stmt->bind_param("ii", $shoppingId, $uId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: ShoppingList.php");
    exit();
}

$list = $result->fetch_assoc();
$stmt->close();

// 只有未完成的清單才允許新增和編輯
$isEditable = !$list['IsCompleted'];

// 處理新增購物清單項目（僅未完成清單）
if ($isEditable && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_shopping_items'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "CSRF 驗證失敗，請重試！";
        goto display_form;
    }

    $ingredientNames = $_POST['ingredientNames'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $units = $_POST['units'] ?? [];
    $prices = $_POST['prices'] ?? [];

    if (empty($ingredientNames) || !array_filter($ingredientNames)) {
        $error_message = "請至少輸入或選擇一個食材名稱！";
        goto display_form;
    }

    $stmt = $conn->prepare("INSERT INTO ShoppingItem (ShoppingId, IngredientName, Quantity, Price, Unit) VALUES (?, ?, ?, ?, ?)");
    if ($stmt === false) {
        $error_message = "SQL 準備失敗: " . $conn->error;
        goto display_form;
    }

    $success = true;
    $validUnits = ['個', '克', '毫升', '瓶', '包', '公斤', ''];
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
        header("Location: ShoppingListDetail.php?ShoppingId=" . $shoppingId);
        exit();
    }
}

// 處理購物清單項目的編輯（僅未完成清單）
if ($isEditable && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_shopping_item'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "CSRF 驗證失敗，請重試！";
        goto display_form;
    }

    $itemId = (int)$_POST['itemId'];
    $ingredientName = trim($_POST['ingredientName']);
    $quantity = $_POST['quantity'] ? (int)$_POST['quantity'] : NULL;
    $price = $_POST['price'] ? (int)$_POST['price'] : NULL;
    $unit = $_POST['unit'] ?? NULL;

    if (empty($ingredientName)) {
        $error_message = "食材名稱不得為空！";
        goto display_form;
    }

    $validUnits = ['個', '克', '毫升', '瓶', '包', '公斤', ''];
    if ($unit && !in_array($unit, $validUnits)) {
        $error_message = "無效的單位值！請選擇有效單位或留空。";
        goto display_form;
    }

    $stmt = $conn->prepare("UPDATE ShoppingItem SET IngredientName = ?, Quantity = ?, Price = ?, Unit = ? WHERE ItemId = ? AND ShoppingId = ?");
    $stmt->bind_param("siisii", $ingredientName, $quantity, $price, $unit, $itemId, $shoppingId);

    if ($stmt->execute()) {
        header("Location: ShoppingListDetail.php?ShoppingId=" . $shoppingId);
        exit();
    } else {
        $error_message = "更新失敗: " . $stmt->error;
    }
    $stmt->close();
}

// 處理購物清單項目的刪除
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_shopping_item'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = "CSRF 驗證失敗，請重試！";
        goto display_form;
    }

    $itemId = (int)$_POST['itemId'];

    $stmt = $conn->prepare("DELETE FROM ShoppingItem WHERE ItemId = ? AND ShoppingId = ?");
    $stmt->bind_param("ii", $itemId, $shoppingId);

    if ($stmt->execute()) {
        header("Location: ShoppingListDetail.php?ShoppingId=" . $shoppingId);
        exit();
    } else {
        $error_message = "刪除失敗: " . $stmt->error;
    }
    $stmt->close();
}

// 查詢該購物清單的項目
$stmt = $conn->prepare("SELECT ItemId, ShoppingId, IngredientName, Quantity, Price, Unit FROM ShoppingItem WHERE ShoppingId = ?");
$stmt->bind_param("i", $shoppingId);
$stmt->execute();
$itemsResult = $stmt->get_result();

$items = [];
while ($itemRow = $itemsResult->fetch_assoc()) {
    $items[] = $itemRow;
}
$stmt->close();

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
    <title>購物清單詳情 - <?php echo htmlspecialchars($list['ListName'] ?? "購物清單 #{$list['ShoppingId']}"); ?></title>
    <link rel="stylesheet" href="css/boot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #e9f1f6;
            color: #333;
        }

        .list-group-item .row {
            font-family: monospace;
        }

        .list-group-item .col-4,
        .list-group-item .col-2,
        .list-group-item .col-3,
        .list-group-item .col-1 {
            padding: 0.5rem 0;
        }

        .list-group-item .col-2,
        .list-group-item .col-3,
        .list-group-item .col-1 {
            border-left: 1px solid #dee2e6;
        }

        .btn-custom {
            background-color: #007bff;
            color: #fff;
            border-radius: 8px;
        }

        .btn-custom:hover {
            background-color: #0056b3;
        }

        .btn-secondary,
        .btn-danger {
            border-radius: 8px;
        }

        .action-btn {
            border: 1px solid #fff;
            padding: 0.3rem 0.5rem;
        }

        .action-btn i {
            font-size: 1.1rem;
        }

        .ingredient-row {
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2>購物清單詳情</h2>
        <div class="mb-3">
            <h5><?php echo htmlspecialchars($list['ListName'] ?? "購物清單 #{$list['ShoppingId']}"); ?></h5>
            <p class="text-muted">建立日期: <?php echo $list['CreateDate']; ?>
                <?php if ($list['IsCompleted']): ?>
                    <span class="badge bg-success ms-2">已完成</span>
                <?php endif; ?>
            </p>
            <a href="ShoppingList.php" class="btn btn-secondary">返回清單</a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- 新增食材項目表單（僅未完成清單可見） -->
        <?php if ($isEditable): ?>
            <div class="card p-4 mb-4">
                <h5>新增食材項目</h5>
                <form method="POST" id="addItemsForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
                            <input type="number" class="form-control" name="prices[]" placeholder="輸入單價">

                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <button type="button" class="btn btn-custom" id="addIngredientBtn">+ 添加更多食材</button>
                    </div>
                    <div class="text-center">
                        <button type="submit" name="add_shopping_items" class="btn btn-custom">新增項目</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-4">此購物清單已完成，無法新增或編輯項目。</div>
        <?php endif; ?>

        <!-- 現有項目列表 -->
        <div class="list-group">
            <div class="list-group-item bg-light">
                <div class="row align-items-center font-weight-bold">
                    <div class="col-4">食材名稱</div>
                    <div class="col-2 text-center">數量</div>
                    <div class="col-1 text-center">單位</div>
                    <div class="col-2 text-center">單價</div>
                    <div class="col-3 text-center">操作</div>
                </div>
            </div>
            <?php if (empty($items)): ?>
                <div class="list-group-item">此購物清單尚無項目</div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <div class="list-group-item">
                        <div class="row align-items-center">
                            <div class="col-4">
                                <?php echo htmlspecialchars($item['IngredientName'] ?? '未知食材'); ?>
                            </div>
                            <div class="col-2 text-center" data-item-id="<?php echo $item['ItemId']; ?>">
                                <?php $quantity = $item['Quantity'] ? (int)$item['Quantity'] : 0; ?>
                                <button type="button"
                                    class="btn btn-sm btn-outline-secondary minus-btn"
                                    style="visibility: <?php echo ($quantity <= 1) ? 'hidden' : 'visible'; ?>;"
                                    onclick="changeQuantity(this, -1)">−</button>
                                <span class="mx-2 quantity"><?php echo $quantity; ?></span>
                                <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    onclick="changeQuantity(this, 1)">＋</button>
                            </div>

                            <div class="col-1 text-center">
                                <?php echo htmlspecialchars($item['Unit'] ?? '-'); ?>
                            </div>
                            <div class="col-2 text-center">
                                <?php echo $item['Price'] ? $item['Price'] . '$' : '未指定'; ?>
                            </div>
                            <div class="col-3 text-center">
                                <?php if ($isEditable): ?>
                                    <button class="btn btn-primary action-btn me-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $item['ItemId']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('確定要刪除此購物項目嗎？');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="itemId" value="<?php echo $item['ItemId']; ?>">
                                    <button type="submit" name="delete_shopping_item" class="btn btn-danger action-btn">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 編輯 Modal（僅未完成清單可見） -->
                    <?php if ($isEditable): ?>
                        <div class="modal fade" id="editModal<?php echo $item['ItemId']; ?>" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="editModalLabel">編輯購物項目</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="itemId" value="<?php echo $item['ItemId']; ?>">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="ingredientName" class="form-label">食材名稱</label>
                                                <input list="ingredientList" class="form-control" name="ingredientName" value="<?php echo htmlspecialchars($item['IngredientName'] ?? ''); ?>" placeholder="輸入或選擇食材名稱" required>
                                                <datalist id="ingredientList">
                                                    <?php foreach ($allIngredients as $name): ?>
                                                        <option value="<?php echo htmlspecialchars($name); ?>">
                                                        <?php endforeach; ?>
                                                </datalist>
                                            </div>
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">數量</label>
                                                <input type="number" class="form-control" name="quantity" value="<?php echo $item['Quantity']; ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label for="unit" class="form-label">單位</label>
                                                <select class="form-control" name="unit">
                                                    <option value="">選擇單位</option>
                                                    <option value="個" <?php if ($item['Unit'] == '個') echo 'selected'; ?>>個</option>
                                                    <option value="克" <?php if ($item['Unit'] == '克') echo 'selected'; ?>>克</option>
                                                    <option value="毫升" <?php if ($item['Unit'] == '毫升') echo 'selected'; ?>>毫升</option>
                                                    <option value="瓶" <?php if ($item['Unit'] == '瓶') echo 'selected'; ?>>瓶</option>
                                                    <option value="包" <?php if ($item['Unit'] == '包') echo 'selected'; ?>>包</option>
                                                    <option value="公斤" <?php if ($item['Unit'] == '公斤') echo 'selected'; ?>>公斤</option>
                                                    <?php if ($item['Unit'] && !in_array($item['Unit'], ['個', '克', '毫升', '瓶', '包', '公斤'])): ?>
                                                        <option value="<?php echo htmlspecialchars($item['Unit']); ?>" selected><?php echo htmlspecialchars($item['Unit']); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="price" class="form-label">價格</label>
                                                <input type="number" class="form-control" name="price" value="<?php echo $item['Price']; ?>">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                            <button type="submit" name="edit_shopping_item" class="btn btn-custom">儲存</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php
                $total_price = 0;
                foreach ($items as $item) {
                    $quantity = isset($item['Quantity']) ? (float)$item['Quantity'] : 0;
                    $price = isset($item['Price']) ? (float)$item['Price'] : 0;
                    $total_price += $quantity * $price;
                }
                ?>
                <div class="list-group-item text-end">
                    <strong id="totalPriceDisplay">
                        總價金額：NT$
                        <?php
                        echo (fmod($total_price, 1) == 0)
                            ? number_format($total_price, 0)
                            : number_format($total_price, 2);
                        ?>
                    </strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isEditable): ?>
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
                    <input type="number" class="form-control" name="prices[]" placeholder="輸入單價">
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
    <?php endif; ?>
    <script>
        function changeQuantity(button, delta) {
            const container = button.parentElement;
            const quantitySpan = container.querySelector('.quantity');
            const minusBtn = container.querySelector('.minus-btn');
            const itemId = container.getAttribute('data-item-id');

            let current = parseInt(quantitySpan.textContent);
            if (isNaN(current)) current = 0;

            current += delta;
            if (current < 0) current = 0;

            quantitySpan.textContent = current;
            minusBtn.style.visibility = (current <= 1) ? 'hidden' : 'visible';

            // AJAX 儲存數量
            fetch('update_quantity.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `itemId=${itemId}&quantity=${current}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data !== 'success') {
                        alert('更新失敗：' + data);
                    }
                })
                .catch(error => {
                    alert('錯誤：' + error);
                });
        }
    </script>
    <script>
        function changeQuantity(button, delta) {
            const container = button.parentElement;
            const quantitySpan = container.querySelector('.quantity');
            const minusBtn = container.querySelector('.minus-btn');
            const itemId = container.getAttribute('data-item-id');

            let current = parseInt(quantitySpan.textContent);
            if (isNaN(current)) current = 0;

            current += delta;
            if (current < 0) current = 0;

            quantitySpan.textContent = current;
            minusBtn.style.visibility = (current <= 1) ? 'hidden' : 'visible';

            // AJAX 儲存數量
            fetch('update_quantity.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `itemId=${itemId}&quantity=${current}`
                })
                .then(response => response.text())
                .then(data => {
                    if (data !== 'success') {
                        alert('更新失敗：' + data);
                    } else {
                        updateTotalPrice(); // ➕ 更新總價
                    }
                })
                .catch(error => {
                    alert('錯誤：' + error);
                });
            updateTotalPrice()
        }

        // 計算所有購物項目的總價
        function updateTotalPrice() {
            let total = 0;

            document.querySelectorAll('.list-group-item .row').forEach(row => {
                const quantityElem = row.querySelector('.quantity');
                const priceElem = row.querySelector('.col-2.text-center + .col-1 + .col-2'); // 根據你提供的 HTML 結構

                if (quantityElem && priceElem) {
                    const quantity = parseInt(quantityElem.textContent) || 0;
                    const priceText = priceElem.textContent.replace('未指定', '').replace('$', '');
                    const price = parseFloat(priceText) || 0;

                    total += quantity * price;
                }
            });

            const totalDisplay = document.getElementById('totalPriceDisplay');
            if (totalDisplay) {
                totalDisplay.innerHTML = `總價金額：NT$ ${ (total % 1 === 0) ? total.toFixed(0) : total.toFixed(2) }`;
            }
        }
    </script>





</body>

</html>
