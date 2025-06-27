<?php
session_start();

// 檢查用戶是否已登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 建立資料庫連線
$conn = new mysqli("localhost", "root", "", "foodmanager");

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

// 從 session 中獲取 uId
$uId = $_SESSION['user_id'];

// 獲取選擇的月份，預設為當前月份
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

// 查詢儲存類型分布
$storeTypeQuery = "SELECT 
                    CASE 
                        WHEN StoreType IS NULL OR StoreType = '' THEN '無' 
                        ELSE StoreType 
                    END AS StoreType, 
                    COUNT(*) AS Count 
                  FROM Ingredient 
                  WHERE uId = ? AND Quantity > 0 
                  GROUP BY StoreType";

$stmt = $conn->prepare($storeTypeQuery);
$stmt->bind_param("i", $uId);
$stmt->execute();
$storeTypeResult = $stmt->get_result();

$storeTypeData = [];
while ($row = $storeTypeResult->fetch_assoc()) {
    $storeTypeData[] = $row;
}

// 查詢每個儲存類型下的具體食材名稱、數量和單位
$ingredientsByTypeQuery = "SELECT 
                            IName, 
                            Quantity, 
                            Unit,
                            CASE 
                                WHEN StoreType IS NULL OR StoreType = '' THEN '無' 
                                ELSE StoreType 
                            END AS StoreType 
                          FROM Ingredient 
                          WHERE uId = ? AND Quantity > 0 
                          ORDER BY StoreType, IName";

$stmt = $conn->prepare($ingredientsByTypeQuery);
$stmt->bind_param("i", $uId);
$stmt->execute();
$ingredientsByTypeResult = $stmt->get_result();

$ingredientsByType = [];
while ($row = $ingredientsByTypeResult->fetch_assoc()) {
    if (!isset($ingredientsByType[$row['StoreType']])) {
        $ingredientsByType[$row['StoreType']] = [];
    }
    $ingredientsByType[$row['StoreType']][] = [
        'name' => $row['IName'],
        'quantity' => $row['Quantity'],
        'unit' => $row['Unit'] ?: '個'
    ];
}

// 查詢購買食材數據（按月份）
$purchaseQuery = "SELECT 
                    CASE 
                        WHEN StoreType IS NULL OR StoreType = '' THEN '無' 
                        ELSE StoreType 
                    END AS StoreType, 
                    COUNT(*) AS Count 
                  FROM Ingredient 
                  WHERE uId = ? AND PurchaseDate BETWEEN ? AND ? 
                  GROUP BY StoreType";

$stmt = $conn->prepare($purchaseQuery);
$stmt->bind_param("iss", $uId, $monthStart, $monthEnd);
$stmt->execute();
$purchaseResult = $stmt->get_result();

$purchaseData = [];
while ($row = $purchaseResult->fetch_assoc()) {
    $purchaseData[] = $row;
}

// 查詢每個儲存類型下的購買食材詳細資料
$purchaseDetailQuery = "SELECT 
                          IName, 
                          Quantity, 
                          Unit,
                          CASE 
                              WHEN StoreType IS NULL OR StoreType = '' THEN '無' 
                              ELSE StoreType 
                          END AS StoreType 
                        FROM Ingredient 
                        WHERE uId = ? AND PurchaseDate BETWEEN ? AND ? 
                        ORDER BY StoreType, IName";

$stmt = $conn->prepare($purchaseDetailQuery);
$stmt->bind_param("iss", $uId, $monthStart, $monthEnd);
$stmt->execute();
$purchaseDetailResult = $stmt->get_result();

$purchaseDetailData = [];
while ($row = $purchaseDetailResult->fetch_assoc()) {
    if (!isset($purchaseDetailData[$row['StoreType']])) {
        $purchaseDetailData[$row['StoreType']] = [];
    }
    $purchaseDetailData[$row['StoreType']][] = [
        'name' => $row['IName'],
        'quantity' => $row['Quantity'],
        'unit' => $row['Unit'] ?: '個'
    ];
}

// 查詢過期食材數據（按月份）
$expiredQuery = "SELECT 
                    CASE 
                        WHEN StoreType IS NULL OR StoreType = '' THEN '無' 
                        ELSE StoreType 
                    END AS StoreType, 
                    COUNT(*) AS Count 
                  FROM Ingredient 
                  WHERE uId = ? AND ExpireDate BETWEEN ? AND ? 
                  GROUP BY StoreType";

$stmt = $conn->prepare($expiredQuery);
$stmt->bind_param("iss", $uId, $monthStart, $monthEnd);
$stmt->execute();
$expiredResult = $stmt->get_result();

