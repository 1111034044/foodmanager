<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['uId'])) {
    header('Location: login.php');
    exit();
}
$uId = $_SESSION['uId'];

// 取得查詢區間
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

// 取得目標熱量
$stmt = $db->prepare("SELECT calorie_goal FROM user_calorie_goal WHERE user_id=? ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$uId]);
$goal = $stmt->fetch();
$goal_cal = $goal ? $goal['calorie_goal'] : 0;

// 取得區間熱量紀錄
$stmt = $db->prepare("SELECT record_date, SUM(calorie) AS total FROM calorie_records WHERE user_id=? AND record_date BETWEEN ? AND ? GROUP BY record_date ORDER BY record_date");
$stmt->execute([$uId, $start, $end]);
$data = $stmt->fetchAll();
$labels = [];
$values = [];
foreach ($data as $row) {
    $labels[] = $row['record_date'];
    $values[] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>熱量回顧</title>
    <link rel="stylesheet" href="css/boot.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>熱量回顧</h2>
        <a href="calorie_tracker.php" class="btn btn-outline-secondary">返回紀錄</a>
    </div>
    <form class="row g-2 mb-3 align-items-end" method="get">
        <div class="col-auto">
            <label class="form-label">起始日</label>
            <input type="date" class="form-control" name="start" value="<?= htmlspecialchars($start) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label">結束日</label>
            <input type="date" class="form-control" name="end" value="<?= htmlspecialchars($end) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">查詢</button>
        </div>
        <div class="col-auto">
            <a href="?start=<?= date('Y-m-d', strtotime('-6 days')) ?>&end=<?= date('Y-m-d') ?>" class="btn btn-outline-info">本週</a>
            <a href="?start=<?= date('Y-m-01') ?>&end=<?= date('Y-m-d') ?>" class="btn btn-outline-info">本月</a>
        </div>
    </form>
    <div class="card mb-4">
        <div class="card-body">
            <canvas id="calorieChart" height="100"></canvas>
        </div>
    </div>
    <div class="card">
        <div class="card-header">每日詳細</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>日期</th><th>總熱量</th><th>狀態</th><th>操作</th></tr>
                </thead>
                <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?= $row['record_date'] ?></td>
                        <td><?= $row['total'] ?> kcal</td>
                        <td><?= $goal_cal && $row['total'] > $goal_cal ? '<span class="text-danger">超標</span>' : '<span class="text-success">正常</span>' ?></td>
                        <td><a href="?start=<?= $row['record_date'] ?>&end=<?= $row['record_date'] ?>" class="btn btn-sm btn-outline-primary">查看</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
const ctx = document.getElementById('calorieChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: '每日總熱量',
            data: <?= json_encode($values) ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.1)',
            fill: true,
            tension: 0.3
        }
        <?php if ($goal_cal): ?>,
        {
            label: '目標熱量',
            data: Array(<?= count($labels) ?>).fill(<?= $goal_cal ?>),
            borderColor: '#dc3545',
            borderDash: [5,5],
            pointRadius: 0,
            fill: false
        }
        <?php endif; ?>
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'top' },
            title: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>
</body>
</html> 