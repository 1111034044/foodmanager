<?php
session_start(); // 啟動 session

// 檢查用戶是否已登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // 未登入則跳轉到登入頁面
    exit();
}

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 從 session 中獲取 uId
$uId = $_SESSION['user_id'];

// 處理新食材的添加
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_ingredient'])) {
    $iName = $_POST['iName'];
    $quantity = $_POST['quantity'] ? (int)$_POST['quantity'] : NULL;
    $unit = $_POST['unit'];
    $expireDate = $_POST['expireDate'] ? $_POST['expireDate'] : NULL;
    $storeType = $_POST['storeType'] ? $_POST['storeType'] : NULL;
    $purchaseDate = $_POST['purchaseDate'] ? $_POST['purchaseDate'] : NULL;
    $role = $_SESSION['user_role'] ?? '未指定'; // ✅ 加在第 30 行左右
    $stmt = $conn->prepare("INSERT INTO Ingredient (uId, IName, Quantity, Unit, ExpireDate, StoreType, PurchaseDate, Role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isisssss", $uId, $iName, $quantity, $unit, $expireDate, $storeType, $purchaseDate, $role);

    if ($stmt->execute()) {
        header("Location: IngredientManager.php");
        exit();
    } else {
        echo "插入失敗: " . $stmt->error . "<br>";
    }
    $stmt->close();
}

// 處理食材編輯
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_ingredient'])) {
    $ingredientId = (int)$_POST['ingredientId'];
    $iName = $_POST['iName'];
    $quantity = $_POST['quantity'] ? (int)$_POST['quantity'] : NULL;
    $expireDate = $_POST['expireDate'] ? $_POST['expireDate'] : NULL;
    $storeType = $_POST['storeType'] ? $_POST['storeType'] : NULL;
    $purchaseDate = $_POST['purchaseDate'] ? $_POST['purchaseDate'] : NULL;
    $unit = $_POST['unit'] ?? null;

    $stmt = $conn->prepare("UPDATE Ingredient SET IName = ?, Quantity = ?, ExpireDate = ?, StoreType = ?, PurchaseDate = ?, Unit = ? WHERE IngredientId = ? AND uId = ?");
    $stmt->bind_param("sissssii", $iName, $quantity, $expireDate, $storeType, $purchaseDate, $unit, $ingredientId, $uId);

    if ($stmt->execute()) {
        header("Location: IngredientManager.php");
        exit();
    } else {
        echo "更新失敗: " . $stmt->error . "<br>";
    }
    $stmt->close();
}

// 處理食材刪除
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_ingredient'])) {
    $ingredientId = (int)$_POST['ingredientId'];

    $stmt = $conn->prepare("DELETE FROM Ingredient WHERE IngredientId = ? AND uId = ?");
    $stmt->bind_param("ii", $ingredientId, $uId);

    if ($stmt->execute()) {
        header("Location: IngredientManager.php");
        exit();
    } else {
        echo "刪除失敗: " . $stmt->error . "<br>";
    }
    $stmt->close();
}

