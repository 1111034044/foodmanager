<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['uId'])) {
    header('Location: login.php');
    exit();
}
$uId = $_SESSION['uId'];
$today = date('Y-m-d');

// 取得熱量目標
$stmt = $db->prepare("SELECT calorie_goal, gender, age, height, weight FROM user_calorie_goal WHERE user_id=? ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$uId]);
$goal = $stmt->fetch(PDO::FETCH_ASSOC);

// 取得今日紀錄
$stmt = $db->prepare("SELECT * FROM calorie_records WHERE user_id=? AND record_date=? ORDER BY created_at DESC");
$stmt->execute([$uId, $today]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 計算今日總熱量
$total_cal = 0;
foreach ($records as $r) {
    $total_cal += $r['calorie'];
}
$goal_cal = $goal ? $goal['calorie_goal'] : 0;
$progress = $goal_cal ? min(100, round($total_cal / $goal_cal * 100)) : 0;
$over = $goal_cal && $total_cal > $goal_cal;
// 進度條顏色
if ($progress <= 50) {
    $progress_class = 'bg-success';
} elseif ($progress <= 80) {
    $progress_class = 'bg-warning';
} else {
    $progress_class = 'bg-danger';
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>熱量紀錄</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/boot.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/calorie_tracker.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="main-title">每日熱量紀錄</h2>
        <a href="calorie_review.php" class="btn btn-outline-primary">熱量回顧</a>
    </div>
    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <span class="fs-5">今日熱量目標：</span>
                <span class="fw-bold fs-4 text-primary" id="goalCalorie">
                    <?= $goal_cal ? $goal_cal . ' kcal' : '尚未設定' ?>
                </span>
            </div>
            <button class="btn btn-sm btn-main" data-bs-toggle="modal" data-bs-target="#goalModal">設定/修改目標</button>
        </div>
    </div>
    <div class="mb-4">
        <form id="foodForm" class="row g-2 align-items-end">
            <div class="col-md-8">
                <label for="foodName" class="form-label">食物名稱</label>
                <input type="text" class="form-control" id="foodName" name="food_name" required autocomplete="off" placeholder="請輸入或選擇食物">
            </div>
            <div class="col-md-2">
                <label for="quantity" class="form-label">數量</label>
                <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="1" required>
            </div>
            <div class="col-md-2">
                <label for="calorie" class="form-label">熱量 (kcal)</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="calorie" name="calorie" min="0" step="0.1" required placeholder="自動帶入或手動輸入">
                    <button type="button" class="btn btn-outline-secondary" id="queryCalorieBtn">查詢熱量</button>
                </div>
            </div>
            <div class="col-12 mt-2">
                <button type="submit" class="btn btn-main w-100">新增紀錄</button>
            </div>
        </form>
        <div id="foodSearchHint" class="form-text text-danger d-none">查無資料，請手動輸入熱量</div>
    </div>
    <div class="mb-4">
        <div class="mb-2">今日總熱量：<span class="fw-bold fs-5 text-<?= $over ? 'danger' : 'success' ?>"><?= $total_cal ?> kcal</span></div>
        <div class="progress" style="height: 30px;">
            <div class="progress-bar <?= $progress_class ?>" role="progressbar" style="width: <?= $progress ?>%;" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                <?= $progress ?>%
            </div>
        </div>
        <?php if ($over): ?>
            <div class="alert alert-danger mt-2">今日熱量已超標，建議多運動！</div>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="card-header">今日紀錄清單</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0 food-list">
                <thead class="table-light">
                    <tr>
                        <th>食物名稱</th>
                        <th>數量</th>
                        <th>熱量 (kcal)</th>
                        <th>時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="recordList">
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['food_name']) ?></td>
                        <td><?= isset($r['quantity']) ? $r['quantity'] : 1 ?></td>
                        <td><?= $r['calorie'] ?></td>
                        <td><?= date('H:i', strtotime($r['created_at'])) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $r['id'] ?>">刪除</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 熱量目標設定 Modal -->
<div class="modal fade" id="goalModal" tabindex="-1" aria-labelledby="goalModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="goalForm">
      <div class="modal-header">
        <h5 class="modal-title" id="goalModalLabel">設定每日熱量目標</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2 mb-2">
          <div class="col-md-6">
            <label class="form-label">性別</label>
            <select class="form-select" name="gender" id="gender">
              <option value="">--選擇--</option>
              <option value="male" <?= $goal && $goal['gender']==='male'?'selected':'' ?>>男</option>
              <option value="female" <?= $goal && $goal['gender']==='female'?'selected':'' ?>>女</option>
              <option value="other" <?= $goal && $goal['gender']==='other'?'selected':'' ?>>其他</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">年齡</label>
            <input type="number" class="form-control" name="age" id="age" min="1" max="120" value="<?= $goal ? $goal['age'] : '' ?>">
          </div>
        </div>
        <div class="row g-2 mb-2">
          <div class="col-md-6">
            <label class="form-label">身高 (cm)</label>
            <input type="number" class="form-control" name="height" id="height" min="50" max="250" value="<?= $goal ? $goal['height'] : '' ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">體重 (kg)</label>
            <input type="number" class="form-control" name="weight" id="weight" min="10" max="300" value="<?= $goal ? $goal['weight'] : '' ?>">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">建議熱量 (kcal)</label>
          <input type="number" class="form-control" name="calorie_goal" id="calorie_goal" min="1000" max="5000" required value="<?= $goal_cal ?>">
          <div class="form-text">根據性別、年齡、身高、體重自動建議（可手動修改）</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-main">儲存</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
// 單份熱量暫存
var singleKcal = null;

$('#foodName').autocomplete({
    source: function(request, response) {
        $.get('search_food_autocomplete.php', { term: request.term }, function(data) {
            response(data);
        }, 'json');
    },
    select: function(event, ui) {
        $('#foodName').val(ui.item.value);
        if (ui.item.kcal) {
            singleKcal = parseFloat(ui.item.kcal);
            var qty = parseFloat($('#quantity').val()) || 1;
            $('#calorie').val((singleKcal * qty).toFixed(1)).prop('readonly', true);
            $('#foodSearchHint').addClass('d-none');
        } else {
            singleKcal = null;
            $('#calorie').val('').prop('readonly', false);
            $('#foodSearchHint').removeClass('d-none');
        }
        return false;
    },
    minLength: 1
});
// 若直接輸入食物名稱，離開欄位時查詢本地資料庫
$('#foodName').on('blur', function() {
    var name = $(this).val().trim();
    if (!name) return;
    $.get('search_food_autocomplete.php', { term: name }, function(data) {
        if (data && data.length > 0 && data[0].kcal) {
            singleKcal = parseFloat(data[0].kcal);
            var qty = parseFloat($('#quantity').val()) || 1;
            $('#calorie').val((singleKcal * qty).toFixed(1)).prop('readonly', true);
            $('#foodSearchHint').addClass('d-none');
        } else {
            singleKcal = null;
            $('#calorie').val('').prop('readonly', false);
            $('#foodSearchHint').removeClass('d-none');
        }
    }, 'json');
});
// 數量變動時自動更新熱量
$('#quantity').on('input change', function() {
    var qty = parseFloat($(this).val()) || 1;
    if (singleKcal !== null) {
        $('#calorie').val((singleKcal * qty).toFixed(1));
    }
});
// 新增紀錄
$('#foodForm').on('submit', function(e) {
    e.preventDefault();
    $('#calorie').prop('readonly', false);
    $.post('add_calorie_record.php', $(this).serialize(), function() {
        location.reload();
    });
});
// 刪除紀錄
$('.delete-btn').on('click', function() {
    if (!confirm('確定要刪除這筆紀錄？')) return;
    var id = $(this).data('id');
    $.post('delete_calorie_record.php', { id: id }, function() {
        location.reload();
    });
});
// 目標設定自動建議
$('#gender, #age, #height, #weight').on('change blur', function() {
    var gender = $('#gender').val();
    var age = $('#age').val();
    var height = $('#height').val();
    var weight = $('#weight').val();
    if (gender && age && height && weight) {
        $.get('calorie_suggest_api.php', { gender: gender, age: age, height: height, weight: weight }, function(res) {
            if (res.suggest) {
                $('#calorie_goal').val(res.suggest);
            }
        }, 'json');
    }
});
// 目標設定送出
$('#goalForm').on('submit', function(e) {
    e.preventDefault();
    $.post('set_calorie_goal.php', $(this).serialize(), function() {
        location.reload();
    });
});
// 查詢熱量按鈕
$('#queryCalorieBtn').on('click', function() {
    // 僅保留按鈕存在，不執行任何查詢動作
    alert('此功能已暫停，請自行輸入熱量。');
});
</script>
</body>
</html> 