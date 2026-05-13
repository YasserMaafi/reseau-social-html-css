// Admin Panel Functionality

document.addEventListener('DOMContentLoaded', function() {
    setupLogout();
});

function setupLogout() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => window.location.href = '/logout.php');
    }
}
