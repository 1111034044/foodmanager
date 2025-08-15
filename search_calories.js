
function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    sidebar.classList.toggle("active");
}
function closeSidebar() {
    document.querySelector('.sidebar').style.display = 'none';
  }

// 查詢熱量功能（改為呼叫後端 PHP，引用 config.php）
window.queryCalorieByGPT = async function(food, qty, btnSelector, calorieSelector, hintSelector) {
    if (!food || !qty) {
        alert('請先輸入食物名稱和數量');
        return;
    }
    var $btn = $(btnSelector);
    var $calorie = $(calorieSelector);
    var $hint = $(hintSelector);
    $btn.prop('disabled', true).text('查詢中...');
    try {
        const response = await fetch('calorie_query_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ food: food, qty: qty })
        });
        const data = await response.json();
        let kcal = '';
        if (data && data.success && data.kcal) {
            kcal = String(data.kcal).replace(/[^\d\.]/g, '');
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
        $calorie.val('').prop('readonly', false);
        $hint.removeClass('d-none');
        alert('查詢失敗，請手動輸入熱量');
    }
    $btn.prop('disabled', false).text('查詢熱量');
}