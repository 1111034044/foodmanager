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

$userId = $_SESSION['user_id'];
$nameKeyword = $_GET['name'] ?? '';
$ingredientKeyword = $_GET['ingredient'] ?? '';
$sort = $_GET['sort'] ?? '';

$sql = "
    SELECT DISTINCT r.*, u.uName, u.uImage, IFNULL(l.LikeCount, 0) AS LikeCount
    FROM recipe_favorites f
    JOIN Recipe r ON f.RecipeId = r.RecipeId
    JOIN user u ON r.uId = u.uId
    LEFT JOIN (
        SELECT RecipeId, COUNT(*) AS LikeCount
        FROM recipe_likes
        GROUP BY RecipeId
    ) l ON r.RecipeId = l.RecipeId
";

// 食材搜尋需要 LEFT JOIN recipeingredient
if (!empty($ingredientKeyword)) {
    $sql .= " JOIN recipeingredient ri ON r.RecipeId = ri.RecipeId ";
}

$sql .= " WHERE f.UserId = ? ";

// 搜尋食譜名稱
$params = [$userId];
$types = "i";

if (!empty($nameKeyword)) {
    $sql .= " AND r.rName LIKE ? ";
    $params[] = "%$nameKeyword%";
    $types .= "s";
}

// 搜尋食材名稱
if (!empty($ingredientKeyword)) {
    $sql .= " AND ri.IngredientName LIKE ? ";
    $params[] = "%$ingredientKeyword%";
    $types .= "s";
}

// 排序
switch ($sort) {
    case 'name':
        $sql .= " ORDER BY r.rName ASC ";
        break;
    case 'date':
        $sql .= " ORDER BY r.UploadDate DESC ";
        break;
    case 'likes':
        $sql .= " ORDER BY LikeCount DESC ";
        break;
    case 'views':
        $sql .= " ORDER BY r.ViewCount DESC ";
        break;
    default:
        $sql .= " ORDER BY f.FavoriteDate DESC ";
        break;
}

$stmt = $conn->prepare($sql);

// 動態綁定參數
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$favorites = [];
while ($row = $result->fetch_assoc()) {
    $favorites[] = $row;
}

$stmt->close();
$conn->close();
?>



<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <title>我的收藏食譜</title>
    <link rel="stylesheet" href="css/boot.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2>我的收藏</h2>
        <form method="GET" class="row g-3 align-items-center mb-4">
            <div class="col-md-3">
                <input type="text" name="name" class="form-control" placeholder="搜尋食譜名稱" value="<?= htmlspecialchars($_GET['name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="ingredient" class="form-control" placeholder="搜尋食材" value="<?= htmlspecialchars($_GET['ingredient'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <select name="sort" class="form-select">
                    <option value="">排序方式</option>
                    <option value="name" <?= ($_GET['sort'] ?? '') === 'name' ? 'selected' : '' ?>>名稱</option>
                    <option value="date" <?= ($_GET['sort'] ?? '') === 'date' ? 'selected' : '' ?>>上傳日期</option>
                    <option value="likes" <?= ($_GET['sort'] ?? '') === 'likes' ? 'selected' : '' ?>>喜歡數</option>
                    <option value="views" <?= ($_GET['sort'] ?? '') === 'views' ? 'selected' : '' ?>>瀏覽數</option>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </form>

        <?php if (empty($favorites)): ?>
            <p>你目前尚未收藏任何食譜。</p>
        <?php else: ?>
            <div class="row">

                <?php foreach ($favorites as $recipe): ?>
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
                                <img src="<?= $recipe['CoverImage'] ?: 'images/recipe-placeholder.jpg'; ?>" class="card-img-top" alt="食譜封面" style="height:200px;object-fit:cover;">
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($recipe['rName']); ?></h5>
                                    <p class="card-text"><?= htmlspecialchars(mb_substr($recipe['Description'], 0, 50)) . '...'; ?></p>
                                    <p class="card-text">
                                        <small class="text-muted">難度：<?= htmlspecialchars($recipe['DifficultyLevel']); ?></small>
                                    </p>
                                    <p class="card-text">
                                        <small class="text-muted">預計用時：<?php echo $recipe['cooktime'] ? $recipe['cooktime'] . ' 分鐘' : '未指定'; ?></small>
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
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
