/* XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX */
/* purchases dashboard */

function processPurchase(memberId, clientName) {
    if (confirm(`Êtes-vous sûr de vouloir traiter l'achat de "${clientName}" ?\n\nCela activera le compte syndic et permettra l'accès à la plateforme.`)) {
        document.getElementById('processMemberId').value = memberId;
        document.getElementById('processForm').submit();
    }
}

function cancelPurchase(memberId, clientName) {
    if (confirm(`Êtes-vous sûr de vouloir annuler l'achat de "${clientName}" ?\n\nCette action supprimera définitivement toutes les données associées.`)) {
        document.getElementById('cancelMemberId').value = memberId;
        document.getElementById('cancelForm').submit();
    }
}

function refundPurchase(memberId, clientName) {
    if (confirm(`Êtes-vous sûr de vouloir rembourser l'achat de "${clientName}" ?\n\nLe statut sera marqué comme remboursé.`)) {
        document.getElementById('refundMemberId').value = memberId;
        document.getElementById('refundForm').submit();
    }
}

function viewPurchaseDetails(purchase) {
    const content = document.getElementById('purchaseDetailsContent');

    content.innerHTML = `
               <div class="purchase-details">
                   <h4><i class="fas fa-user"></i> Informations Client</h4>
                   <div class="detail-row">
                       <span>Nom complet:</span>
                       <strong>${purchase.client_name}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Email:</span>
                       <strong>${purchase.client_email}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Téléphone:</span>
                       <strong>${purchase.client_phone || 'Non renseigné'}</strong>
                   </div>
               </div>

               <div class="purchase-details">
                   <h4><i class="fas fa-building"></i> Informations Entreprise</h4>
                   <div class="detail-row">
                       <span>Nom de l'entreprise:</span>
                       <strong>${purchase.company_name || 'Non définie'}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Ville:</span>
                       <strong>${purchase.company_city || 'Non définie'}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Adresse:</span>
                       <strong>${purchase.company_address || 'Non renseignée'}</strong>
                   </div>
               </div>

               <div class="purchase-details">
                   <h4><i class="fas fa-credit-card"></i> Détails de l'Achat</h4>
                   <div class="detail-row">
                       <span>Forfait:</span>
                       <strong>${purchase.subscription_name}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Prix du forfait:</span>
                       <strong>${parseFloat(purchase.subscription_price).toFixed(2)} DH/mois</strong>
                   </div>
                   <div class="detail-row">
                       <span>Montant payé:</span>
                       <strong class="amount-highlight">${parseFloat(purchase.amount_paid).toFixed(2)} DH</strong>
                   </div>
                   <div class="detail-row">
                       <span>Date de paiement:</span>
                       <strong>${new Date(purchase.payment_date).toLocaleString('fr-FR')}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Statut actuel:</span>
                       <span class="status-badge status-${purchase.purchase_status}">${purchase.status_text}</span>
                   </div>
                   <div class="detail-row">
                       <span>Il y a:</span>
                       <strong>${purchase.days_since_purchase} jours</strong>
                   </div>
               </div>

               <div class="purchase-details">
                   <h4><i class="fas fa-calendar"></i> Historique</h4>
                   <div class="detail-row">
                       <span>Date d'inscription:</span>
                       <strong>${new Date(purchase.registration_date).toLocaleString('fr-FR')}</strong>
                   </div>
                   <div class="detail-row">
                       <span>Date de paiement:</span>
                       <strong>${new Date(purchase.payment_date).toLocaleString('fr-FR')}</strong>
                   </div>
               </div>
           `;

    document.getElementById('purchaseModal').classList.add('show');
}

function closeModal() {
    document.getElementById('purchaseModal').classList.remove('show');
}

