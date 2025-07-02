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

// 處理刪除稱謂請求
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['role_id'])) {
        $role_id = intval($_POST['role_id']);
        $sql = "DELETE FROM family_roles WHERE id = $role_id";
        if ($conn->query($sql) === TRUE) {
            echo "<script>alert('稱謂刪除成功'); window.location.href = 'select_family_role.php';</script>";
        } else {
            echo "<script>alert('稱謂刪除失敗: " . $conn->error . "'); window.history.back();</script>";
        }
    }
}

// 獲取所有稱謂
$sql = "SELECT id, role_name FROM family_roles";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>刪除家庭稱謂 - 食材管理系統</title>
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
            <h3 class="text-center">刪除家庭稱謂</h3>
            <form method="POST">
                <div class="mb-3">
                    <label for="role_id" class="form-label">請選擇要刪除的稱謂</label>
                    <select class="form-select" id="role_id" name="role_id" required>
                        <?php
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<option value=\"{$row['id']}\">{$row['role_name']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-custom w-100">刪除</button>
            </form>
        </div>
    </div>
</body>
</html>