<?php
session_start();

// 檢查用戶是否已登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "foodmanager");
if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 處理新增稱謂表單提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['new_role'])) {
        $new_role = $_POST['new_role'];
        
        // 檢查稱謂是否已存在
        $check_sql = "SELECT * FROM family_roles WHERE role_name = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $new_role);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if (!$check_result) {
            echo "<script>alert('查詢稱謂出錯: " . $conn->error . "'); window.history.back();</script>";
            exit();
        }

        if ($check_result->num_rows > 0) {
            echo "<script>alert('該稱謂已存在，請輸入其他稱謂'); window.history.back();</script>";
        } else {
            $insert_sql = "INSERT INTO family_roles (role_name) VALUES (?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("s", $new_role);
            if ($stmt->execute()) {
                echo "<script>alert('稱謂新增成功'); window.location.href = 'select_family_role.php';</script>";
            } else {
                echo "<script>alert('稱謂新增失敗: " . $conn->error . "'); window.history.back();</script>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增家庭稱謂 - 食材管理系統</title>
    <link rel="stylesheet" href="css/LogAndReg.css">
    <link rel="stylesheet" href="css/boot.css">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .role-container {
            width: 400px;
            padding: 30px;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
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

    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="role-container">
            <h3 class="text-center">新增家庭稱謂</h3>
            <form method="POST">
                <div class="mb-3">
                    <label for="new_role" class="form-label">請輸入新稱謂</label>
                    <input type="text" class="form-control" id="new_role" name="new_role" required>
                </div>
                <button type="submit" class="btn btn-custom w-100">新增</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>