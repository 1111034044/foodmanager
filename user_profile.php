<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "foodmanager");
if ($conn->connect_error) {
    die("\u9023\u7dda\u5931\u6557: " . $conn->connect_error);
}

$authorId = $_GET['uid'] ?? 0;

// 取得作者資訊與食譜總數
$stmt = $conn->prepare("SELECT uName, uImage, (SELECT COUNT(*) FROM Recipe WHERE uId = ?) AS RecipeCount FROM user WHERE uId = ?");
$stmt->bind_param("ii", $authorId, $authorId);
$stmt->execute();
$stmt->bind_result($authorName, $authorImage, $authorRecipeCount);
$stmt->fetch();
$stmt->close();

if (!$authorName) {
    echo "<p>找不到該作者。</p>";
    exit();
}

$stmt = $conn->prepare("SELECT r.*, IFNULL(l.LikeCount, 0) AS LikeCount FROM Recipe r LEFT JOIN (SELECT RecipeId, COUNT(*) AS LikeCount FROM recipe_likes GROUP BY RecipeId) l ON r.RecipeId = l.RecipeId WHERE r.uId = ? ORDER BY r.UploadDate DESC");
$stmt->bind_param("i", $authorId);
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
    <title><?php echo htmlspecialchars($authorName); ?>的食譜</title>
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
        <!-- 作者區塊 -->
        <div class="mt-4 p-3 bg-white rounded shadow-sm d-flex align-items-center">
            <img src="<?php echo htmlspecialchars($authorImage ?: 'img/user-placeholder.jpg'); ?>" class="rounded-circle me-3" alt="頭像" width="50" height="50">
            <div>
                <span class="fs-5 fw-semibold d-block"><?php echo htmlspecialchars($authorName); ?></span>
                <small class="text-muted"><?php echo $authorRecipeCount; ?> 道食譜</small>
            </div>
        </div>

        <!-- 食譜列表 -->
        <div class="row mt-4">
            <?php if (empty($recipes)): ?>
                <p class="mt-3">目前沒有任何食譜。</p>
            <?php else: ?>
                <?php foreach ($recipes as $recipe): ?>
                    <?php
                    $liked = false;
                    $favorited = false;
                    $likeCount = isset($recipe['LikeCount']) ? (int)$recipe['LikeCount'] : 0;

                    if (isset($_SESSION['user_id'], $recipe['RecipeId']) && $conn) {
                        $uid = (int)$_SESSION['user_id'];
                        $rid = (int)$recipe['RecipeId'];

                        // 檢查是否已按讚
                        $stmt_like = $conn->prepare("SELECT 1 FROM recipe_likes WHERE RecipeId = ? AND UserId = ?");
                        if ($stmt_like) {
                            $stmt_like->bind_param("ii", $rid, $uid);
                            $stmt_like->execute();
                            $stmt_like->store_result();
                            $liked = $stmt_like->num_rows > 0;
                            $stmt_like->close();
                        }

                        // 檢查是否已收藏
                        $stmt_fav = $conn->prepare("SELECT 1 FROM recipe_favorites WHERE RecipeId = ? AND UserId = ?");
                        if ($stmt_fav) {
                            $stmt_fav->bind_param("ii", $rid, $uid);
                            $stmt_fav->execute();
                            $stmt_fav->store_result();
                            $favorited = $stmt_fav->num_rows > 0;
                            $stmt_fav->close();
                        }
                    }
                    ?>
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <a href="recipe_detail.php?RecipeId=<?= $recipe['RecipeId']; ?>" class="text-decoration-none">
                                <img src="<?= $recipe['CoverImage'] ?: 'images/recipe-placeholder.jpg'; ?>" class="card-img-top" alt="封面" style="height:200px;object-fit:cover;">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($recipe['rName']); ?></h5>
                                    <p class="card-text"><?= htmlspecialchars(mb_substr($recipe['Description'], 0, 50)) . '...'; ?></p>
                                    <p class="card-text">
                                        <small class="text-muted">難度：<?= htmlspecialchars($recipe['DifficultyLevel']); ?></small>
                                    </p>
                                    <p class="card-text">
                                        <small class="text-muted">用時：<?= $recipe['cooktime'] ? $recipe['cooktime'] . ' 分鐘' : '未指定'; ?></small>
                                    </p>
                                </div>
                            </a>
                            <div class="card-footer d-flex justify-content-between align-items-center" style="background-color: #fffaf3; border-top: 1px solid #f0e6d2;">
                                <!-- 左邊：瀏覽數 -->
                                <div class="d-flex align-items-center" style="color: #555; font-size: 1.2rem;">
                                    <i class="bi bi-eye"></i>
                                    <span style="margin-left: 5px;"><?php echo htmlspecialchars($recipe['ViewCount'] ?? 0); ?></span>
                                </div>

                                <!-- 右邊：喜歡和取消收藏 -->
                                <div class="d-flex align-items-center">
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <!-- 喜歡 -->
                                        <form id="like-form-<?php echo $recipe['RecipeId']; ?>" action="toggle_like.php" method="POST" style="margin: 0;">
                                            <input type="hidden" name="RecipeId" value="<?php echo $recipe['RecipeId']; ?>">
                                            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] . '#like-form-' . $recipe['RecipeId']); ?>">
                                            <button type="submit" class="btn btn-link p-0" style="font-size: 1.5rem; color: <?php echo $liked ? 'red' : 'gray'; ?>;">
                                                <?php echo $liked ? '❤️' : '🤍'; ?>
                                            </button>
                                            <span style="margin-left: 5px; color: #555;"><?php echo $likeCount; ?></span>
                                        </form>
                                    <?php endif; ?>

                                    <!-- 收藏取消（藍色書籤） -->
                                    <form id="favorite-form-<?php echo $recipe['RecipeId']; ?>" action="toggle_favorite.php" method="POST" style="margin-left: 10px;">
                                        <input type="hidden" name="RecipeId" value="<?php echo $recipe['RecipeId']; ?>">
                                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] . '#favorite-form-' . $recipe['RecipeId']); ?>">
                                        <button type="submit" class="btn btn-link favorite-btn" title="取消收藏">
                                            <i class="bi bi-bookmark-fill" style="color: #0d6efd; font-size: 1.5rem;"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
