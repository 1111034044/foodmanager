<?php
session_start(); // 啟動 session

$conn = new mysqli("localhost", "root", "", "foodmanager");
if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT uId, uName, Password FROM user WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row['Password'])) {
            // 驗證成功，但不直接登入，先暫存帳戶資訊
            $_SESSION['temp_user_id'] = $row['uId'];
            $_SESSION['temp_user_name'] = $row['uName'];
            $stmt->close();
            header("Location: select_family_role.php");
            exit();
        } else {
            echo "<script>alert('密碼錯誤');</script>";
        }
    } else {
        echo "<script>alert('找不到該用戶');</script>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 - 食材管理系統</title>
    <link rel="stylesheet" href="css/LogAndReg.css">
    <link rel="stylesheet" href="css/boot.css">
    <link rel="stylesheet" href="css/style.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .login-container {
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
    <!-- 引入導覽列 -->
    <?php include 'navbar.php'; ?>
    <!-- 引入側邊欄 -->
    <?php include 'sidebar.php'; ?>

    <!-- 登入表單 -->
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="login-container">
            <h3 class="text-center mb-4">登入</h3>
            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">電子郵件</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="請輸入電子郵件" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">密碼</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="請輸入密碼" required>
                </div>
                <button type="submit" class="btn btn-custom w-100">登入</button>
                <p class="text-center mt-3">
                    還沒有帳號？<a href="register.php">註冊</a>
                </p>
            </form>
        </div>
    </div>
</body>
</html>
