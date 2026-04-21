// ============================================================
// assets/js/app.js — ParkOS Frontend Helpers
// ============================================================

// Auto-dismiss flash alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Mobile sidebar toggle
    const toggleBtn = document.getElementById('sidebar-toggle');
    const sidebar   = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    // Live timer for active sessions (if element exists)
    const startTimeEl = document.getElementById('session-start-time');
    const durationEl  = document.getElementById('live-duration');
    if (startTimeEl && durationEl) {
        const startTime = new Date(startTimeEl.value);
        setInterval(function () {
            const now  = new Date();
            const diff = Math.floor((now - startTime) / 1000); // seconds
            const h    = Math.floor(diff / 3600);
            const m    = Math.floor((diff % 3600) / 60);
            const s    = diff % 60;
            durationEl.textContent = h + 'h ' + String(m).padStart(2,'0') + 'm ' + String(s).padStart(2,'0') + 's';
        }, 1000);
    }

    // Vehicle number auto-uppercase
    document.querySelectorAll('input[name="vehicle_number"]').forEach(function (input) {
        input.addEventListener('input', function () {
            const pos = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(pos, pos);
        });
    });
});
