<?php

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 檢查是否已登入，並獲取用戶資訊
$userImage = 'uploads/user-placeholder.jpg'; // 預設頭像

if (isset($_SESSION['user_id']) && isset($conn)) {
    $uId = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT uImage FROM user WHERE uId = ?");
    if ($stmt) {
        $stmt->bind_param("i", $uId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $userImage = !empty($row['uImage']) ? $row['uImage'] : $userImage;
        }
        $stmt->close();
    } else {
        error_log("Navbar prepare failed: " . $conn->error);
    }
}

// 不在這裡關閉$conn，避免其他頁面還需要用

?>

<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <button class="menu-btn me-3" data-bs-toggle="offcanvas" data-bs-target="#sidebar">☰</button>
        <a class="navbar-brand" href="index.php">食材管理系統</a>
        <div class="d-flex">
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- 已登入，顯示頭像和下拉選單 -->
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo htmlspecialchars($userImage); ?>" class="rounded-circle" alt="用戶頭像" width="40" height="40">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="personal.php">個人資料</a></li>
                        <li><a class="dropdown-item" href="logout.php">登出</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <!-- 未登入，顯示登入按鈕 -->
                <button class="btn btn-outline-success" onclick="location.href='login.php'">登入</button>
            <?php endif; ?>
        </div>
    </div>
</nav>
