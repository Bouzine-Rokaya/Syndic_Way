 // Auto-hide alerts
 document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Animate statistics counters
    document.querySelectorAll('.stat-number').forEach(counter => {
        const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
        if (!isNaN(target)) {
            animateCounter(counter, target);
        }
    });
});

function animateCounter(element, target) {
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, 20);
}