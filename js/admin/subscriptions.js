// Enhanced Modal management
function openModal(action) {
    const modal = document.getElementById('subscriptionModal');
    const form = document.getElementById('subscriptionForm');
    const title = document.getElementById('modalTitleText');
    const icon = document.getElementById('modalIcon');
    const actionInput = document.getElementById('formAction');
    const submitBtn = document.getElementById('submitBtn');

    modal.classList.add('show');
    actionInput.value = action;

    if (action === 'create') {
        title.textContent = 'Nouveau forfait';
        icon.className = 'fas fa-plus';
        form.reset();
        document.getElementById('subscriptionId').value = '';
        submitBtn.innerHTML = '<i class="fas fa-plus"></i> Créer le forfait';

        // Set default values
        document.getElementById('duration_months').value = '12';
    } else {
        title.textContent = 'Modifier forfait';
        icon.className = 'fas fa-edit';
        submitBtn.innerHTML = '<i class="fas fa-save"></i> Mettre à jour';
    }

    // Focus first input with animation
    setTimeout(() => {
        const firstInput = document.getElementById('name_subscription');
        firstInput.focus();
        firstInput.style.transform = 'scale(1.02)';
        setTimeout(() => {
            firstInput.style.transform = 'scale(1)';
        }, 200);
    }, 400);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('show');

    // Reset any transformations
    const inputs = modal.querySelectorAll('input, textarea');
    inputs.forEach(input => {
        input.style.transform = 'scale(1)';
    });
}

function editSubscription(subscription) {
    openModal('update');

    // Populate form with animation
    setTimeout(() => {
        document.getElementById('subscriptionId').value = subscription.id_subscription;

        const fields = [
            { id: 'name_subscription', value: subscription.name_subscription },
            { id: 'price_subscription', value: subscription.price_subscription },
            { id: 'description', value: subscription.description || '' },
            { id: 'duration_months', value: subscription.duration_months },
            { id: 'max_residents', value: subscription.max_residents },
            { id: 'max_apartments', value: subscription.max_apartments }
        ];

        fields.forEach((field, index) => {
            setTimeout(() => {
                const element = document.getElementById(field.id);
                element.value = field.value;
                element.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    element.style.transform = 'scale(1)';
                }, 200);
            }, index * 100);
        });
    }, 200);
}

function confirmDelete(id, name, subscriberCount) {
    const modal = document.getElementById('deleteModal');
    const title = document.getElementById('deleteTitle');
    const message = document.getElementById('deleteMessage');
    const confirmBtn = document.getElementById('confirmDeleteBtn');

    document.getElementById('deleteSubscriptionId').value = id;

    title.textContent = `Supprimer "${name}"`;

    if (subscriberCount > 0) {
        message.innerHTML = `
                   <strong>Attention !</strong> Ce forfait a actuellement <strong>${subscriberCount}</strong> abonnés actifs.<br>
                   La suppression sera impossible tant qu'il y a des abonnés.
               `;
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="fas fa-ban"></i> Suppression impossible';
        confirmBtn.className = 'btn btn-secondary';
    } else {
        message.innerHTML = `
                   Êtes-vous sûr de vouloir supprimer ce forfait ?<br>
                   <strong>Cette action est irréversible.</strong>
               `;
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-trash"></i> Supprimer définitivement';
        confirmBtn.className = 'btn btn-danger';
    }

    modal.classList.add('show');
}

function toggleStatus(id, newStatus) {
    const action = newStatus ? 'activer' : 'désactiver';

    if (confirm(`Êtes-vous sûr de vouloir ${action} cet abonnement ?`)) {
        document.getElementById('toggleSubscriptionId').value = id;
        document.getElementById('toggleNewStatus').value = newStatus;
        document.getElementById('toggleForm').submit();
    }
}

// Enhanced event listeners
document.addEventListener('DOMContentLoaded', function () {
    // Auto-hide alerts with animation
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Enhanced card hover effects
    document.querySelectorAll('.subscription-card').forEach(card => {
        card.addEventListener('mouseenter', function () {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });

        card.addEventListener('mouseleave', function () {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Form validation and enhancement
    const form = document.getElementById('subscriptionForm');
    const inputs = form.querySelectorAll('input[required]');

    inputs.forEach(input => {
        input.addEventListener('blur', function () {
            if (this.validity.valid) {
                this.style.borderColor = 'var(--color-green)';
            } else {
                this.style.borderColor = '#dc3545';
            }
        });

        input.addEventListener('input', function () {
            if (this.validity.valid) {
                this.style.borderColor = 'var(--color-green)';
            }
        });
    });

    // Enhanced form submission
    form.addEventListener('submit', function (event) {
        const submitBtn = document.getElementById('submitBtn');

        // Add loading state with animation
        submitBtn.classList.add('btn-loading');
        submitBtn.disabled = true;

        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';

        // Reset after timeout if form doesn't redirect
        setTimeout(() => {
            submitBtn.classList.remove('btn-loading');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }, 3000);
    });

    // Delete form submission
    document.getElementById('deleteForm').addEventListener('submit', function (event) {
        const confirmBtn = document.getElementById('confirmDeleteBtn');

        if (!confirmBtn.disabled) {
            confirmBtn.classList.add('btn-loading');
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression...';
        }
    });
});

// Close modal when clicking outside
window.onclick = function (event) {
    const subscriptionModal = document.getElementById('subscriptionModal');
    const deleteModal = document.getElementById('deleteModal');

    if (event.target === subscriptionModal) {
        closeModal('subscriptionModal');
    }
    if (event.target === deleteModal) {
        closeModal('deleteModal');
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeModal('subscriptionModal');
        closeModal('deleteModal');
    }

    // Ctrl/Cmd + N for new subscription
    if ((event.ctrlKey || event.metaKey) && event.key === 'n') {
        event.preventDefault();
        openModal('create');
    }
});

// Price formatting
document.getElementById('price_subscription').addEventListener('input', function () {
    let value = parseFloat(this.value);
    if (!isNaN(value)) {
        // Optional: Format the display
        this.setAttribute('title', `${value.toFixed(2)} DH`);
    }
});

// Enhanced animations for better UX
function animateCardUpdate(cardId) {
    const card = document.querySelector(`[data-subscription-id="${cardId}"]`);
    if (card) {
        card.style.transform = 'scale(1.05)';
        card.style.boxShadow = '0 20px 40px rgba(244, 185, 66, 0.3)';

        setTimeout(() => {
            card.style.transform = 'scale(1)';
            card.style.boxShadow = '';
        }, 500);
    }
}

// Success animation for form submission
function showSuccessAnimation() {
    const successIcon = document.createElement('div');
    successIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
    successIcon.style.cssText = `
               position: fixed;
               top: 50%;
               left: 50%;
               transform: translate(-50%, -50%);
               font-size: 4rem;
               color: var(--color-green);
               z-index: 9999;
               animation: successPulse 1s ease-out;
           `;

    document.body.appendChild(successIcon);

    setTimeout(() => {
        successIcon.remove();
    }, 1000);
}

// Add CSS for success animation
const style = document.createElement('style');
style.textContent = `
           @keyframes successPulse {
               0% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
               50% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
               100% { transform: translate(-50%, -50%) scale(1); opacity: 0; }
           }
       `;
document.head.appendChild(style);