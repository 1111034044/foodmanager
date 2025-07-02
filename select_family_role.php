<?php
session_start();
$conn = new mysqli("localhost", "root", "", "foodmanager");

if (!isset($_SESSION['temp_user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['temp_user_id'];
$userName = $_SESSION['temp_user_name'];

// 動作處理
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    // 選擇身份登入
    if ($action === 'select_role' && isset($_POST['role'])) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $userName;
        $_SESSION['user_role'] = $_POST['role'];
        unset($_SESSION['temp_user_id'], $_SESSION['temp_user_name']);
        header("Location: index.php");
        exit();
    }

    // 新增身份
    if ($action === 'add_role' && !empty(trim($_POST['new_role']))) {
        $newRole = trim($_POST['new_role']);
        // 避免重複新增
        $stmt = $conn->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_name = ?");
        $stmt->bind_param("is", $userId, $newRole);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count == 0) {
            $stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_name) VALUES (?, ?)");
            $stmt->bind_param("is", $userId, $newRole);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: select_family_role.php");
        exit();
    }

    // 刪除身份
    if ($action === 'delete_role' && isset($_POST['delete_role'])) {
        $roleToDelete = $_POST['delete_role'];
        $stmt = $conn->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_name = ?");
        $stmt->bind_param("is", $userId, $roleToDelete);
        $stmt->execute();
        $stmt->close();
        header("Location: select_family_role.php");
        exit();
    }
}

// 讀取所有身份
$roles = [];
$stmt = $conn->prepare("SELECT role_name FROM user_roles WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $roles[] = $row['role_name'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>選擇身份</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
<div class="card p-4 shadow" style="width: 420px;">
    <!-- 新增顯示當前登入身份 -->
    <?php if (isset($_SESSION['user_role'])): ?>
        <div class="alert alert-info text-center mb-3" role="alert">
            目前登入身份：<?= htmlspecialchars($_SESSION['user_role']) ?>
        </div>
    <?php endif; ?>
    <h4 class="text-center mb-3">您好，<?= htmlspecialchars($userName) ?>，請選擇身份</h4>

    <!-- ✅ 身份選擇與登入（單一 form） -->
    <form method="POST">
        <input type="hidden" name="action" value="select_role">
        <?php foreach ($roles as $index => $role): ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="role" id="role<?= $index ?>" value="<?= htmlspecialchars($role) ?>" required>
                    <label class="form-check-label" for="role<?= $index ?>"><?= htmlspecialchars($role) ?></label>
                </div>
                <?php if (count($roles) > 1): ?>
                    <!-- ❌ 注意不要把刪除按鈕寫在同一 form 裡 -->
                    <!-- 修改：移除嵌套表單 -->
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteRole('<?= htmlspecialchars($role) ?>')">刪除</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary w-100 mt-2">登入所選身份</button>
    </form>

    <!-- 添加 JavaScript 代碼處理刪除操作 -->
    <script>
        function deleteRole(role) {
            if (confirm('確定要刪除此身份嗎？')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_role';
                form.appendChild(actionInput);

                const roleInput = document.createElement('input');
                roleInput.type = 'hidden';
                roleInput.name = 'delete_role';
                roleInput.value = role;
                form.appendChild(roleInput);

                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>

    <!-- ➕ 新增身份 -->
    <hr>
    <form method="POST" class="mt-3">
        <input type="hidden" name="action" value="add_role">
        <div class="input-group">
            <input type="text" name="new_role" class="form-control" placeholder="新增身份（如：媽媽）" required>
            <button class="btn btn-success" type="submit">新增</button>
        </div>
    </form>
</div>
</body>
</html>
