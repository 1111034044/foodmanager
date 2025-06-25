<?php
session_start();

// 檢查是否登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "foodmanager");
if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

$uId = $_SESSION['user_id'];

// 處理刪除消耗紀錄
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_usage'])) {
    $usageId = (int)$_POST['usageId'];
    $stmt = $conn->prepare("DELETE FROM IngredientUsage WHERE UsageId = ? AND uId = ?");
    $stmt->bind_param("ii", $usageId, $uId);
    $stmt->execute();
    $stmt->close();
    header("Location: IngredientUsageManager.php");
    exit();
}
// 處理編輯消耗紀錄
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_usage'])) {
    $usageId = (int)$_POST['usageId'];
    $usedQuantity = $_POST['UsedQuantity'];
    $unit = $_POST['Unit'];
    $usageDate = $_POST['UsageDate'];
    $note = $_POST['Note'];

    $stmt = $conn->prepare("UPDATE IngredientUsage SET UsedQuantity = ?, Unit = ?, UsageDate = ?, Note = ? WHERE UsageId = ? AND uId = ?");
    $stmt->bind_param("isssii", $usedQuantity, $unit, $usageDate, $note, $usageId, $uId);
    $stmt->execute();
    $stmt->close();

    header("Location: IngredientUsageManager.php");
    exit();
}

// 取得所有消耗紀錄
$stmt = $conn->prepare("
    SELECT u.UsageId, i.IName, u.UsedQuantity, u.Unit, u.UsageDate, u.Note
FROM IngredientUsage u
JOIN Ingredient i ON u.IngredientId = i.IngredientId
WHERE u.uId = ?
ORDER BY u.UsageDate DESC

");
$stmt->bind_param("i", $uId);
$stmt->execute();
$result = $stmt->get_result();

$usages = [];
while ($row = $result->fetch_assoc()) {
    $usages[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>食材消耗紀錄</title>
    <link rel="stylesheet" href="css/boot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .usage-card .row {
            font-family: monospace;
        }

        .usage-card .col-2,
        .usage-card .col-3,
        .usage-card .col-4 {
            padding: 0.5rem 0;
        }

        .btn-custom {
            background-color: #fc8181;
            color: #fff;
        }

        .btn-custom:hover {
            background-color: #ff6b6b;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2>食材消耗紀錄</h2>
        <div class="list-group mt-3">
            <div class="list-group-item bg-light">
                <div class="row font-weight-bold align-items-center">
                    <div class="col-3">食材名稱</div>
                    <div class="col-1 text-center">消耗數量</div>
                    <div class="col-1 text-center">單位</div>
                    <div class="col-2 text-center">消耗日期</div>
                    <div class="col-3 text-center">備註</div>
                    <div class="col-2 text-center">操作</div>
                </div>
            </div>
            <?php if (empty($usages)): ?>
                <div class="list-group-item">尚無消耗紀錄</div>
            <?php else: ?>
                <?php foreach ($usages as $usage): ?>
                    <div class="list-group-item usage-card">
                        <div class="row align-items-center">
                            <div class="col-3"><?= htmlspecialchars($usage['IName']) ?></div>
                            <div class="col-1 text-center"><?= htmlspecialchars($usage['UsedQuantity']) ?></div>
                            <div class="col-1 text-center"><?= htmlspecialchars($usage['Unit']) ?></div>
                            <div class="col-2 text-center"><?= htmlspecialchars($usage['UsageDate']) ?></div>

                            <div class="col-3 text-center"><?= $usage['Note'] ? htmlspecialchars($usage['Note']) : '-' ?></div>
                            <div class="col-2 text-center">
                                <form method="POST" onsubmit="return confirm('確定要刪除此紀錄？');" style="display:inline;">
                                    <input type="hidden" name="usageId" value="<?= $usage['UsageId'] ?>">
                                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $usage['UsageId'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="submit" name="delete_usage" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="modal fade" id="editModal<?= $usage['UsageId'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $usage['UsageId'] ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <form method="POST" class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editModalLabel<?= $usage['UsageId'] ?>">編輯消耗紀錄</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="關閉"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="usageId" value="<?= $usage['UsageId'] ?>">

                                    <div class="mb-3">
                                        <label for="UsedQuantity<?= $usage['UsageId'] ?>" class="form-label">消耗數量</label>
                                        <input type="number" min="0" step="any" class="form-control" id="UsedQuantity<?= $usage['UsageId'] ?>" name="UsedQuantity" required value="<?= htmlspecialchars($usage['UsedQuantity']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="Unit<?= $usage['UsageId'] ?>" class="form-label">單位</label>
                                        <input type="text" class="form-control" id="Unit<?= $usage['UsageId'] ?>" name="Unit" required value="<?= htmlspecialchars($usage['Unit']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="UsageDate<?= $usage['UsageId'] ?>" class="form-label">消耗日期</label>
                                        <input type="date" class="form-control" id="UsageDate<?= $usage['UsageId'] ?>" name="UsageDate" required value="<?= htmlspecialchars($usage['UsageDate']) ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="Note<?= $usage['UsageId'] ?>" class="form-label">備註</label>
                                        <textarea class="form-control" id="Note<?= $usage['UsageId'] ?>" name="Note" rows="2"><?= htmlspecialchars($usage['Note']) ?></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                    <button type="submit" name="edit_usage" class="btn btn-primary">儲存修改</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
