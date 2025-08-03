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

// 取得營養素目標
$stmt = $db->prepare("SELECT protein_goal, fat_goal, carb_goal, fiber_goal FROM user_nutrition_goal WHERE user_id=? ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$uId]);
$nutrition_goal = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <!-- apexcharts -->
    <link href="css/apexcharts.css" rel="stylesheet">
    <script src="js/apexcharts.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="main-title">每日熱量紀錄</h2>
        <div>
            <button class="btn btn-outline-info me-2" data-bs-toggle="modal" data-bs-target="#nutritionChartModal">
                <i class="bi bi-pie-chart"></i> 營養素圖表
            </button>
            <button class="btn btn-outline-success me-2" data-bs-toggle="modal" data-bs-target="#mealRecommendModal">
                <i class="bi bi-lightbulb"></i> 推薦餐點
            </button>
            <a href="calorie_review.php" class="btn btn-outline-primary">熱量回顧</a>
        </div>
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
                        <th>蛋白質 (g)</th>
                        <th>脂肪 (g)</th>
                        <th>碳水 (g)</th>
                        <th>纖維 (g)</th>
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
                        <td><?= isset($r['protein']) ? number_format($r['protein'], 1) : '-' ?></td>
                        <td><?= isset($r['fat']) ? number_format($r['fat'], 1) : '-' ?></td>
                        <td><?= isset($r['carb']) ? number_format($r['carb'], 1) : '-' ?></td>
                        <td><?= isset($r['fiber']) ? number_format($r['fiber'], 1) : '-' ?></td>
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
        
        <!-- 營養素目標設定 -->
        <div class="mb-2">
          <label class="form-label">營養素目標設定</label>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">蛋白質目標 (g)</label>
              <input type="number" class="form-control" name="protein_goal" id="protein_goal" min="0" step="0.1" value="<?= $nutrition_goal ? $nutrition_goal['protein_goal'] : 0 ?>">
              <div class="form-text">建議：<span id="suggestProtein">-</span>g</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">脂肪目標 (g)</label>
              <input type="number" class="form-control" name="fat_goal" id="fat_goal" min="0" step="0.1" value="<?= $nutrition_goal ? $nutrition_goal['fat_goal'] : 0 ?>">
              <div class="form-text">建議：<span id="suggestFat">-</span>g</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">碳水化合物目標 (g)</label>
              <input type="number" class="form-control" name="carb_goal" id="carb_goal" min="0" step="0.1" value="<?= $nutrition_goal ? $nutrition_goal['carb_goal'] : 0 ?>">
              <div class="form-text">建議：<span id="suggestCarb">-</span>g</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">膳食纖維目標 (g)</label>
              <input type="number" class="form-control" name="fiber_goal" id="fiber_goal" min="0" step="0.1" value="<?= $nutrition_goal ? $nutrition_goal['fiber_goal'] : 0 ?>">
              <div class="form-text">建議：<span id="suggestFiber">-</span>g</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-main">儲存</button>
      </div>
    </form>
  </div>
</div>

<!-- 營養素圖表 Modal -->
<div class="modal fade" id="nutritionChartModal" tabindex="-1" aria-labelledby="nutritionChartModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="nutritionChartModalLabel">今日營養素達標情況</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <div id="proteinChart"></div>
          </div>
          <div class="col-md-6">
            <div id="fatChart"></div>
          </div>
        </div>
        <div class="row mt-3">
          <div class="col-md-6">
            <div id="carbChart"></div>
          </div>
          <div class="col-md-6">
            <div id="fiberChart"></div>
          </div>
        </div>
        <div class="row mt-3">
          <div class="col-12">
            <div class="table-responsive">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>營養素</th>
                    <th>今日攝取</th>
                    <th>目標</th>
                    <th>完成度</th>
                  </tr>
                </thead>
                <tbody id="nutritionTable">
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 推薦餐點 Modal -->
<div class="modal fade" id="mealRecommendModal" tabindex="-1" aria-labelledby="mealRecommendModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mealRecommendModalLabel">營養均衡餐點推薦</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">選擇餐點類型</label>
            <select class="form-select" id="mealType">
              <option value="">請選擇餐點類型</option>
              <option value="breakfast">早餐</option>
              <option value="lunch">午餐</option>
              <option value="dinner">晚餐</option>
              <option value="snack">點心</option>
            </select>
          </div>
          <div class="col-md-6">
            <button class="btn btn-success mt-4" id="getRecommendBtn" disabled>
              <i class="bi bi-search"></i> 取得推薦
            </button>
          </div>
        </div>
        
        <div id="recommendResult" class="d-none">
          <div class="alert alert-info">
            <h6><i class="bi bi-info-circle"></i> 推薦理由</h6>
            <p id="recommendReason" class="mb-0"></p>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="card">
                <div class="card-header">
                  <h6 class="mb-0"><i class="bi bi-utensils"></i> 推薦餐點</h6>
                </div>
                <div class="card-body">
                  <h5 id="mealName" class="text-primary"></h5>
                  <p id="mealDescription" class="text-muted"></p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card">
                <div class="card-header">
                  <h6 class="mb-0"><i class="bi bi-pie-chart"></i> 營養素分布</h6>
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-6">
                      <small class="text-muted">熱量</small>
                      <div class="fw-bold" id="mealCalorie">-</div>
                    </div>
                    <div class="col-6">
                      <small class="text-muted">蛋白質</small>
                      <div class="fw-bold" id="mealProtein">-</div>
                    </div>
                    <div class="col-6">
                      <small class="text-muted">脂肪</small>
                      <div class="fw-bold" id="mealFat">-</div>
                    </div>
                    <div class="col-6">
                      <small class="text-muted">碳水</small>
                      <div class="fw-bold" id="mealCarb">-</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="card mt-3">
            <div class="card-header">
              <h6 class="mb-0"><i class="bi bi-journal-text"></i> 食譜建議</h6>
            </div>
            <div class="card-body">
              <div id="mealRecipe"></div>
            </div>
          </div>
          
          <div class="text-center mt-3">
            <button class="btn btn-primary me-2" id="addToRecordBtn">
              <i class="bi bi-plus-circle"></i> 一鍵新增到紀錄
            </button>
            <button class="btn btn-outline-secondary" id="getAnotherBtn">
              <i class="bi bi-arrow-clockwise"></i> 換一個
            </button>
          </div>
        </div>
        
        <div id="loadingRecommend" class="text-center d-none">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">載入中...</span>
          </div>
          <p class="mt-2">正在分析營養需求並生成推薦...</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="search_calories.js"></script>
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
    
    var foodName = $('#foodName').val().trim();
    var quantity = $('#quantity').val();
    var calorie = $('#calorie').val();
    
    // 先查詢營養素
    $.get('nutrition_analysis_api.php', { food: foodName, quantity: quantity }, function(data) {
        if (data.success && data.nutrition) {
            // 將營養素資料加入表單
            var formData = $('#foodForm').serialize();
            formData += '&protein=' + (data.nutrition.protein || 0);
            formData += '&fat=' + (data.nutrition.fat || 0);
            formData += '&carb=' + (data.nutrition.carb || 0);
            formData += '&fiber=' + (data.nutrition.fiber || 0);
            formData += '&vitamin=' + encodeURIComponent(data.nutrition.vitamin || '');
            formData += '&mineral=' + encodeURIComponent(data.nutrition.mineral || '');
            
            $.post('add_calorie_record.php', formData, function() {
                location.reload();
            });
        } else {
            // 如果營養素查詢失敗，仍然新增紀錄（只有熱量）
            $.post('add_calorie_record.php', $('#foodForm').serialize(), function() {
                location.reload();
            });
        }
    }).fail(function() {
        // API 呼叫失敗時，仍然新增紀錄
        $.post('add_calorie_record.php', $('#foodForm').serialize(), function() {
            location.reload();
        });
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
        // 查詢熱量建議
        $.get('calorie_suggest_api.php', { gender: gender, age: age, height: height, weight: weight }, function(res) {
            if (res.suggest) {
                $('#calorie_goal').val(res.suggest);
            }
        }, 'json');
        
        // 查詢營養素建議
        $.get('nutrition_suggest_api.php', { gender: gender, age: age, height: height, weight: weight }, function(data) {
            if (data.success) {
                $('#suggestProtein').text(data.suggestions.protein);
                $('#suggestFat').text(data.suggestions.fat);
                $('#suggestCarb').text(data.suggestions.carb);
                $('#suggestFiber').text(data.suggestions.fiber);
                
                // 如果營養素目標欄位是空的，自動填入建議值
                if ($('#protein_goal').val() == 0) $('#protein_goal').val(data.suggestions.protein);
                if ($('#fat_goal').val() == 0) $('#fat_goal').val(data.suggestions.fat);
                if ($('#carb_goal').val() == 0) $('#carb_goal').val(data.suggestions.carb);
                if ($('#fiber_goal').val() == 0) $('#fiber_goal').val(data.suggestions.fiber);
            }
        }, 'json');
    }
});

