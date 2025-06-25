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

// 處理購物清單的完成
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complete_shopping_list'])) {
    $shoppingId = (int)$_POST['shoppingId'];

    // 開始交易
    $conn->begin_transaction();

    try {
        // 1. 將購物清單標記為已完成
        $stmt = $conn->prepare("UPDATE ShoppingList SET IsCompleted = 1 WHERE ShoppingId = ? AND uId = ?");
        $stmt->bind_param("ii", $shoppingId, $uId);
        $stmt->execute();
        $stmt->close();

        // 2. 查詢該清單中的所有項目
        $stmt = $conn->prepare("SELECT IngredientName, Quantity, Unit FROM ShoppingItem WHERE ShoppingId = ?");
        $stmt->bind_param("i", $shoppingId);
        $stmt->execute();
        $result = $stmt->get_result();

        // 3. 將每個項目新增至 ingredient 表
        while ($item = $result->fetch_assoc()) {
            $ingredientName = $item['IngredientName'];
            $quantity = (int)$item['Quantity'];
            $unit = $item['Unit'];
            $today = date('Y-m-d');

            // 檢查該食材是否已存在（相同 uId 和 IName）
            $checkStmt = $conn->prepare("SELECT IngredientId, Quantity FROM ingredient WHERE uId = ? AND IName = ?");
            $checkStmt->bind_param("is", $uId, $ingredientName);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($existing = $checkResult->fetch_assoc()) {
                // 若已存在，則更新數量
                $newQuantity = $existing['Quantity'] + $quantity;
                $updateStmt = $conn->prepare("UPDATE ingredient SET Quantity = ? WHERE IngredientId = ?");
                $updateStmt->bind_param("ii", $newQuantity, $existing['IngredientId']);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                // 若不存在，插入新紀錄
                $insertStmt = $conn->prepare("
                    INSERT INTO ingredient (uId, IName, Quantity, Unit, PurchaseDate)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertStmt->bind_param("isiss", $uId, $ingredientName, $quantity, $unit, $today);
                $insertStmt->execute();
                $insertStmt->close();
            }

            $checkStmt->close();
        }

        $stmt->close();

        // 4. 提交交易
        $conn->commit();
        header("Location: ShoppingList.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "操作失敗: " . $e->getMessage();
    }
}

// 處理購物清單名稱的編輯
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_list_name'])) {
    $shoppingId = (int)$_POST['shoppingId'];
    $listName = trim($_POST['listName']);

    $stmt = $conn->prepare("UPDATE ShoppingList SET ListName = ? WHERE ShoppingId = ? AND uId = ?");
    $stmt->bind_param("sii", $listName, $shoppingId, $uId);

    if ($stmt->execute()) {
        header("Location: ShoppingList.php");
        exit();
    } else {
        echo "更新名稱失敗: " . $stmt->error . "<br>";
    }
    $stmt->close();
}

// 處理購物清單刪除
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_shopping_list'])) {
    $shoppingId = (int)$_POST['shoppingId'];

    // 開始交易
    $conn->begin_transaction();
    try {
        // 刪除 ShoppingItem 表中的項目
        $stmt = $conn->prepare("DELETE FROM ShoppingItem WHERE ShoppingId = ?");
        $stmt->bind_param("i", $shoppingId);
        $stmt->execute();
        $stmt->close();

        // 刪除 ShoppingList 表中的清單
        $stmt = $conn->prepare("DELETE FROM ShoppingList WHERE ShoppingId = ? AND uId = ?");
        $stmt->bind_param("ii", $shoppingId, $uId);
        $stmt->execute();
        $stmt->close();

        // 提交交易
        $conn->commit();
        header("Location: ShoppingList.php");
        exit();
    } catch (Exception $e) {
        // 回滾交易
        $conn->rollback();
        echo "刪除失敗: " . $e->getMessage() . "<br>";
    }
}

