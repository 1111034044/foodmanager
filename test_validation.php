<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>測試食譜驗證功能</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>測試食譜驗證功能</h2>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>測試資料</h5>
                    </div>
                    <div class="card-body">
                        <button id="test-btn" class="btn btn-primary">測試驗證 API</button>
                        <div id="test-result" class="mt-3"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>API 狀態</h5>
                    </div>
                    <div class="card-body">
                        <div id="api-status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('test-btn').addEventListener('click', async function() {
            const btn = this;
            const resultDiv = document.getElementById('test-result');
            
            btn.disabled = true;
            btn.textContent = '測試中...';
            resultDiv.innerHTML = '<div class="spinner-border text-primary" role="status"></div> 測試中...';
            
            try {
                // 測試資料
                const testData = {
                    recipe_name: '牛肉丼飯',
                    cook_time: 5,
                    difficulty: '簡單',
                    description: '快速製作的牛肉丼飯',
                    ingredients: '牛肉片 200克, 洋蔥 1個, 白飯 1碗, 醬油 適量',
                    steps: '步驟1: 將牛肉片切絲\n步驟2: 洋蔥切絲\n步驟3: 熱鍋下油\n步驟4: 炒牛肉和洋蔥\n步驟5: 加入醬油調味\n步驟6: 盛在白飯上'
                };
                
                const response = await fetch('recipe_validation_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(testData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h6>驗證成功！</h6>
                            <p><strong>評分：</strong>${result.validation_result.score}/10</p>
                            <p><strong>問題：</strong>${result.validation_result.issues?.join(', ') || '無'}</p>
                            <p><strong>建議：</strong>${result.validation_result.suggestions?.join(', ') || '無'}</p>
                        </div>
                        <pre class="bg-light p-2 rounded">${JSON.stringify(result, null, 2)}</pre>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <h6>驗證失敗</h6>
                            <p>${result.error}</p>
                        </div>
                    `;
                }
                
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>測試錯誤</h6>
                        <p>${error.message}</p>
                    </div>
                `;
            } finally {
                btn.disabled = false;
                btn.textContent = '測試驗證 API';
            }
        });
        
        // 檢查 API 狀態
        async function checkApiStatus() {
            const statusDiv = document.getElementById('api-status');
            
            try {
                const response = await fetch('recipe_validation_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        recipe_name: 'test',
                        cook_time: 1,
                        difficulty: 'test',
                        ingredients: 'test',
                        steps: 'test'
                    })
                });
                
                if (response.ok) {
                    statusDiv.innerHTML = '<span class="text-success">✅ API 正常運作</span>';
                } else {
                    statusDiv.innerHTML = '<span class="text-warning">⚠️ API 回應異常</span>';
                }
            } catch (error) {
                statusDiv.innerHTML = '<span class="text-danger">❌ API 無法連線</span>';
            }
        }
        
        // 頁面載入時檢查狀態
        document.addEventListener('DOMContentLoaded', checkApiStatus);
    </script>
</body>
</html>
