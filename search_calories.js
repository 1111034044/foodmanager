
function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    sidebar.classList.toggle("active");
}
function closeSidebar() {
    document.querySelector('.sidebar').style.display = 'none';
  }

// 查詢熱量功能
window.queryCalorieByGPT = async function(food, qty, btnSelector, calorieSelector, hintSelector) {
    if (!food || !qty) {
        alert('請先輸入食物名稱和數量');
        return;
    }
    var prompt = `請告訴我 ${qty} 份 ${food} 的熱量（kcal），請計算到小數點第一位。只回覆數字，不要加單位。`;
    var $btn = $(btnSelector);
    var $calorie = $(calorieSelector);
    var $hint = $(hintSelector);
    $btn.prop('disabled', true).text('查詢中...');
    try {
        const response = await fetch('https://api.openai.com/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer {API_KEY}'
                
            },
            body: JSON.stringify({
                model: 'gpt-4o',
                messages: [
                    { role: 'user', content: prompt }
                ],
                max_tokens: 20,
                temperature: 0.2
            })
        });
        const data = await response.json();
        let kcal = '';
        if (data.choices && data.choices[0] && data.choices[0].message && data.choices[0].message.content) {
            kcal = data.choices[0].message.content.replace(/[^\d\.]/g, '');
        }
        if (kcal) {
            $calorie.val(kcal).prop('readonly', true);
            $hint.addClass('d-none');
        } else {
            $calorie.val('').prop('readonly', false);
            $hint.removeClass('d-none');
            alert('查詢失敗，請手動輸入熱量');
        }
    } catch (e) {
        alert('查詢失敗，請手動輸入熱量');
    }
    $btn.prop('disabled', false).text('查詢熱量');
}

function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    sidebar.classList.toggle("active");
}
function closeSidebar() {
    document.querySelector('.sidebar').style.display = 'none';
  }

// 查詢熱量功能
window.queryCalorieByGPT = async function(food, qty, btnSelector, calorieSelector, hintSelector) {
    if (!food || !qty) {
        alert('請先輸入食物名稱和數量');
        return;
    }
    var prompt = `請告訴我 ${qty} 份 ${food} 的熱量（kcal），請計算到小數點第一位。只回覆數字，不要加單位。`;
    var $btn = $(btnSelector);
    var $calorie = $(calorieSelector);
    var $hint = $(hintSelector);
    $btn.prop('disabled', true).text('查詢中...');
    try {
        const response = await fetch('https://api.openai.com/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer {API_KEY}'
                
            },
            body: JSON.stringify({
                model: 'gpt-4o',
                messages: [
                    { role: 'user', content: prompt }
                ],
                max_tokens: 20,
                temperature: 0.2
            })
        });
        const data = await response.json();
        let kcal = '';
        if (data.choices && data.choices[0] && data.choices[0].message && data.choices[0].message.content) {
            kcal = data.choices[0].message.content.replace(/[^\d\.]/g, '');
        }
        if (kcal) {
            $calorie.val(kcal).prop('readonly', true);
            $hint.addClass('d-none');
        } else {
            $calorie.val('').prop('readonly', false);
            $hint.removeClass('d-none');
            alert('查詢失敗，請手動輸入熱量');
        }
    } catch (e) {
        alert('查詢失敗，請手動輸入熱量');
    }
    $btn.prop('disabled', false).text('查詢熱量');
}