$expiredData = [];
while ($row = $expiredResult->fetch_assoc()) {
    $expiredData[] = $row;
}

// 查詢每個儲存類型下的過期食材詳細資料
$expiredDetailQuery = "SELECT 
                          IName, 
                          Quantity, 
                          Unit,
                          CASE 
                              WHEN StoreType IS NULL OR StoreType = '' THEN '無' 
                              ELSE StoreType 
                          END AS StoreType 
                        FROM Ingredient 
                        WHERE uId = ? AND ExpireDate BETWEEN ? AND ? 
                        ORDER BY StoreType, IName";

$stmt = $conn->prepare($expiredDetailQuery);
$stmt->bind_param("iss", $uId, $monthStart, $monthEnd);
$stmt->execute();
$expiredDetailResult = $stmt->get_result();

$expiredDetailData = [];
while ($row = $expiredDetailResult->fetch_assoc()) {
    if (!isset($expiredDetailData[$row['StoreType']])) {
        $expiredDetailData[$row['StoreType']] = [];
    }
    $expiredDetailData[$row['StoreType']][] = [
        'name' => $row['IName'],
        'quantity' => $row['Quantity'],
        'unit' => $row['Unit'] ?: '個'
    ];
}

// 關閉資料庫連線
$stmt->close();
$conn->close();

// 將數據轉換為 JSON 格式，以便在 JavaScript 中使用
$storeTypeDataJSON = json_encode($storeTypeData);
$purchaseDataJSON = json_encode($purchaseData);
$expiredDataJSON = json_encode($expiredData);
$ingredientsByTypeJSON = json_encode($ingredientsByType);
$purchaseDetailDataJSON = json_encode($purchaseDetailData);
$expiredDetailDataJSON = json_encode($expiredDetailData);
?>

