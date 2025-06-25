<?php
session_start(); // 啟動 session 以便儲存 uId

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uname = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // 密碼加密
    $uimage = "uploads/user-placeholder.jpg"; // 預設頭像

    // 處理圖片上傳
    if (isset($_FILES['uimage']) && $_FILES['uimage']['error'] == 0) {
        $target_dir = "uploads/"; // 請確保這個資料夾存在且有寫入權限
        $target_file = $target_dir . basename($_FILES["uimage"]["name"]);
        if (move_uploaded_file($_FILES["uimage"]["tmp_name"], $target_file)) {
            $uimage = $target_file; // 如果上傳成功，使用上傳的圖片
        }
        // 如果上傳失敗，仍使用預設頭像
    }

    // 使用 prepared statement 防止 SQL 注入
    $stmt = $conn->prepare("INSERT INTO user (uName, Password, Email, Language, uImage) VALUES (?, ?, ?, '', ?)");
    $stmt->bind_param("ssss", $uname, $password, $email, $uimage);

    if ($stmt->execute()) {
        // 取得剛剛插入的 uId
        $uId = $conn->insert_id; // mysqli 的 insert_id 屬性會返回最後插入的 AUTO_INCREMENT 值

        // 將 uId 和 uName 存入 session，以便後續頁面使用
        $_SESSION['user_id'] = $uId;
        $_SESSION['user_name'] = $uname;

        // 關閉語句
        $stmt->close();

        // 註冊成功後跳轉到 login.php
        header("Location: index.php");
        exit();
    } else {
        echo "錯誤: " . $stmt->error;
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊 - 食材管理系統</title>
    <link rel="stylesheet" href="css/LogAndReg.css">
    <link rel="stylesheet" href="css/boot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .register-container {
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
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #fc8181;
            margin: 0 auto 15px;
            display: block;
        }
        .avatar-container {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- 引入導覽列 -->
    <?php include 'navbar.php'; ?>
    <!-- 引入側邊欄 -->
    <?php include 'sidebar.php'; ?>

    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="register-container">
            <h3 class="text-center">註冊</h3>
            <form method="POST" enctype="multipart/form-data">
                <!-- 頭像預覽區域 -->
                <div class="avatar-container">
                    <img id="avatar-preview" src="uploads/user-placeholder.jpg" alt="頭像預覽" class="avatar-preview">
                    <small class="text-muted">頭像預覽</small>
                </div>
                
                <div class="mb-3">
                    <label for="name" class="form-label">姓名</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="請輸入姓名" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">電子郵件</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="請輸入電子郵件" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">密碼</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="請輸入密碼" required>
                </div>
                <div class="mb-3">
                    <label for="confirm-password" class="form-label">確認密碼</label>
                    <input type="password" class="form-control" id="confirm-password" placeholder="請再次輸入密碼" required>
                </div>
                <div class="mb-3">
                    <label for="uimage" class="form-label">頭像 <small class="text-muted">(選填，不上傳將使用預設頭像)</small></label>
                    <input type="file" class="form-control" id="uimage" name="uimage" accept="image/*">
                </div>
                <button type="submit" class="btn btn-custom w-100">註冊</button>
                <p class="text-center mt-3">
                    已有帳號？<a href="login.php">登入</a>
                </p>
            </form>
        </div>
    </div>

    <script>
        // 密碼確認驗證
        document.getElementById('confirm-password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('密碼不相符');
            } else {
                this.setCustomValidity('');
            }
        });

        // 頭像預覽功能
        document.getElementById('uimage').addEventListener('change', function(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('avatar-preview');
            
            if (file) {
                // 檢查檔案類型
                if (!file.type.startsWith('image/')) {
                    alert('請選擇圖片檔案');
                    this.value = '';
                    preview.src = 'uploads/user-placeholder.jpg';
                    return;
                }
                
                // 檢查檔案大小 (限制 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('圖片檔案不能超過 5MB');
                    this.value = '';
                    preview.src = 'uploads/user-placeholder.jpg';
                    return;
                }
                
                // 預覽圖片
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                // 如果沒有選擇檔案，顯示預設頭像
                preview.src = 'uploads/user-placeholder.jpg';
            }
        });

        // 表單提交前最終驗證
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('密碼與確認密碼不相符，請重新輸入');
                return false;
            }
        });
    </script>
</body>
</html>