// 處理匯出購物清單項目
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['export_items'])) {
    if (!isset($_POST['shopping_ids']) || empty($_POST['shopping_ids'])) {
        header("Location: ShoppingList.php?error=no_selection");
        exit();
    }

    $shoppingIds = array_map('intval', $_POST['shopping_ids']);
    $placeholders = implode(',', array_fill(0, count($shoppingIds), '?'));

    $stmt = $conn->prepare("
        SELECT si.ItemId, si.ShoppingId, si.IngredientName, si.Quantity, si.Price, si.Unit, sl.ListName, sl.IsCompleted
        FROM ShoppingItem si
        JOIN ShoppingList sl ON si.ShoppingId = sl.ShoppingId
        WHERE sl.uId = ? AND si.ShoppingId IN ($placeholders) AND sl.IsCompleted = 0
        ORDER BY sl.CreateDate DESC, si.ItemId
    ");

    // 綁定參數：uId 和 shoppingIds
    $types = str_repeat('i', count($shoppingIds) + 1);
    $params = array_merge([$uId], $shoppingIds);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // 設定 CSV 標頭
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="shopping_list_items_' . date('Ymd') . '.csv"');

    // 使用 BOM 確保 Excel 正確顯示中文
    echo "\xEF\xBB\xBF"; // UTF-8 BOM

    // 開啟輸出流
    $output = fopen('php://output', 'w');

    // 寫入 CSV 標頭（無狀態欄）
    fputcsv($output, ['購物清單', '食材名稱', '數量', '單位', '價格']);

    // 寫入資料（無狀態欄）
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['ListName'] ?? "購物清單 #{$row['ShoppingId']}",
            $row['IngredientName'] ?? '未知食材',
            $row['Quantity'] ?? '-',
            $row['Unit'] ?? '-',
            $row['Price'] ? $row['Price'] . '$' : '未指定'
        ]);
    }

    fclose($output);
    $stmt->close();
    $conn->close();
    exit();
}

// 查詢當前用戶的購物清單資料
$shoppingLists = [];
$completedLists = [];
$stmt = $conn->prepare("SELECT ShoppingId, ListName, CreateDate, IsCompleted FROM ShoppingList WHERE uId = ? ORDER BY CreateDate DESC");
$stmt->bind_param("i", $uId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    if ($row['IsCompleted']) {
        $completedLists[] = $row;
    } else {
        $shoppingLists[] = $row;
    }
}
$stmt->close();

