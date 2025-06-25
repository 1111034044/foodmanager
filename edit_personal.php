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

// 使用 prepared statement 查詢用戶資料
$stmt = $conn->prepare("SELECT uName, Email FROM user WHERE uId = ?");
$stmt->bind_param("i", $uId); // "i" 表示整數類型，因為 uId 是 INT
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $uName = $user['uName'];
    $email = $user['Email'];
} else {
    $uName = "未知用戶";
    $email = "無電子郵件";
}

$stmt->close();

// 處理表單提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newName = $_POST['uName']; // 獲取用戶輸入的名字

    // 更新資料庫中的用戶名稱
    $updateStmt = $conn->prepare("UPDATE user SET uName = ? WHERE uId = ?");
    $updateStmt->bind_param("si", $newName, $uId);
    if ($updateStmt->execute()) {
        // 更新成功後，更新 session 變量
        $_SESSION['user_name'] = $newName;
        header("Location: personal.php"); // 跳轉回個人資料頁面
        exit();
    } else {
        $error = "更新失敗，請稍後再試";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編輯個人資料</title>
    <link rel="stylesheet" href="css/boot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        body {
            background-color: #f8e8c6;
        }
    </style>
</head>
<body>
    <!-- 導覽列 -->
    <?php include 'navbar.php'; ?>
    <!-- 側邊選單 -->
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2>編輯個人資料</h2>

        <?php if (isset($error)) { ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php } ?>

        <form method="post">
            <div class="mb-3">
                <label for="uName" class="form-label">使用者名稱</label>
                <input type="text" class="form-control" id="uName" name="uName" value="<?php echo htmlspecialchars($uName); ?>" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">電子郵件</label>
                <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
            </div>

            <button type="submit" class="btn btn-primary">儲存變更</button>
            <!-- 取消變更按鈕 -->
            <a href="personal.php" class="btn btn-secondary">取消變更</a>
        </form>
    </div>

</body>
</html>