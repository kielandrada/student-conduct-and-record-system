document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const menuBtn = document.getElementById('menuBtn');

    if (menuBtn && sidebar)  {
        menuBtn.addEventListener('click', function () {
            sidebar.classList.toggle('sidebar-open');
        });
    }
});