// 頁面載入時也查詢營養素建議
$(document).ready(function() {
    var gender = $('#gender').val();
    var age = $('#age').val();
    var height = $('#height').val();
    var weight = $('#weight').val();
    
    if (gender && age && height && weight) {
        $.get('nutrition_suggest_api.php', { gender: gender, age: age, height: height, weight: weight }, function(data) {
            if (data.success) {
                $('#suggestProtein').text(data.suggestions.protein);
                $('#suggestFat').text(data.suggestions.fat);
                $('#suggestCarb').text(data.suggestions.carb);
                $('#suggestFiber').text(data.suggestions.fiber);
                
                // 如果營養素目標欄位是空的，自動填入建議值
                if ($('#protein_goal').val() == 0) $('#protein_goal').val(data.suggestions.protein);
                if ($('#fat_goal').val() == 0) $('#fat_goal').val(data.suggestions.fat);
                if ($('#carb_goal').val() == 0) $('#carb_goal').val(data.suggestions.carb);
                if ($('#fiber_goal').val() == 0) $('#fiber_goal').val(data.suggestions.fiber);
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
    var food = $('#foodName').val().trim();
    var qty = $('#quantity').val().trim();
    queryCalorieByGPT(food, qty, '#queryCalorieBtn', '#calorie', '#foodSearchHint');
});

// 營養素圖表功能
$('#nutritionChartModal').on('shown.bs.modal', function() {
    loadNutritionCharts();
});

function loadNutritionCharts() {
    $.get('nutrition_chart_api.php', function(data) {
        if (data.success) {
            createNutritionCharts(data.today, data.goal);
            updateNutritionTable(data.today, data.goal);
        } else {
            alert('載入營養素資料失敗');
        }
    }).fail(function() {
        alert('載入營養素資料失敗');
    });
}

function createNutritionCharts(today, goal) {
    const chartOptions = {
        chart: {
            type: 'donut',
            height: 200
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%'
                }
            }
        },
        colors: ['#00E396', '#FEB019', '#FF4560', '#775DD0'],
        legend: {
            position: 'bottom'
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return val.toFixed(1) + 'g';
                }
            }
        }
    };

    // 蛋白質圖表
    const proteinPercent = goal.protein > 0 ? Math.min(100, (today.protein / goal.protein) * 100) : 0;
    const proteinChart = new ApexCharts(document.querySelector("#proteinChart"), {
        ...chartOptions,
        series: [today.protein, Math.max(0, goal.protein - today.protein)],
        labels: ['已攝取', '剩餘'],
        title: {
            text: '蛋白質',
            align: 'center',
            style: {
                fontSize: '16px'
            }
        },
        subtitle: {
            text: today.protein.toFixed(1) + 'g / ' + goal.protein.toFixed(1) + 'g',
            align: 'center',
            style: {
                fontSize: '12px'
            }
        }
    });
    proteinChart.render();

    // 脂肪圖表
    const fatPercent = goal.fat > 0 ? Math.min(100, (today.fat / goal.fat) * 100) : 0;
    const fatChart = new ApexCharts(document.querySelector("#fatChart"), {
        ...chartOptions,
        series: [today.fat, Math.max(0, goal.fat - today.fat)],
        labels: ['已攝取', '剩餘'],
        title: {
            text: '脂肪',
            align: 'center',
            style: {
                fontSize: '16px'
            }
        },
        subtitle: {
            text: today.fat.toFixed(1) + 'g / ' + goal.fat.toFixed(1) + 'g',
            align: 'center',
            style: {
                fontSize: '12px'
            }
        }
    });
    fatChart.render();

    // 碳水化合物圖表
    const carbPercent = goal.carb > 0 ? Math.min(100, (today.carb / goal.carb) * 100) : 0;
    const carbChart = new ApexCharts(document.querySelector("#carbChart"), {
        ...chartOptions,
        series: [today.carb, Math.max(0, goal.carb - today.carb)],
        labels: ['已攝取', '剩餘'],
        title: {
            text: '碳水化合物',
            align: 'center',
            style: {
                fontSize: '16px'
            }
        },
        subtitle: {
            text: today.carb.toFixed(1) + 'g / ' + goal.carb.toFixed(1) + 'g',
            align: 'center',
            style: {
                fontSize: '12px'
            }
        }
    });
    carbChart.render();

    // 膳食纖維圖表
    const fiberPercent = goal.fiber > 0 ? Math.min(100, (today.fiber / goal.fiber) * 100) : 0;
    const fiberChart = new ApexCharts(document.querySelector("#fiberChart"), {
        ...chartOptions,
        series: [today.fiber, Math.max(0, goal.fiber - today.fiber)],
        labels: ['已攝取', '剩餘'],
        title: {
            text: '膳食纖維',
            align: 'center',
            style: {
                fontSize: '16px'
            }
        },
        subtitle: {
            text: today.fiber.toFixed(1) + 'g / ' + goal.fiber.toFixed(1) + 'g',
            align: 'center',
            style: {
                fontSize: '12px'
            }
        }
    });
    fiberChart.render();
}

