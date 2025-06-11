function openModal() {
    document.getElementById('userModal').classList.add('show');
    // Reset to admin tab
    switchTab('admin');
    document.getElementById('admin_name').focus();
}

function closeModal() {
    document.getElementById('userModal').classList.remove('show');
    // Reset forms
    document.getElementById('adminForm').reset();
    document.getElementById('memberForm').reset();
}

function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + 'Tab').classList.add('active');
    
    // Activate corresponding button
    event.target.classList.add('active');
    
    // Focus first input
    setTimeout(() => {
        const firstInput = document.querySelector(`#${tabName}Tab input[type="text"], #${tabName}Tab input[type="email"]`);
        if (firstInput) {
            firstInput.focus();
        }
    }, 100);
}

function updateMemberStatus(memberId, newStatus) {
    const statusText = {
        'active': 'activer',
        'inactive': 'suspendre',
        'pending': 'mettre en attente'
    };
    
    if (confirm(`Êtes-vous sûr de vouloir ${statusText[newStatus]} ce membre ?`)) {
        document.getElementById('statusMemberId').value = memberId;
        document.getElementById('newStatus').value = newStatus;
        document.getElementById('statusForm').submit();
    }
}

function resetPassword(userType, userId) {
    if (confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe ?\n\nLe nouveau mot de passe sera : password123')) {
        document.getElementById('passwordUserType').value = userType;
        document.getElementById('passwordUserId').value = userId;
        document.getElementById('passwordForm').submit();
    }
}

function confirmDeleteAdmin(adminId, adminName) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer l'administrateur "${adminName}" ?\n\nCette action est irréversible.`)) {
        document.getElementById('deleteAdminId').value = adminId;
        document.getElementById('deleteAdminForm').submit();
    }
}

function confirmDeleteMember(memberId, memberName) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer le membre "${memberName}" ?\n\nCette action supprimera toutes les données associées et est irréversible.`)) {
        document.getElementById('deleteMemberId').value = memberId;
        document.getElementById('deleteMemberForm').submit();
    }
}

// Manual filter submission - only when button is clicked
document.getElementById('filtersForm').addEventListener('submit', function(event) {
    // Form will submit normally when button is clicked
});

// Prevent auto-submission on input change - remove all auto-submit listeners
// Only submit when the filter button is clicked

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('userModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

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

    // Update statistics with animation
    document.querySelectorAll('.stat-card').forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.5s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50);
        }, index * 100);
    });
});

// Form validation
document.getElementById('adminForm').addEventListener('submit', function(event) {
    const email = document.getElementById('admin_email').value;
    const password = document.getElementById('admin_password').value;
    
    if (password.length < 6) {
        event.preventDefault();
        alert('Le mot de passe doit contenir au moins 6 caractères.');
        return;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        event.preventDefault();
        alert('Veuillez entrer une adresse email valide.');
        return;
    }
});

document.getElementById('memberForm').addEventListener('submit', function(event) {
    const email = document.getElementById('member_email').value;
    const password = document.getElementById('member_password').value;
    const role = document.getElementById('member_role').value;
    
    if (!role) {
        event.preventDefault();
        alert('Veuillez sélectionner un rôle.');
        return;
    }
    
    if (password.length < 6) {
        event.preventDefault();
        alert('Le mot de passe doit contenir au moins 6 caractères.');
        return;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        event.preventDefault();
        alert('Veuillez entrer une adresse email valide.');
        return;
    }
});

// Format phone number
document.getElementById('member_phone').addEventListener('input', function() {
    let value = this.value.replace(/\D/g, '');
    if (value.length >= 10) {
        value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
    }
    this.value = value;
});

// Auto-capitalize names
document.getElementById('admin_name').addEventListener('input', function() {
    this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
});

document.getElementById('member_name').addEventListener('input', function() {
    this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
});

// Show loading state on form submission
document.querySelectorAll('#adminForm, #memberForm').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création en cours...';
        
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }, 5000);
    });
});

// Enhanced table interactions
document.querySelectorAll('.data-table tbody tr').forEach(row => {
    row.addEventListener('mouseenter', function() {
        this.style.backgroundColor = 'rgba(244, 185, 66, 0.1)';
        this.style.transform = 'scale(1.01)';
    });
    
    row.addEventListener('mouseleave', function() {
        this.style.backgroundColor = '';
        this.style.transform = 'scale(1)';
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    // Ctrl/Cmd + N for new user
    if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
        event.preventDefault();
        openModal();
    }
    
    // Ctrl/Cmd + F for search focus
    if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
        event.preventDefault();
        document.getElementById('search').focus();
    }
});

// Real-time form validation
document.querySelectorAll('#userModal input[required]').forEach(field => {
    field.addEventListener('blur', function() {
        if (!this.value.trim()) {
            this.style.borderColor = '#dc3545';
        } else {
            this.style.borderColor = 'var(--color-green)';
        }
    });

    field.addEventListener('input', function() {
        if (this.style.borderColor === 'rgb(220, 53, 69)' && this.value.trim()) {
            this.style.borderColor = 'var(--color-green)';
        }
    });
});

// Enhanced button animations
document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
    });
    
    btn.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

// Dynamic statistics counter animation
function animateCounter(element, target) {
    let current = 0;
    const increment = target / 20;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, 50);
}

// Initialize counter animations on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.stat-number').forEach(counter => {
        const target = parseInt(counter.textContent);
        counter.textContent = '0';
        setTimeout(() => {
            animateCounter(counter, target);
        }, 500);
    });
});