// 處理食材消耗
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['use_ingredient'])) {
    $ingredientId = (int)$_POST['ingredientId'];
    $usedQuantity = (int)$_POST['usedQuantity'];
    $unit = $_POST['unit'] ?? '';
    $usageDate = $_POST['usageDate'];
    $note = $_POST['note'] ?? '';

    // 取得目前庫存
    $stmt = $conn->prepare("SELECT Quantity FROM Ingredient WHERE IngredientId = ? AND uId = ?");
    if ($stmt === false) {
        die("Prepare失敗: " . htmlspecialchars($conn->error));
    }
    $stmt->bind_param("ii", $ingredientId, $uId);
    $stmt->execute();
    $stmt->bind_result($currentQuantity);
    $stmt->fetch();
    $stmt->close();

    if ($usedQuantity <= 0 || $usedQuantity > $currentQuantity) {
        echo "<script>alert('消耗數量無效或大於現有庫存');</script>";
    } else {
        $newQuantity = $currentQuantity - $usedQuantity;

        $role = $_SESSION['user_role'] ?? '未指定'; // ✅ 加在這裡
        $stmt = $conn->prepare("INSERT INTO IngredientUsage (IngredientId, uId, UsedQuantity, Unit, UsageDate, Note, Role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissss", $ingredientId, $uId, $usedQuantity, $unit, $usageDate, $note, $role);
        $stmt->execute();
        $stmt->close(); 
        
        $stmt = $conn->prepare("INSERT INTO IngredientUsage (IngredientId, uId, UsedQuantity, Unit, UsageDate, Note) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            die("Prepare失敗: " . htmlspecialchars($conn->error));
        }
        $stmt->bind_param("iiisss", $ingredientId, $uId, $usedQuantity, $unit, $usageDate, $note);
        $stmt->execute();
        $stmt->close();

        header("Location: IngredientManager.php");
        exit();
    }
}


// 處理排序選項
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'expire';
$order = $_GET['order'] ?? 'asc';

$validSorts = ['name' => 'IName', 'purchase' => 'PurchaseDate', 'expire' => 'ExpireDate'];
$sortColumn = $validSorts[$sort] ?? 'ExpireDate';
$order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

// 使用 LIKE 進行名稱模糊搜尋
$sql = "SELECT IngredientId, IName, Quantity, Unit, ExpireDate, StoreType, PurchaseDate, Role
        FROM Ingredient
        WHERE uId = ? AND Quantity > 0 AND IName LIKE ?
        ORDER BY $sortColumn $order";

$stmt = $conn->prepare($sql);
$searchKeyword = '%' . $search . '%';
$stmt->bind_param("is", $uId, $searchKeyword);
$stmt->execute();
$result = $stmt->get_result();