function updateNutritionTable(today, goal) {
    const nutritionData = [
        { name: '蛋白質', today: today.protein, goal: goal.protein, color: '#00E396' },
        { name: '脂肪', today: today.fat, goal: goal.fat, color: '#FEB019' },
        { name: '碳水化合物', today: today.carb, goal: goal.carb, color: '#FF4560' },
        { name: '膳食纖維', today: today.fiber, goal: goal.fiber, color: '#775DD0' }
    ];

    let tableHtml = '';
    nutritionData.forEach(item => {
        const percent = item.goal > 0 ? Math.min(100, (item.today / item.goal) * 100) : 0;
        const progressClass = percent >= 100 ? 'bg-success' : percent >= 80 ? 'bg-warning' : 'bg-info';
        
        tableHtml += `
            <tr>
                <td><span style="color: ${item.color}">●</span> ${item.name}</td>
                <td>${item.today.toFixed(1)}g</td>
                <td>${item.goal.toFixed(1)}g</td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar ${progressClass}" style="width: ${percent}%">
                            ${percent.toFixed(1)}%
                        </div>
                    </div>
                </td>
            </tr>
        `;
    });
    
    $('#nutritionTable').html(tableHtml);
}

// 推薦餐點功能
let currentRecommendation = null;

// 餐點類型選擇
$('#mealType').on('change', function() {
    $('#getRecommendBtn').prop('disabled', !$(this).val());
});

