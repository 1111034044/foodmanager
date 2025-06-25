function toggleSidebar() {
    const sidebar = document.querySelector(".sidebar");
    sidebar.classList.toggle("active");
}
function closeSidebar() {
    document.querySelector('.sidebar').style.display = 'none';
  }