$ingredients = [];
while ($row = $result->fetch_assoc()) {
    $ingredients[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>食材管理</title>
    <link rel="stylesheet" href="css/boot.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .list-group-item .row {
            font-family: monospace;
        }

        .list-group-item .col-3,
        .list-group-item .col-2,
        .list-group-item .col-1 {
            padding: 0.5rem 0;
        }

        .list-group-item .col-2,
        .list-group-item .col-1 {
            border-left: 1px solid #dee2e6;
        }

        .btn-custom {
            background-color: #fc8181;
            color: #fff;
        }

        .btn-custom:hover {
            background-color: #ff6b6b;
        }

        .action-btn {
            border: 1px solid #fff;
            padding: 0.3rem 0.5rem;
        }

        .action-btn i {
            font-size: 1.1rem;
        }

        .input-row .form-control {
            width: 90%;
            font-family: monospace;
            padding: 0.5rem;
            height: 100%;
        }

        .input-row select.form-control {
            padding: 0.5rem;
        }

        .input-label {
            font-size: 0.9rem;
            color: #666;
            margin-right: 0.5rem;
        }

        .input-row .col-2,
        .input-row .col-1 {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .store-type-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .expiry-status {
            font-size: 0.8rem;
            margin-top: 4px;
        }

        .list-group-item {
            transition: all 0.2s ease;
        }

        .list-group-item:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <!-- 導覽列 -->
    <?php include 'navbar.php'; ?>
    <!-- 側邊選單 -->
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2>食材管理</h2>
        <div class="list-group mb-3">
            <!-- 新增表單標題列 -->
            <div class="list-group-item bg-light">
                <div class="row align-items-center font-weight-bold">
                    <div class="col-3">食材名稱</div>
                    <div class="col-1 text-center">數量</div>
                    <div class="col-1 text-center">單位</div>
                    <div class="col-2 text-center">購入日期</div>
                    <div class="col-2 text-center">到期日期</div>
                    <div class="col-1 text-center">儲存類型</div>
                    <div class="col-2 text-center">操作</div>
                </div>
            </div>
            <!-- 新增表單輸入欄位 -->
            <div class="list-group-item">
                <form method="POST">
                    <div class="row align-items-center input-row">
                        <div class="col-3 d-flex align-items-center">
                            <input type="text" placeholder="輸入食材名稱" class="form-control" name="iName" required>
                        </div>
                        <div class="col-1 text-center d-flex align-items-center justify-content-center">
                            <input type="number" placeholder="輸入數量" class="form-control" name="quantity">
                        </div>
                        <div class="col-1 text-center d-flex align-items-center justify-content-center">
                            <select class="form-control" name="unit">
                                <option value="">▼選擇單位</option>
                                <option value="個">個</option>
                                <option value="克">克</option>
                                <option value="毫升">毫升</option>
                                <option value="瓶">瓶</option>
                                <option value="包">包</option>
                                <option value="公斤">公斤</option>
                            </select>
                        </div>
                        <div class="col-2 text-center">
                            <input type="date" class="form-control" name="purchaseDate" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-2 text-center">
                            <input type="date" class="form-control" name="expireDate">
                        </div>
                        <div class="col-1 text-center">
                            <select class="form-control" name="storeType">
                                <option value="">▼選擇</option>
                                <option value="冷藏">冷藏</option>
                                <option value="冷凍">冷凍</option>
                                <option value="常溫">常溫</option>
                            </select>
                        </div>
                        <div class="col-2 text-center">
                            <button type="submit" name="add_ingredient" class="btn btn-custom">+</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php
        $search = $_GET['search'] ?? '';
        $sort = $_GET['sort'] ?? 'expire';
        $order = $_GET['order'] ?? 'asc';
        $orderSymbol = $order === 'asc' ? '▲' : '▼';
        $nextOrder = $order === 'asc' ? 'desc' : 'asc';
        ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <!-- 搜尋框 -->
            <form method="GET" class="d-flex">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control me-2" placeholder="搜尋食材名稱">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i>
                </button>
            </form>

            <!-- 排序選單 -->
            <form method="GET" class="d-flex align-items-center">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <label for="sort" class="me-2">排序依據：</label>
                <select name="sort" id="sort" class="form-select w-auto me-2" onchange="this.form.submit()">
                    <option value="expire" <?php if ($sort === 'expire') echo 'selected'; ?>>到期日期</option>
                    <option value="purchase" <?php if ($sort === 'purchase') echo 'selected'; ?>>購入日期</option>
                </select>
                <input type="hidden" name="order" value="<?php echo $nextOrder; ?>">
                <button type="submit" class="btn btn-outline-secondary"><?php echo $orderSymbol; ?></button>
            </form>
        </div>

        <div class="list-group">
            <div class="list-group-item bg-light">
                <div class="row align-items-center font-weight-bold">
                    <div class="col-3">食材名稱</div>
                    <div class="col-1 text-center">數量</div>
                    <div class="col-1 text-center">單位</div>
                    <div class="col-2 text-center">購入日期</div>
                    <div class="col-2 text-center">到期日期</div>
                    <div class="col-1 text-center">儲存類型</div>
                    <div class="col-2 text-center">操作
                        <button class="btn btn-info btn-sm toggle-all-mode-btn ms-2" id="toggleAllBtn">消耗</button>
                    </div>
                </div>
            </div>
            <?php if (empty($ingredients)): ?>
                <div class="list-group-item">尚未添加任何食材</div>
            <?php else: ?>
                <?php foreach ($ingredients as $ingredient): ?>
                    <?php
                    $expireClass = '';
                    $expireText = $ingredient['ExpireDate'] ? $ingredient['ExpireDate'] : '無期限';
                    $expireStatus = '';
                    if ($ingredient['ExpireDate']) {
                        $expireDate = new DateTime($ingredient['ExpireDate']);
                        $today = new DateTime();
                        $interval = $today->diff($expireDate);
                        $daysLeft = $interval->days;
                        if ($interval->invert) {
                            $expireClass = 'bg-danger text-white';
                            $expireStatus = '(已過期)';
                        } elseif ($daysLeft <= 7) {
                            $expireClass = 'bg-warning text-dark';
                            $expireStatus = '(即將到期)';
                        }
                    }
                    $quantityDisplay = $ingredient['Quantity'] ? $ingredient['Quantity'] : '-';
                    $purchaseDateDisplay = $ingredient['PurchaseDate'] ? $ingredient['PurchaseDate'] : '-';
                    $storeTypeDisplay = $ingredient['StoreType'] ? $ingredient['StoreType'] : '-';
                    $whoAdded = $ingredient['Role'] ?? '未知身份'; // ✅ 放在每筆列印區塊前

                    ?>
                    <div class="list-group-item <?php echo $expireClass; ?>">
                        <div class="row align-items-center">
                            <div class="col-3">
                                <?php echo htmlspecialchars($ingredient['IName']); ?>
                                <br>
                                <small class="text-muted">身份：<?php echo htmlspecialchars($whoAdded); ?></small>
                            </div>
                            <div class="col-1 text-center">
                                <?php echo htmlspecialchars($quantityDisplay); ?>
                            </div>
                            <div class="col-1 text-center">
                                <?php echo htmlspecialchars($ingredient['Unit'] ?? '-'); ?>
                            </div>
                            <div class="col-2 text-center">
                                <?php echo htmlspecialchars($purchaseDateDisplay); ?>
                            </div>
                            <div class="col-2 text-center">
                                <?php echo htmlspecialchars($expireText); ?>
                            </div>
                            <div class="col-1 text-center">
                                <div class="store-type-container">
                                    <?php echo htmlspecialchars($storeTypeDisplay); ?>
                                    <?php if ($expireStatus): ?>
                                    <span class="expiry-status"><?php echo htmlspecialchars($expireStatus); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-2 text-center">
                                <!-- 消耗按鈕區塊（預設顯示） -->
                                <div id="consume-group-<?php echo $ingredient['IngredientId']; ?>" class="consume-group">
                                    <button class="btn btn-primary me-1" data-bs-toggle="modal"
                                        data-bs-target="#editModal<?php echo $ingredient['IngredientId']; ?>">
                                        消耗
                                    </button>
                                </div>
                                <!-- 編輯 + 刪除區塊（預設隱藏） -->
                                <div id="edit-delete-group-<?php echo $ingredient['IngredientId']; ?>" class="edit-delete-group" style="display: none;">
                                    <button class="btn me-1 border border-white"
                                        style="background-color:rgb(125, 188, 255);"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editIngredientModal<?php echo $ingredient['IngredientId']; ?>"
                                        title="編輯食材">
                                        <i class="bi bi-pencil text-dark"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('確定要刪除此食材嗎？');">
                                        <input type="hidden" name="ingredientId" value="<?php echo $ingredient['IngredientId']; ?>">
                                        <button type="submit" name="delete_ingredient" class="btn btn-danger action-btn"
                                            title="刪除食材">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 編輯 Modal -->
                    <div class="modal fade" id="editIngredientModal<?php echo $ingredient['IngredientId']; ?>" tabindex="-1"
                        aria-labelledby="editIngredientLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">編輯食材：<?php echo htmlspecialchars($ingredient['IName']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="ingredientId" value="<?php echo $ingredient['IngredientId']; ?>">

                                        <div class="mb-3">
                                            <label class="form-label">食材名稱</label>
                                            <input type="text" name="iName" class="form-control"
                                                value="<?php echo htmlspecialchars($ingredient['IName']); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">數量</label>
                                            <input type="number" name="quantity" class="form-control"
                                                value="<?php echo htmlspecialchars($ingredient['Quantity']); ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">單位</label>
                                            <select name="unit" class="form-select">
                                                <option value="">請選擇單位</option>
                                                <option value="個" <?php if ($ingredient['Unit'] == '個') echo 'selected'; ?>>個</option>
                                                <option value="克" <?php if ($ingredient['Unit'] == '克') echo 'selected'; ?>>克</option>
                                                <option value="毫升" <?php if ($ingredient['Unit'] == '毫升') echo 'selected'; ?>>毫升</option>
                                                <option value="瓶" <?php if ($ingredient['Unit'] == '瓶') echo 'selected'; ?>>瓶</option>
                                                <option value="包" <?php if ($ingredient['Unit'] == '包') echo 'selected'; ?>>包</option>
                                                <option value="公斤" <?php if ($ingredient['Unit'] == '公斤') echo 'selected'; ?>>公斤</option>
                                                <?php if ($ingredient['Unit'] && !in_array($ingredient['Unit'], ['個', '克', '毫升', '瓶', '包', '公斤'])): ?>
                                                    <option value="<?php echo htmlspecialchars($ingredient['Unit']); ?>" selected><?php echo htmlspecialchars($ingredient['Unit']); ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">購入日期</label>
                                            <input type="date" name="purchaseDate" class="form-control"
                                                value="<?php echo htmlspecialchars($ingredient['PurchaseDate']); ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">到期日期</label>
                                            <input type="date" name="expireDate" class="form-control"
                                                value="<?php echo htmlspecialchars($ingredient['ExpireDate']); ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">保存類型</label>
                                            <select name="storeType" class="form-select">
                                                <option value="">請選擇</option>
                                                <option value="冷藏" <?php if ($ingredient['StoreType'] == '冷藏') echo 'selected'; ?>>冷藏</option>
                                                <option value="冷凍" <?php if ($ingredient['StoreType'] == '冷凍') echo 'selected'; ?>>冷凍</option>
                                                <option value="常溫" <?php if ($ingredient['StoreType'] == '常溫') echo 'selected'; ?>>常溫</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                        <button type="submit" name="edit_ingredient" class="btn btn-success">儲存變更</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- 消耗 Modal -->
                    <div class="modal fade" id="editModal<?php echo $ingredient['IngredientId']; ?>" tabindex="-1"
                        aria-labelledby="editModalLabel<?php echo $ingredient['IngredientId']; ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="editModalLabel<?php echo $ingredient['IngredientId']; ?>">消耗食材：<?php echo htmlspecialchars($ingredient['IName']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="ingredientId" value="<?php echo $ingredient['IngredientId']; ?>">
                                        <div class="mb-3">
                                            <label for="usedQuantity<?php echo $ingredient['IngredientId']; ?>" class="form-label">消耗數量</label>
                                            <input type="number" class="form-control" id="usedQuantity<?php echo $ingredient['IngredientId']; ?>" name="usedQuantity" min="1" max="<?php echo $ingredient['Quantity']; ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="unit<?php echo $ingredient['IngredientId']; ?>" class="form-label">單位</label>
                                            <input type="text" class="form-control" id="unit<?php echo $ingredient['IngredientId']; ?>" name="unit" value="<?php echo htmlspecialchars($ingredient['Unit']); ?>" readonly>
                                        </div>
                                        <div class="mb-3">
                                            <label for="usageDate<?php echo $ingredient['IngredientId']; ?>" class="form-label">消耗日期</label>
                                            <input type="date" class="form-control" id="usageDate<?php echo $ingredient['IngredientId']; ?>" name="usageDate" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="note<?php echo $ingredient['IngredientId']; ?>" class="form-label">備註</label>
                                            <input type="text" class="form-control" id="note<?php echo $ingredient['IngredientId']; ?>" name="note" placeholder="可選填備註">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                        <button type="submit" name="use_ingredient" class="btn btn-warning">確認消耗</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="toggleMode.js"></script>
</body>

</html>
