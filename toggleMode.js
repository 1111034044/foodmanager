document.addEventListener('DOMContentLoaded', function () {
    const toggleAllBtn = document.getElementById('toggleAllBtn');
    let globalMode = 'consume'; // 初始模式

    toggleAllBtn?.addEventListener('click', () => {
        const consumeGroups = document.querySelectorAll('.consume-group');
        const editGroups = document.querySelectorAll('.edit-delete-group');

        if (globalMode === 'consume') {
            // 顯示編輯/刪除、隱藏消耗
            consumeGroups.forEach(div => div.style.display = 'none');
            editGroups.forEach(div => div.style.display = 'block');
            toggleAllBtn.textContent = '修改/刪除';
            toggleAllBtn.classList.remove('btn-info');
            toggleAllBtn.classList.add('btn-warning');
            globalMode = 'edit';
        } else {
            // 顯示消耗、隱藏編輯/刪除
            consumeGroups.forEach(div => div.style.display = 'block');
            editGroups.forEach(div => div.style.display = 'none');
            toggleAllBtn.textContent = '消耗';
            toggleAllBtn.classList.remove('btn-warning');
            toggleAllBtn.classList.add('btn-info');
            globalMode = 'consume';
        }
    });
});
