<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo "<div class='text-center p-3'>請先登入</div>";
    exit();
}

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    echo "<div class='text-center p-3'>連線失敗</div>";
    exit();
}

// 獲取最新的5條通知
$stmt = $conn->prepare("SELECT n.*, n.role as user_role 
                       FROM notifications n 
                       WHERE n.user_id = ? 
                       ORDER BY n.created_at DESC LIMIT 5");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $type_icon = '';
        $type_text = '';
        
        switch ($row['type']) {
            case 'ingredient':
                $type_icon = '<i class="bi bi-egg-fill text-success"></i>';
                $type_text = '新增食材';
                break;
            case 'shopping':
                $type_icon = '<i class="bi bi-cart-fill text-primary"></i>';
                $type_text = '新增購物清單';
                break;
            case 'recipe':
                $type_icon = '<i class="bi bi-journal-richtext text-danger"></i>';
                $type_text = '新增食譜';
                break;
        }
        
        $time_ago = time_elapsed_string($row['created_at']);
        $read_class = $row['is_read'] ? '' : 'fw-bold bg-light';
        
        echo "<div class='dropdown-item notification-item {$read_class}' data-id='{$row['id']}'>";
        echo "  <div class='d-flex align-items-center'>";
        echo "    <div class='me-2'>{$type_icon}</div>";
        echo "    <div>";
        echo "      <div><strong>{$row['user_role']}</strong> {$type_text}：{$row['item_name']}</div>";
        echo "      <small class='text-muted'>{$time_ago}</small>";
        echo "    </div>";
        echo "  </div>";
        echo "</div>";
    }
    
    // 標記所有通知為已讀
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    $stmt->execute();
    
} else {
    echo "<div class='text-center p-3'>沒有通知</div>";
}

$stmt->close();
$conn->close();

// 時間格式化函數
function time_elapsed_string($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->d > 0) {
        return $diff->d . "天前";
    } elseif ($diff->h > 0) {
        return $diff->h . "小時前";
    } elseif ($diff->i > 0) {
        return $diff->i . "分鐘前";
    } else {
        return "剛剛";
    }
}