<!DOCTYPE html>
<html lang="zh-Hant">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>食材分析圖表</title>
    <link rel="stylesheet" href="css/boot.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
        }
        .card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8dada;
            border-bottom: 1px solid #f0e6d2;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }
        .card-body {
            padding: 20px;
        }
        .month-selector {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <!-- 導覽列 -->
    <?php include 'navbar.php'; ?>
    <!-- 側邊選單 -->
    <?php include 'sidebar.php'; ?>

    <div class="container mt-4">
        <h2>食材分析圖表</h2>

        <!-- 月份選擇器 -->
        <div class="card month-selector">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="month" class="form-label">選擇月份：</label>
                    </div>
                    <div class="col-auto">
                        <input type="month" class="form-control" id="month" name="month" value="<?php echo $selectedMonth; ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">查看</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <!-- 儲存類型分布圖表 -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>食材儲存類型分布</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="storeTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 購買食材圖表 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><?php echo date('Y年m月', strtotime($selectedMonth)); ?> 購買食材分析</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="purchaseChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 過期食材圖表 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><?php echo date('Y年m月', strtotime($selectedMonth)); ?> 過期食材分析</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="expiredChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 將 PHP 數據傳遞給 JavaScript
        const storeTypeData = <?php echo $storeTypeDataJSON; ?>;
        const purchaseData = <?php echo $purchaseDataJSON; ?>;
        const expiredData = <?php echo $expiredDataJSON; ?>;
        const ingredientsByType = <?php echo $ingredientsByTypeJSON; ?>;
        const purchaseDetailData = <?php echo $purchaseDetailDataJSON; ?>;
        const expiredDetailData = <?php echo $expiredDetailDataJSON; ?>;

        // 定義圖表顏色
        const chartColors = {
            '冷藏': 'rgba(54, 162, 235, 0.7)',
            '冷凍': 'rgba(153, 102, 255, 0.7)',
            '常溫': 'rgba(255, 206, 86, 0.7)',
            '無': 'rgba(201, 203, 207, 0.7)'
        };

        // 準備儲存類型圖表數據
        const storeTypeLabels = [];
        const storeTypeCounts = [];
        const storeTypeBackgroundColors = [];

        storeTypeData.forEach(item => {
            storeTypeLabels.push(item.StoreType);
            storeTypeCounts.push(item.Count);
            storeTypeBackgroundColors.push(chartColors[item.StoreType] || 'rgba(201, 203, 207, 0.7)');
        });

        // 準備購買食材圖表數據
        const purchaseLabels = [];
        const purchaseCounts = [];
        const purchaseBackgroundColors = [];

        purchaseData.forEach(item => {
            purchaseLabels.push(item.StoreType);
            purchaseCounts.push(item.Count);
            purchaseBackgroundColors.push(chartColors[item.StoreType] || 'rgba(201, 203, 207, 0.7)');
        });

        // 準備過期食材圖表數據
        const expiredLabels = [];
        const expiredCounts = [];
        const expiredBackgroundColors = [];

        expiredData.forEach(item => {
            expiredLabels.push(item.StoreType);
            expiredCounts.push(item.Count);
            expiredBackgroundColors.push(chartColors[item.StoreType] || 'rgba(201, 203, 207, 0.7)');
        });

        // 創建儲存類型分布圖表
        const storeTypeCtx = document.getElementById('storeTypeChart').getContext('2d');
        const storeTypeChart = new Chart(storeTypeCtx, {
            type: 'bar',
            data: {
                labels: storeTypeLabels,
                datasets: [{
                    label: '食材數量',
                    data: storeTypeCounts,
                    backgroundColor: storeTypeBackgroundColors,
                    borderColor: storeTypeBackgroundColors.map(color => color.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: '食材儲存類型分布'
                    },
                    legend: {
                        display: false
                    }
                },
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const storeType = storeTypeLabels[index];
                        showIngredientPieChart(storeType, ingredientsByType[storeType], '儲存類型');
                    }
                }
            }
        });

        // 創建購買食材圖表
        const purchaseCtx = document.getElementById('purchaseChart').getContext('2d');
        const purchaseChart = new Chart(purchaseCtx, {
            type: 'bar',
            data: {
                labels: purchaseLabels,
                datasets: [{
                    label: '購買數量',
                    data: purchaseCounts,
                    backgroundColor: purchaseBackgroundColors,
                    borderColor: purchaseBackgroundColors.map(color => color.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: '購買食材分析'
                    },
                    legend: {
                        display: false
                    }
                },
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const storeType = purchaseLabels[index];
                        showIngredientPieChart(storeType, purchaseDetailData[storeType], '購買食材');
                    }
                }
            }
        });

        // 創建過期食材圖表
        const expiredCtx = document.getElementById('expiredChart').getContext('2d');
        const expiredChart = new Chart(expiredCtx, {
            type: 'bar',
            data: {
                labels: expiredLabels,
                datasets: [{
                    label: '過期數量',
                    data: expiredCounts,
                    backgroundColor: expiredBackgroundColors,
                    borderColor: expiredBackgroundColors.map(color => color.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: '過期食材分析'
                    },
                    legend: {
                        display: false
                    }
                },
                onClick: (event, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const storeType = expiredLabels[index];
                        showIngredientPieChart(storeType, expiredDetailData[storeType], '過期食材');
                    }
                }
            }
        });

        // 創建一個新的 modal 元素用於顯示圓餅圖
        document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="ingredientPieChartModal" tabindex="-1" aria-labelledby="ingredientPieChartModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="ingredientPieChartModalLabel">食材詳細分布</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="chart-container" style="position: relative; height: 400px;">
                                <canvas id="ingredientPieChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `);

        // 定義顯示圓餅圖的函數
        let pieChart = null;
        function showIngredientPieChart(storeType, ingredients, chartType) {
            if (!ingredients || ingredients.length === 0) {
                alert(`沒有${storeType}類型的${chartType}數據`);
                return;
            }

            // 準備圓餅圖數據
            const pieLabels = [];
            const pieData = [];
            const pieColors = [];
            const pieUnits = [];

            ingredients.forEach((item, index) => {
                pieLabels.push(item.name);
                pieData.push(item.quantity);
                pieUnits.push(item.unit);
                
                // 為每個食材生成不同的顏色
                const hue = (index * 137.5) % 360; // 使用黃金角來分散顏色
                pieColors.push(`hsla(${hue}, 70%, 60%, 0.7)`);
            });

            // 如果已經有圓餅圖，則銷毀它
            if (pieChart) {
                pieChart.destroy();
            }

            // 創建圓餅圖
            const pieCtx = document.getElementById('ingredientPieChart').getContext('2d');
            pieChart = new Chart(pieCtx, {
                type: 'pie',
                data: {
                    labels: pieLabels,
                    datasets: [{
                        data: pieData,
                        backgroundColor: pieColors,
                        borderColor: pieColors.map(color => color.replace('0.7', '1')),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: `${storeType}類型${chartType}分布`
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const unit = pieUnits[context.dataIndex] || '個';
                                    return `${label}: ${value} ${unit}`;
                                }
                            }
                        }
                    }
                }
            });

            // 顯示 modal
            const modal = new bootstrap.Modal(document.getElementById('ingredientPieChartModal'));
            modal.show();
        }
    </script>
</body>

</html>