// 取得推薦
$('#getRecommendBtn').on('click', function() {
    const mealType = $('#mealType').val();
    if (!mealType) return;
    
    $('#loadingRecommend').removeClass('d-none');
    $('#recommendResult').addClass('d-none');
    
    $.get('meal_recommend_api.php', { meal_type: mealType }, function(data) {
        $('#loadingRecommend').addClass('d-none');
        
        if (data.success && data.meal) {
            currentRecommendation = data.meal;
            displayRecommendation(data.meal);
            $('#recommendResult').removeClass('d-none');
        } else {
            alert('取得推薦失敗，請稍後再試');
        }
    }).fail(function() {
        $('#loadingRecommend').addClass('d-none');
        alert('取得推薦失敗，請稍後再試');
    });
});

function displayRecommendation(meal) {
    $('#mealName').text(meal.meal_name);
    $('#mealDescription').text(meal.description);
    $('#mealCalorie').text(meal.calorie + ' kcal');
    $('#mealProtein').text(meal.protein + 'g');
    $('#mealFat').text(meal.fat + 'g');
    $('#mealCarb').text(meal.carb + 'g');
    $('#recommendReason').text(meal.reason);
    $('#mealRecipe').html(meal.recipe.replace(/\n/g, '<br>'));
}

// 一鍵新增到紀錄
$('#addToRecordBtn').on('click', function() {
    if (!currentRecommendation) return;
    
    // 自動填入表單
    $('#foodName').val(currentRecommendation.meal_name);
    $('#quantity').val(1);
    $('#calorie').val(currentRecommendation.calorie);
    
    // 關閉 Modal
    $('#mealRecommendModal').modal('hide');
    
    // 顯示提示
    alert('已自動填入推薦餐點，請確認後點擊「新增紀錄」');
});

// 換一個推薦
$('#getAnotherBtn').on('click', function() {
    const mealType = $('#mealType').val();
    if (!mealType) return;
    
    $('#loadingRecommend').removeClass('d-none');
    $('#recommendResult').addClass('d-none');
    
    $.get('meal_recommend_api.php', { meal_type: mealType, retry: 1 }, function(data) {
        $('#loadingRecommend').addClass('d-none');
        
        if (data.success && data.meal) {
            currentRecommendation = data.meal;
            displayRecommendation(data.meal);
            $('#recommendResult').removeClass('d-none');
        } else {
            alert('取得推薦失敗，請稍後再試');
        }
    }).fail(function() {
        $('#loadingRecommend').addClass('d-none');
        alert('取得推薦失敗，請稍後再試');
    });
});
</script>
</body>
</html> 
