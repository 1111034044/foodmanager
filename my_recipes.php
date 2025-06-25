<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "foodmanager");
if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'];

$query = "
    SELECT RecipeId, rName, CoverImage, Description, UploadDate
    FROM Recipe
    WHERE uId = ?
    ORDER BY UploadDate DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$recipes = [];
while ($row = $result->fetch_assoc()) {
    $recipes[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>我的食譜</title>
    <link rel="stylesheet" href="css/boot.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>我上傳的食譜</h2>
            <a href="add_recipe.php" class="btn custom-btn-upload">
                <i class="bi bi-plus-circle me-2"></i>上傳食譜
            </a>
        </div>
        
        <?php if (empty($recipes)): ?>
            <div class="text-center py-5">
                <p class="mb-3">你尚未上傳任何食譜。</p>
                <a href="add_recipe.php" class="btn custom-btn-upload">
                    <i class="bi bi-plus-circle me-2"></i>開始上傳你的第一道食譜
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($recipes as $recipe): ?>
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <a href="recipe_detail.php?RecipeId=<?php echo $recipe['RecipeId']; ?>" class="text-decoration-none">
                                <img src="<?php echo htmlspecialchars($recipe['CoverImage'] ?? 'img/recipe-placeholder.jpg'); ?>" class="card-img-top" alt="封面">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($recipe['rName']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($recipe['Description']); ?></p>
                                </div>
                            </a>
                            <div class="card-footer d-flex justify-content-between">
                                <form action="edit_recipe.php" method="GET" style="display:inline;">
                                    <input type="hidden" name="RecipeId" value="<?php echo $recipe['RecipeId']; ?>">
                                    <button type="submit" class="btn me-1 border border-white"
                                    style="background-color:rgb(125, 188, 255);">
                                        <i class="bi bi-pencil text-dark"></i>
                                    </button>
                                </form>

                                <form action="delete_recipe.php" method="POST" style="display:inline;" onsubmit="return confirm('確定要刪除這道食譜嗎？');">
                                    <input type="hidden" name="RecipeId" value="<?php echo $recipe['RecipeId']; ?>">
                                    <button type="submit" class="btn btn-danger action-btn">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: scale(1.05);
        }

        .card-img-top {
            height: 200px;
            object-fit: cover;
        }

        .card-footer {
            background-color: #f8f9fa;
        }

        /* 上傳食譜按鈕樣式 */
        .custom-btn-upload {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .custom-btn-upload:hover {
            background-color: #218838;
            border-color: #1e7e34;
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }
    </style>
</body>
</html>
