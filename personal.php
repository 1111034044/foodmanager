<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "foodmanager");
if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

$uId = $_SESSION['user_id'];

// 查詢用戶資料（保持不變）
$stmt = $conn->prepare("SELECT uName, Email, uImage FROM user WHERE uId = ?");
$stmt->bind_param("i", $uId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $uName = $user['uName'];
    $email = $user['Email'];
    $uImage = $user['uImage'] ? $user['uImage'] : "images/profile-placeholder.png";
} else {
    $uName = "未知用戶";
    $email = "無電子郵件";
    $uImage = "images/profile-placeholder.png";
}

// 處理頭像上傳
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['uimage']) && $_FILES['uimage']['error'] == UPLOAD_ERR_OK) {
    $target_dir = "uploads/";

    // 檢查並建立 uploads 目錄
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true); // 建立目錄並賦予寫入權限
    }

    // 檢查目錄是否可寫
    if (!is_writable($target_dir)) {
        $error_message = "無法寫入 uploads 目錄，請檢查權限！";
    } else {
        $file_ext = strtolower(pathinfo($_FILES['uimage']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif']; // 限制檔案類型
        $target_file = $target_dir . $uId . '_avatar.' . $file_ext;

        if (in_array($file_ext, $allowed_ext) && $_FILES['uimage']['size'] <= 2 * 1024 * 1024) { // 限制大小 2MB
            if (move_uploaded_file($_FILES['uimage']['tmp_name'], $target_file)) {
                // 準備更新資料庫
                $stmt = $conn->prepare("UPDATE user SET uImage = ? WHERE uId = ?");
                if ($stmt === false) {
                    $error_message = "準備更新語句失敗: " . $conn->error;
                } else {
                    $stmt->bind_param("si", $target_file, $uId);
                    if ($stmt->execute()) {
                        $uImage = $target_file; // 更新本地變數
                        $success_message = "頭像已成功更新！";
                    } else {
                        $error_message = "更新資料庫失敗: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                $error_message = "頭像上傳失敗，請檢查檔案或目錄權限！";
            }
        } else {
            $error_message = "檔案格式不支援或超過 2MB！僅支援 JPG、PNG、GIF。";
        }
    }
}

// 處理密碼修改（保持不變）
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT Password FROM user WHERE uId = ?");
    $stmt->bind_param("i", $uId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stored_password = $result->fetch_assoc()['Password'];

    if (password_verify($current_password, $stored_password)) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE user SET Password = ? WHERE uId = ?");
            $stmt->bind_param("si", $hashed_password, $uId);
            if ($stmt->execute()) {
                $success_message = "密碼已成功更新！";
            } else {
                $error_message = "密碼更新失敗，請稍後再試。";
            }
        } else {
            $error_message = "新密碼與確認密碼不符！";
        }
    } else {
        $error_message = "目前密碼輸入錯誤！";
    }
}

// 查詢用戶食譜（保持不變）
$recipeStmt = $conn->prepare("SELECT RecipeId, rName, CoverImage, DifficultyLevel, cooktime FROM Recipe WHERE uId = ?");
$recipeStmt->bind_param("i", $uId);
$recipeStmt->execute();
$recipeResult = $recipeStmt->get_result();
$recipes = [];
while ($recipe = $recipeResult->fetch_assoc()) {
    $recipes[] = $recipe;
}

$recipeStmt->close();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>個人資料 - 食材管理系統</title>
    <link rel="stylesheet" href="css/boot.css">
    <link rel="stylesheet" href="css/get_random_recipes.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Cropper.js -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
            color: #333;
        }

        .profile-card {
            background-color: #ffffff;
            border-radius: 15px;
            padding: 30px;
            max-width: 800px;
            margin: 0 auto 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .avatar-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgb(125, 188, 255);
            margin-bottom: 20px;
        }

        .form-control {
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-radius: 8px;
            color: #333;
        }

        .btn-custom {
            background-color:rgb(125, 188, 255);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: background-color 0.3s, transform 0.2s;
            color: #fff;
        }

        .btn-custom:hover {
            background-color: rgb(94, 172, 255);
            transform: scale(1.05);
        }

        h2,
        h3 {
            color: #333;
        }

        .recipe-section {
            background-color: #f5f5f5;
            padding: 30px 0;
        }

        .recipe-section h3 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        /* 裁切模態框樣式 */
        .cropper-container {
            max-width: 100%;
            min-height: 800px !important;
            margin: auto;
            overflow: hidden;
        }

        #cropper-image {
            max-width: 100%;
            min-height: 800px !important;
            object-fit: contain;
        }

        /* 調整模態框尺寸 */
        .modal-lg {
            max-width: 1200px;
            min-width: 1200px !important;
        }

        .modal-dialog {
            min-width: 1200px !important;
            min-height: 900px !important;
            margin: 0 auto;
        }

        .modal-content {
            min-height: 850px !important;
            width: 100%;
        }

        .cropper-canvas {
            min-height: 800px !important;
        }
    </style>