// Auto-hide alerts
document.addEventListener('DOMContentLoaded', function () {
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

// Close modal when clicking outside
window.onclick = function (event) {
    const modal = document.getElementById('purchaseModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});

// Enhanced table interactions
document.querySelectorAll('.data-table tbody tr').forEach(row => {
    row.addEventListener('mouseenter', function () {
        this.style.backgroundColor = 'rgba(244, 185, 66, 0.1)';
        this.style.transform = 'scale(1.01)';
    });

    row.addEventListener('mouseleave', function () {
        this.style.backgroundColor = '';
        this.style.transform = 'scale(1)';
    });
});

// Enhanced button animations
document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('mouseenter', function () {
        this.style.transform = 'translateY(-2px)';
    });

    btn.addEventListener('mouseleave', function () {
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
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.stat-number').forEach(counter => {
        const target = parseInt(counter.textContent.replace(/[^0-9]/g, ''));
        if (!isNaN(target)) {
            counter.textContent = '0';
            setTimeout(() => {
                animateCounter(counter, target);
                // Add back the currency for revenue
                if (counter.closest('.revenue-stat')) {
                    const timer = setInterval(() => {
                        if (parseInt(counter.textContent) >= target) {
                            counter.textContent = target.toLocaleString() + ' DH';
                            clearInterval(timer);
                        }
                    }, 100);
                }
            }, 500);
        }
    });
});

// Quick action keyboard shortcuts
document.addEventListener('keydown', function (event) {
    // Ctrl/Cmd + F for search focus
    if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
        event.preventDefault();
        document.getElementById('search').focus();
    }
});

// Real-time search suggestion (optional enhancement)
let searchTimeout;
document.getElementById('search').addEventListener('input', function () {
    // Clear previous timeout
    clearTimeout(searchTimeout);

    // Optional: Add search suggestions or highlighting
    const searchTerm = this.value.toLowerCase();
    if (searchTerm.length >= 2) {
        // You could implement live search suggestions here
        console.log('Searching for:', searchTerm);
    }
});

// Export functionality (you can add this as an enhancement)
function exportPurchases() {
    // This could export the current filtered results to CSV
    console.log('Export functionality would go here');
}

// Bulk actions (you can add this as an enhancement)
function bulkAction(action) {
    const checkedBoxes = document.querySelectorAll('.purchase-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('Veuillez sélectionner au moins un achat.');
        return;
    }

    const ids = Array.from(checkedBoxes).map(cb => cb.value);
    console.log(`Bulk ${action} for IDs:`, ids);
}

// Refresh data function
function refreshData() {
    location.reload();
}


// Status change animations
function animateStatusChange(memberId, newStatus) {
    const row = document.querySelector(`tr[data-member-id="${memberId}"]`);
    if (row) {
        row.style.transition = 'all 0.3s ease';
        row.style.transform = 'scale(1.02)';
        row.style.backgroundColor = 'rgba(244, 185, 66, 0.2)';

        setTimeout(() => {
            row.style.transform = 'scale(1)';
            row.style.backgroundColor = '';
        }, 300);
    }
}

// Show loading state for actions
function showLoadingState(button, action) {
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + action + '...';

    // Reset after timeout if action doesn't complete
    setTimeout(() => {
        button.disabled = false;
        button.innerHTML = originalText;
    }, 5000);
}

// Enhanced purchase processing with loading state
document.querySelectorAll('.btn-success').forEach(btn => {
    if (btn.textContent.includes('Traiter')) {
        btn.addEventListener('click', function () {
            showLoadingState(this, 'Traitement');
        });
    }
});

// Filter form enhancements
document.getElementById('filtersForm').addEventListener('submit', function () {
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;

    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Filtrage...';

    setTimeout(() => {
        submitBtn.innerHTML = originalText;
    }, 2000);
});

// Add visual feedback for successful actions
function showSuccessMessage(message) {
    const successDiv = document.createElement('div');
    successDiv.className = 'alert alert-success';
    successDiv.style.position = 'fixed';
    successDiv.style.top = '20px';
    successDiv.style.right = '20px';
    successDiv.style.zIndex = '9999';
    successDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;

    document.body.appendChild(successDiv);

    setTimeout(() => {
        successDiv.style.opacity = '0';
        setTimeout(() => successDiv.remove(), 300);
    }, 3000);
}

// Purchase statistics updates
function updateStatistics() {
    // This could be enhanced to update statistics via AJAX
    // without requiring a full page refresh
}

// Advanced filtering with URL parameters
function updateURLWithFilters() {
    const form = document.getElementById('filtersForm');
    const formData = new FormData(form);
    const params = new URLSearchParams();

    for (let [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }

    const newURL = window.location.pathname + '?' + params.toString();
    window.history.pushState({}, '', newURL);
}

// Initialize tooltips for status badges
document.querySelectorAll('.status-badge').forEach(badge => {
    badge.addEventListener('mouseenter', function () {
        // You could add tooltips explaining what each status means
        this.title = getStatusDescription(this.textContent.trim());
    });
});

function getStatusDescription(status) {
    const descriptions = {
        'En attente': 'Achat effectué, en attente de traitement administratif',
        'Actif': 'Compte activé, accès complet à la plateforme',
        'Inactif': 'Compte suspendu temporairement',
        'Remboursé': 'Achat remboursé, compte désactivé'
    };
    return descriptions[status] || 'Statut du compte';
}

// Print functionality for purchase details
function printPurchaseDetails(purchase) {
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write(`
               <html>
               <head>
                   <title>Détails de l'achat - ${purchase.client_name}</title>
                   <style>
                       body { font-family: Arial, sans-serif; margin: 20px; }
                       .header { text-align: center; margin-bottom: 30px; }
                       .detail-section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; }
                       .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
                       .amount { font-weight: bold; color: #28a745; font-size: 1.2em; }
                   </style>
               </head>
               <body>
                   <div class="header">
                       <h1>Syndic Way - Détails de l'achat</h1>
                       <p>Généré le ${new Date().toLocaleString('fr-FR')}</p>
                   </div>
                   <!-- Purchase details would be formatted here -->
               </body>
               </html>
           `);
    printWindow.document.close();
    printWindow.print();
}




/* XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX */
/* subscriptions dashboard */

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
document.addEventListener('DOMContentLoaded', function() {
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
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Form validation and enhancement
    const form = document.getElementById('subscriptionForm');
    const inputs = form.querySelectorAll('input[required]');
    
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.validity.valid) {
                this.style.borderColor = 'var(--color-green)';
            } else {
                this.style.borderColor = '#dc3545';
            }
        });
        
        input.addEventListener('input', function() {
            if (this.validity.valid) {
                this.style.borderColor = 'var(--color-green)';
            }
        });
    });

    // Enhanced form submission
    form.addEventListener('submit', function(event) {
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
    document.getElementById('deleteForm').addEventListener('submit', function(event) {
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        
        if (!confirmBtn.disabled) {
            confirmBtn.classList.add('btn-loading');
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression...';
        }
    });
});

// Close modal when clicking outside
window.onclick = function(event) {
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
document.addEventListener('keydown', function(event) {
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
document.getElementById('price_subscription').addEventListener('input', function() {
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