// 查詢所有購物清單項目（用於「查看所有購物清單項目」Modal）
$allItems = [];
$stmt = $conn->prepare("
    SELECT si.ItemId, si.ShoppingId, si.IngredientName, si.Quantity, si.Price, si.Unit, sl.ListName, sl.IsCompleted
    FROM ShoppingItem si
    JOIN ShoppingList sl ON si.ShoppingId = sl.ShoppingId
    WHERE sl.uId = ?
    ORDER BY sl.CreateDate DESC, si.ItemId
");
$stmt->bind_param("i", $uId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $allItems[] = $row;
}
$stmt->close();

// 查詢未完成的購物清單（用於匯出選擇）
$allShoppingLists = [];
$stmt = $conn->prepare("SELECT ShoppingId, ListName, IsCompleted FROM ShoppingList WHERE uId = ? AND IsCompleted = 0 ORDER BY CreateDate DESC");
$stmt->bind_param("i", $uId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $allShoppingLists[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>購物清單</title>
    <link rel="stylesheet" href="css/boot.css">
    <link rel="stylesheet" href="css/shoppinglistremit.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #e9f1f6;
            color: #333;
        }

        .list-card {
            background-color: #ffffff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
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

        .btn-delete {
            background-color: #dc3545;
            color: #fff;
            border-radius: 8px;
        }

        .btn-delete:hover {
            background-color: #b02a37;
        }

        .completed {
            background-color: #e0e0e0;
            opacity: 0.7;
        }

        .completed-section {
            margin-top: 3rem;
        }

        .modal-table th,
        .modal-table td {
            padding: 0.75rem;
            vertical-align: middle;
        }

        .edit-icon {
            cursor: pointer;
            color: #007bff;
            margin-left: 10px;
            transition: color 0.2s;
        }

        .edit-icon:hover {
            color: #0056b3;
        }

        .export-section {
            margin-bottom: 1.5rem;
        }
        
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2>購物清單</h2>
        <!-- 新增按鈕區域 -->
        <div class="mb-3">
            <button id="toggleAllItemsBtn" class="btn btn-custom me-2">
                查看所有購物清單項目
            </button>
            <a href="AddShoppingList.php" class="btn btn-custom">新增購物清單</a>
        </div>

        <!-- 未完成的購物清單 -->
        <?php if (!empty($shoppingLists)): ?>
            <div id="mainShoppingListSection">
            <?php foreach ($shoppingLists as $list): ?>
                <div class="list-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5>
                                <?php echo htmlspecialchars($list['ListName'] ?? "購物清單 #{$list['ShoppingId']}"); ?>
                                <i class="bi bi-pencil-square edit-icon" data-bs-toggle="modal" data-bs-target="#editNameModal<?php echo $list['ShoppingId']; ?>"></i>
                            </h5>
                            <p class="text-muted">建立日期: <?php echo $list['CreateDate']; ?></p>
                        </div>
                        <div>
                            <a href="ShoppingListDetail.php?ShoppingId=<?php echo $list['ShoppingId']; ?>" class="btn btn-primary btn-sm me-2">
                                <i class="bi bi-eye"></i> 查看詳情
                            </a>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="shoppingId" value="<?php echo $list['ShoppingId']; ?>">
                                <button type="submit" name="complete_shopping_list" class="btn btn-success btn-sm me-2">完成</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="shoppingId" value="<?php echo $list['ShoppingId']; ?>">
                                <button type="submit" name="delete_shopping_list" class="btn btn-delete btn-sm">刪除</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- 編輯名稱 Modal -->
                <div class="modal fade" id="editNameModal<?php echo $list['ShoppingId']; ?>" tabindex="-1" aria-labelledby="editNameModalLabel<?php echo $list['ShoppingId']; ?>" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editNameModalLabel<?php echo $list['ShoppingId']; ?>">編輯購物清單名稱</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="shoppingId" value="<?php echo $list['ShoppingId']; ?>">
                                    <div class="mb-3">
                                        <label for="listName<?php echo $list['ShoppingId']; ?>" class="form-label">清單名稱</label>
                                        <input type="text" class="form-control" id="listName<?php echo $list['ShoppingId']; ?>" name="listName" value="<?php echo htmlspecialchars($list['ListName'] ?? "購物清單 #{$list['ShoppingId']}"); ?>" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                    <button type="submit" name="edit_list_name" class="btn btn-primary">儲存</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 已完成的購物清單 -->
        <?php if (!empty($completedLists)): ?>
            <div class="completed-section">
                <h2>已完成的購物清單</h2>
                <?php foreach ($completedLists as $list): ?>
                    <div class="list-card completed">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5>
                                    <?php echo htmlspecialchars($list['ListName'] ?? "購物清單 #{$list['ShoppingId']}"); ?>
                                    <i class="bi bi-pencil-square edit-icon" data-bs-toggle="modal" data-bs-target="#editNameModal<?php echo $list['ShoppingId']; ?>"></i>
                                </h5>
                                <p class="text-muted">建立日期: <?php echo $list['CreateDate']; ?></p>
                            </div>
                            <div>
                                <a href="ShoppingListDetail.php?ShoppingId=<?php echo $list['ShoppingId']; ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-eye"></i> 查看詳情
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- 編輯名稱 Modal -->
                    <div class="modal fade" id="editNameModal<?php echo $list['ShoppingId']; ?>" tabindex="-1" aria-labelledby="editNameModalLabel<?php echo $list['ShoppingId']; ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editNameModalLabel<?php echo $list['ShoppingId']; ?>">編輯購物清單名稱</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="shoppingId" value="<?php echo $list['ShoppingId']; ?>">
                                        <div class="mb-3">
                                            <label for="listName<?php echo $list['ShoppingId']; ?>" class="form-label">清單名稱</label>
                                            <input type="text" class="form-control" id="listName<?php echo $list['ShoppingId']; ?>" name="listName" value="<?php echo htmlspecialchars($list['ListName'] ?? "購物清單 #{$list['ShoppingId']}"); ?>" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                        <button type="submit" name="edit_list_name" class="btn btn-primary">儲存</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 當沒有任何購物清單時顯示的訊息 -->
        <?php if (empty($shoppingLists) && empty($completedLists)): ?>
            <div id="emptyStateMessage" class="alert alert-info mt-4">
                <h5>目前尚未有任何購物清單</h5>
                <p>點選「新增購物清單」開始建立您的第一個購物清單吧！</p>
            </div>
        <?php endif; ?>

        <div id="allItemsSection" style="display: none;">
            <h4>所有購物清單項目</h4>
            <div class="export-section">
                <form method="POST">
                    <h6>選擇要匯出的購物清單</h6>
                    <?php if (empty($allShoppingLists)): ?>
                        <div class="alert alert-info">尚未有未完成的購物清單可匯出</div>
                    <?php else: ?>
                        <?php foreach ($allShoppingLists as $list): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="shopping_ids[]" value="<?php echo $list['ShoppingId']; ?>" id="shoppingId<?php echo $list['ShoppingId']; ?>">
                                <label class="form-check-label" for="shoppingId<?php echo $list['ShoppingId']; ?>">
                                    <?php echo htmlspecialchars($list['ListName'] ?? "購物清單 #{$list['ShoppingId']}"); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" name="export_items" class="btn btn-primary mt-2">匯出選擇的清單</button>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (empty($allItems)): ?>
                <div class="alert alert-info">尚無購物清單項目</div>
            <?php else: ?>
                <table class="table table-striped modal-table mt-3">
                    <thead>
                        <tr>
                            <th>購物清單</th>
                            <th>食材名稱</th>
                            <th>數量</th>
                            <th>單位</th>
                            <th>價格</th>
                            <th>狀態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allItems as $item): ?>
                            <tr>
                                <td>
                                    <a href="ShoppingListDetail.php?ShoppingId=<?php echo $item['ShoppingId']; ?>">
                                        <?php echo htmlspecialchars($item['ListName'] ?? "購物清單 #{$item['ShoppingId']}"); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($item['IngredientName'] ?? '未知食材'); ?></td>
                                <td><?php echo $item['Quantity'] ? $item['Quantity'] : '-'; ?></td>
                                <td><?php echo htmlspecialchars($item['Unit'] ?? '-'); ?></td>
                                <td><?php echo $item['Price'] ? $item['Price'] . '$' : '未指定'; ?></td>
                                <td>
                                    <?php echo $item['IsCompleted'] ? '<span class="badge bg-success">已完成</span>' : '<span class="badge bg-warning">未完成</span>'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 查看所有購物清單項目 Modal -->
        <div class="modal fade" id="allItemsModal" tabindex="-1" aria-labelledby="allItemsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="allItemsModalLabel">所有購物清單項目</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- 匯出表單 -->
                        <div class="export-section">
                            <form method="POST" id="exportForm">
                                <h6>選擇要匯出的購物清單</h6>
                                <?php if (empty($allShoppingLists)): ?>
                                    <div class="alert alert-info">尚未有未完成的購物清單可匯出</div>
                                <?php else: ?>
                                    <?php foreach ($allShoppingLists as $list): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="shopping_ids[]" value="<?php echo $list['ShoppingId']; ?>" id="shoppingId<?php echo $list['ShoppingId']; ?>">
                                            <label class="form-check-label" for="shoppingId<?php echo $list['ShoppingId']; ?>">
                                                <?php echo htmlspecialchars($list['ListName'] ?? "購物清單 #{$list['ShoppingId']}"); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                    <button type="submit" name="export_items" class="btn btn-primary mt-2">匯出選擇的清單</button>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- 項目表格 -->
                        <?php if (empty($allItems)): ?>
                            <div class="alert alert-info">尚無購物清單項目</div>
                        <?php else: ?>
                            <table class="table table-striped modal-table">
                                <thead>
                                    <tr>
                                        <th>購物清單</th>
                                        <th>食材名稱</th>
                                        <th>數量</th>
                                        <th>單位</th>
                                        <th>價格</th>
                                        <th>狀態</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allItems as $item): ?>
                                        <tr>
                                            <td>
                                                <a href="ShoppingListDetail.php?ShoppingId=<?php echo $item['ShoppingId']; ?>">
                                                    <?php echo htmlspecialchars($item['ListName'] ?? "購物清單 #{$item['ShoppingId']}"); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['IngredientName'] ?? '未知食材'); ?></td>
                                            <td><?php echo $item['Quantity'] ? $item['Quantity'] : '-'; ?></td>
                                            <td><?php echo htmlspecialchars($item['Unit'] ?? '-'); ?></td>
                                            <td><?php echo $item['Price'] ? $item['Price'] . '$' : '未指定'; ?></td>
                                            <td>
                                                <?php echo $item['IsCompleted'] ? '<span class="badge bg-success">已完成</span>' : '<span class="badge bg-warning">未完成</span>'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const toggleBtn = document.getElementById("toggleAllItemsBtn");
    const allItemsSection = document.getElementById("allItemsSection");
    const mainSection = document.getElementById("mainShoppingListSection");
    const completedSection = document.querySelector(".completed-section"); // 選取已完成區塊
    const emptyStateMessage = document.getElementById("emptyStateMessage"); // 選取空狀態訊息

    toggleBtn.addEventListener("click", () => {
        const isVisible = allItemsSection.style.display === "block";

        if (isVisible) {
            // 返回主要檢視
            allItemsSection.style.display = "none";
            if (mainSection) mainSection.style.display = "block"; // 只有存在時才顯示
            if (completedSection) completedSection.style.display = "block"; // 只有存在時才顯示
            if (emptyStateMessage) emptyStateMessage.style.display = "block"; // 顯示空狀態訊息
            toggleBtn.textContent = "查看所有購物清單項目";
        } else {
            // 切換到所有項目檢視
            allItemsSection.style.display = "block";
            if (mainSection) mainSection.style.display = "none"; // 只有存在時才隱藏
            if (completedSection) completedSection.style.display = "none"; // 只有存在時才隱藏
            if (emptyStateMessage) emptyStateMessage.style.display = "none"; // 隱藏空狀態訊息
            toggleBtn.textContent = "返回購物清單";
        }
    });
</script>
</body>

</html>