</head>

<body>
    <!-- 導覽列 -->
    <?php include 'navbar.php'; ?>
    <!-- 側邊欄 -->
    <?php include 'sidebar.php'; ?>

    <div class="container">
        <!-- 個人資料區域 -->
        <div class="profile-card">
            <h2 class="text-center">個人資料</h2>
            <div class="text-center">
                <img src="<?php echo htmlspecialchars($uImage); ?>" alt="頭像" class="avatar-img">
                <form id="upload-form" method="POST" enctype="multipart/form-data" class="mb-4">
                    <div class="mb-3">
                        <label for="uimage" class="form-label">上傳新頭像</label>
                        <input type="file" class="form-control" id="uimage" name="uimage" accept="image/*">
                        <input type="hidden" id="cropped-image" name="cropped_image">
                    </div>

                </form>
            </div>

            <div class="mb-3">
                <label class="form-label">使用者名稱</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($uName); ?>" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">電子郵件</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" readonly>
            </div>

            <!-- 修改密碼 -->
            <h3 class="mt-4">修改密碼</h3>
            <form method="POST">
                <div class="mb-3">
                    <label for="current_password" class="form-label">目前密碼</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">新密碼</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">確認新密碼</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-custom">儲存密碼</button>
                </div>
            </form>

            <!-- 提示訊息 -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success mt-3"><?php echo $success_message; ?></div>
            <?php elseif (isset($error_message)): ?>
                <div class="alert alert-danger mt-3"><?php echo $error_message; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 裁切模態框 -->
    <div class="modal fade" id="cropper-modal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cropperModalLabel">裁切頭像</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="cropper-container">
                        <img id="cropper-image" src="" alt="圖片預覽">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-custom" id="crop-button">裁切並上傳</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 引入 jQuery 和 Cropper.js -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script>
        $(document).ready(function() {
            let cropper;
            let fileName;

            // 當選擇圖片時觸發
            $('#uimage').on('change', function(e) {
                const files = e.target.files;
                if (files && files.length > 0) {
                    fileName = files[0].name;
                    const reader = new FileReader();
                    reader.onload = function(event) {
                        $('#cropper-image').attr('src', event.target.result);
                        $('#cropper-modal').modal('show');
                        $('#upload-button').prop('disabled', false);

                        // 初始化 Cropper.js
                        const image = document.getElementById('cropper-image');
                        if (cropper) {
                            cropper.destroy();
                        }
                        cropper = new Cropper(image, {
                            aspectRatio: 1, // 限制為正方形
                            viewMode: 2, // 確保裁切框適應容器
                            autoCropArea: 1.0, // 自動填充整個可用區域
                            responsive: true,
                            autoCrop: true,
                            minContainerWidth: 1200, // 最小容器寬度
                            minContainerHeight: 800 // 最小容器高度
                        });
                    };
                    reader.readAsDataURL(files[0]);
                }
            });

            // 裁切並上傳
            $('#crop-button').on('click', function() {
                if (cropper) {
                    cropper.getCroppedCanvas({
                        width: 150,
                        height: 150
                    }).toBlob(function(blob) {
                        const formData = new FormData();
                        formData.append('uimage', blob, fileName);

                        $.ajax({
                            url: window.location.href,
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                location.reload(); // 重新載入頁面以顯示新頭像
                            },
                            error: function(xhr, status, error) {
                                alert('上傳失敗，請稍後再試！');
                            }
                        });

                        $('#cropper-modal').modal('hide');
                        if (cropper) {
                            cropper.destroy();
                            cropper = null;
                        }
                    }, 'image/jpeg', 0.8); // 設定輸出格式為 JPEG，品質 0.8
                }
            });

            // 關閉模態框時銷毀 Cropper
            $('#cropper-modal').on('hidden.bs.modal', function() {
                if (cropper) {
                    cropper.destroy();
                    cropper = null;
                }
                $('#uimage').val(''); // 清空檔案輸入
                $('#upload-button').prop('disabled', true);
            });
        });
    </script>
</body>

</html>
