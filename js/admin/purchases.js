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

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('purchaseModal');
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
document.addEventListener('keydown', function(event) {
    // Ctrl/Cmd + F for search focus
    if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
        event.preventDefault();
        document.getElementById('search').focus();
    }
});

// Real-time search suggestion (optional enhancement)
let searchTimeout;
document.getElementById('search').addEventListener('input', function() {
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
        btn.addEventListener('click', function() {
            showLoadingState(this, 'Traitement');
        });
    }
});

// Filter form enhancements
document.getElementById('filtersForm').addEventListener('submit', function() {
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
    badge.addEventListener('mouseenter', function() {
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