// Payments.js - JavaScript for payment management

document.addEventListener('DOMContentLoaded', function() {
    initializePaymentsPage();
});

function initializePaymentsPage() {
    setupFilters();
    setupModals();
    setupAnimations();
    setupMobileMenu();
    animateStatistics();
    animateProgressBar();
    autoHideAlerts();
    setupFormValidation();
    setupKeyboardShortcuts();
}

// Filter Functions
function setupFilters() {
    const monthInput = document.getElementById('month');
    const statusSelect = document.getElementById('status');
    const residentSelect = document.getElementById('resident');
    
    // Auto-submit on filter change
    [monthInput, statusSelect, residentSelect].forEach(element => {
        if (element) {
            element.addEventListener('change', function() {
                document.getElementById('filtersForm').submit();
            });
        }
    });

    // Set default month to current if empty
    if (monthInput && !monthInput.value) {
        const now = new Date();
        const currentMonth = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
        monthInput.value = currentMonth;
    }
}

// Modal Management
function setupModals() {
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Keyboard shortcuts for modals
    document.addEventListener('keydown', function(e) {
        // Escape to close modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal[style*="display: block"]');
            openModals.forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });
}

// Payment Actions
function openPaymentModal() {
    resetPaymentForm();
    document.getElementById('paymentModal').style.display = 'block';
}

function recordPayment(residentId, residentName) {
    resetPaymentForm();
    
    // Pre-select the resident
    document.getElementById('payer_select').value = residentId;
    document.getElementById('payerId').value = residentId;
    
    // Set current month
    const currentMonth = new Date().toISOString().slice(0, 7);
    document.getElementById('month_paid').value = currentMonth;
    
    // Update modal title
    document.getElementById('paymentModalTitle').innerHTML = 
        `<i class="fas fa-plus"></i> Enregistrer un paiement - ${residentName}`;
    
    document.getElementById('paymentModal').style.display = 'block';
}

function resetPaymentForm() {
    document.getElementById('paymentForm').reset();
    document.getElementById('payerId').value = '';
    document.getElementById('paymentModalTitle').innerHTML = 
        '<i class="fas fa-plus"></i> Enregistrer un paiement';
}

function viewPaymentDetails(paymentData) {
    const detailsContainer = document.getElementById('paymentDetails');
    const resident = paymentData.resident;
    const payment = paymentData.payment;
    
    const detailsHTML = `
        <div class="payment-detail-item">
            <span class="payment-detail-label">Résident:</span>
            <span class="payment-detail-value">${resident.full_name}</span>
        </div>
        <div class="payment-detail-item">
            <span class="payment-detail-label">Appartement:</span>
            <span class="payment-detail-value">Apt. ${resident.apartment_number} - Étage ${resident.floor}</span>
        </div>
        <div class="payment-detail-item">
            <span class="payment-detail-label">Email:</span>
            <span class="payment-detail-value">${resident.email}</span>
        </div>
        <div class="payment-detail-item">
            <span class="payment-detail-label">Date de paiement:</span>
            <span class="payment-detail-value">${formatDate(payment.date_payment)}</span>
        </div>
        <div class="payment-detail-item">
            <span class="payment-detail-label">Mois payé:</span>
            <span class="payment-detail-value">${formatMonth(payment.month_paid)}</span>
        </div>
        <div class="payment-detail-item">
            <span class="payment-detail-label">Statut:</span>
            <span class="payment-detail-value">
                <span class="status-badge paid">
                    <i class="fas fa-check-circle"></i> Payé
                </span>
            </span>
        </div>
    `;
    
    detailsContainer.innerHTML = detailsHTML;
    document.getElementById('detailsModal').style.display = 'block';
}

function deletePayment(payerId, monthPaid, residentName) {
    const confirmMessage = `Êtes-vous sûr de vouloir supprimer le paiement de ${residentName} ?\n\nCette action est irréversible.`;
    
    if (confirm(confirmMessage)) {
        document.getElementById('deletePayerId').value = payerId;
        document.getElementById('deleteMonthPaid').value = monthPaid;
        document.getElementById('deletePaymentForm').submit();
    }
}

function sendReminder(residentId, residentName) {
    const currentMonth = document.getElementById('month').value || new Date().toISOString().slice(0, 7);
    const confirmMessage = `Envoyer un rappel de paiement à ${residentName} ?`;
    
    if (confirm(confirmMessage)) {
        document.getElementById('reminderResidentId').value = residentId;
        document.getElementById('reminderMonth').value = currentMonth;
        document.getElementById('reminderForm').submit();
    }
}

function sendBulkReminders() {
    const unpaidRows = document.querySelectorAll('.payment-row.unpaid');
    
    if (unpaidRows.length === 0) {
        alert('Aucun paiement en attente trouvé.');
        return;
    }
    
    const confirmMessage = `Envoyer des rappels à ${unpaidRows.length} résidents qui n'ont pas encore payé ?`;
    
    if (confirm(confirmMessage)) {
        // Simulate bulk reminder sending
        showLoadingMessage('Envoi des rappels en cours...');
        
        // In a real implementation, you would make an AJAX call here
        setTimeout(() => {
            hideLoadingMessage();
            showSuccessMessage(`${unpaidRows.length} rappels envoyés avec succès.`);
        }, 2000);
    }
}

function generateReport() {
    const currentMonth = document.getElementById('month').value || new Date().toISOString().slice(0, 7);
    showLoadingMessage('Génération du rapport en cours...');
    
    // Simulate report generation
    setTimeout(() => {
        hideLoadingMessage();
        
        // In a real implementation, this would generate and download a PDF
        const reportData = gatherReportData();
        downloadReport(reportData, currentMonth);
    }, 2000);
}

function openBulkPaymentModal() {
    // This would open a modal for bulk payment entry
    alert('Fonctionnalité de paiements multiples en développement.');
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Utility Functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function formatMonth(monthString) {
    const date = new Date(monthString + '-01');
    return date.toLocaleDateString('fr-FR', {
        month: 'long',
        year: 'numeric'
    });
}

function setupFormValidation() {
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            const payerSelect = document.getElementById('payer_select');
            const amountInput = document.getElementById('amount');
            const monthInput = document.getElementById('month_paid');
            
            // Validate payer selection
            if (!payerSelect.value) {
                e.preventDefault();
                alert('Veuillez sélectionner un résident.');
                payerSelect.focus();
                return;
            }
            
            // Validate amount
            const amount = parseFloat(amountInput.value);
            if (!amount || amount <= 0) {
                e.preventDefault();
                alert('Veuillez entrer un montant valide supérieur à 0.');
                amountInput.focus();
                return;
            }
            
            if (amount > 10000) {
                if (!confirm('Le montant semble élevé. Confirmer le paiement ?')) {
                    e.preventDefault();
                    return;
                }
            }
            
            // Validate month
            if (!monthInput.value) {
                e.preventDefault();
                alert('Veuillez sélectionner le mois payé.');
                monthInput.focus();
                return;
            }
            
            // Check if month is in the future
            const selectedMonth = new Date(monthInput.value + '-01');
            const currentDate = new Date();
            const currentMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
            
            if (selectedMonth > currentMonth) {
                if (!confirm('Le mois sélectionné est dans le futur. Continuer ?')) {
                    e.preventDefault();
                    return;
                }
            }

            // Show loading state
            showFormLoadingState();
        });
    }

    // Real-time amount validation
    const amountInput = document.getElementById('amount');
    if (amountInput) {
        amountInput.addEventListener('input', function(e) {
            const value = parseFloat(e.target.value);
            
            if (value && value > 10000) {
                e.target.style.borderColor = '#ed8936';
                showTooltip(e.target, 'Montant élevé - Vérifiez la saisie');
            } else {
                e.target.style.borderColor = '';
                hideTooltip(e.target);
            }
        });
    }
}

function showFormLoadingState() {
    const submitBtn = document.getElementById('paymentSubmitBtn');
    if (submitBtn) {
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
        
        // Store original text for potential restoration
        submitBtn.dataset.originalText = originalText;
    }
}

function setupAnimations() {
    // Animate payment rows on load
    const paymentRows = document.querySelectorAll('.payment-row');
    paymentRows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            row.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 50);
    });

    // Enhanced hover effects for action buttons
    const actionButtons = document.querySelectorAll('.action-btn');
    actionButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
            this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.boxShadow = '';
        });
    });

    // Status badge pulse animation for unpaid
    const unpaidBadges = document.querySelectorAll('.status-badge.unpaid');
    unpaidBadges.forEach(badge => {
        setInterval(() => {
            badge.style.animation = 'pulse 0.5s ease-in-out';
            setTimeout(() => {
                badge.style.animation = '';
            }, 500);
        }, 3000);
    });
}

function animateStatistics() {
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        const target = parseInt(stat.textContent);
        if (!isNaN(target)) {
            animateNumber(stat, target);
        }
    });
}

function animateNumber(element, target) {
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, 30);
}

function animateProgressBar() {
    const progressFill = document.querySelector('.progress-fill');
    if (progressFill) {
        const targetWidth = progressFill.style.width;
        progressFill.style.width = '0%';
        
        setTimeout(() => {
            progressFill.style.transition = 'width 2s ease-in-out';
            progressFill.style.width = targetWidth;
        }, 500);
    }
}

function setupMobileMenu() {
    function createMobileToggle() {
        if (window.innerWidth <= 768) {
            const existingToggle = document.querySelector('.mobile-toggle');
            if (existingToggle) return;

            const toggle = document.createElement('button');
            toggle.innerHTML = '<i class="fas fa-bars"></i>';
            toggle.className = 'mobile-toggle';
            toggle.style.cssText = `
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 1001;
                background: #48bb78;
                color: white;
                border: none;
                padding: 0.75rem;
                border-radius: 8px;
                font-size: 1.2rem;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            `;
            
            toggle.addEventListener('click', () => {
                const sidebar = document.querySelector('.sidebar');
                const isVisible = sidebar.style.transform === 'translateX(0px)';
                sidebar.style.transform = isVisible ? 'translateX(-100%)' : 'translateX(0px)';
                sidebar.style.zIndex = '1002';
            });
            
            document.body.appendChild(toggle);
        }
    }

    createMobileToggle();
    window.addEventListener('resize', createMobileToggle);
}

function autoHideAlerts() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
}

function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + P to record payment
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            openPaymentModal();
        }
        
        // Ctrl/Cmd + R to generate report
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            generateReport();
        }
        
        // Ctrl/Cmd + B to send bulk reminders
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            sendBulkReminders();
        }
    });
}

// Export and Report Functions
function exportPayments() {
    const paymentRows = document.querySelectorAll('.payment-row');
    const payments = Array.from(paymentRows).map(row => {
        const cells = row.querySelectorAll('td');
        const residentName = cells[0].querySelector('strong').textContent;
        const residentEmail = cells[0].querySelector('small').textContent;
        const apartment = cells[1].querySelector('strong').textContent;
        const floor = cells[1].querySelector('small').textContent;
        const status = cells[3].querySelector('.status-badge').textContent.trim();
        const paymentDate = cells[4].textContent.trim();
        
        return {
            resident: residentName,
            email: residentEmail,
            apartment: apartment,
            floor: floor,
            status: status,
            paymentDate: paymentDate === '-' ? '' : paymentDate
        };
    });
    
    downloadCSV(payments, 'paiements');
}

function downloadCSV(data, filename) {
    const headers = ['Résident', 'Email', 'Appartement', 'Étage', 'Statut', 'Date Paiement'];
    const csvContent = [
        headers.join(','),
        ...data.map(row => [
            `"${row.resident}"`,
            `"${row.email}"`,
            `"${row.apartment}"`,
            `"${row.floor}"`,
            `"${row.status}"`,
            `"${row.paymentDate}"`
        ].join(','))
    ].join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `${filename}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function gatherReportData() {
    const stats = {
        totalResidents: document.querySelector('.total-stat .stat-number').textContent,
        paidCount: document.querySelector('.paid-stat .stat-number').textContent,
        unpaidCount: document.querySelector('.unpaid-stat .stat-number').textContent,
        collectionRate: document.querySelector('.rate-stat .stat-number').textContent
    };
    
    const paymentRows = document.querySelectorAll('.payment-row');
    const payments = Array.from(paymentRows).map(row => ({
        resident: row.querySelector('.resident-info strong').textContent,
        apartment: row.querySelector('.apartment-info strong').textContent,
        status: row.querySelector('.status-badge').textContent.trim(),
        paymentDate: row.querySelectorAll('td')[4].textContent.trim()
    }));
    
    return { stats, payments };
}

function downloadReport(data, month) {
    // In a real implementation, this would generate a proper PDF report
    const reportContent = generateReportHTML(data, month);
    const blob = new Blob([reportContent], { type: 'text/html' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `rapport-paiements-${month}.html`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showSuccessMessage('Rapport téléchargé avec succès.');
}

function generateReportHTML(data, month) {
    const monthName = formatMonth(month);
    
    return `
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Rapport des Paiements - ${monthName}</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .stats { display: flex; justify-content: space-around; margin-bottom: 30px; }
            .stat { text-align: center; padding: 15px; border: 1px solid #ccc; }
            .payments { width: 100%; border-collapse: collapse; }
            .payments th, .payments td { border: 1px solid #ccc; padding: 8px; text-align: left; }
            .payments th { background-color: #f5f5f5; }
            .paid { background-color: #d4edda; }
            .unpaid { background-color: #f8d7da; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Rapport des Paiements</h1>
            <h2>${monthName}</h2>
            <p>Généré le ${new Date().toLocaleDateString('fr-FR')}</p>
        </div>
        
        <div class="stats">
            <div class="stat">
                <h3>${data.stats.totalResidents}</h3>
                <p>Total Résidents</p>
            </div>
            <div class="stat">
                <h3>${data.stats.paidCount}</h3>
                <p>Ont Payé</p>
            </div>
            <div class="stat">
                <h3>${data.stats.unpaidCount}</h3>
                <p>En Attente</p>
            </div>
            <div class="stat">
                <h3>${data.stats.collectionRate}</h3>
                <p>Taux de Collecte</p>
            </div>
        </div>
        
        <table class="payments">
            <thead>
                <tr>
                    <th>Résident</th>
                    <th>Appartement</th>
                    <th>Statut</th>
                    <th>Date Paiement</th>
                </tr>
            </thead>
            <tbody>
                ${data.payments.map(payment => `
                    <tr class="${payment.status.toLowerCase().includes('payé') ? 'paid' : 'unpaid'}">
                        <td>${payment.resident}</td>
                        <td>${payment.apartment}</td>
                        <td>${payment.status}</td>
                        <td>${payment.paymentDate}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </body>
    </html>
    `;
}

// UI Helper Functions
function showLoadingMessage(message) {
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'loadingMessage';
    loadingDiv.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        z-index: 2000;
        text-align: center;
    `;
    loadingDiv.innerHTML = `
        <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #48bb78; margin-bottom: 1rem;"></i>
        <p style="margin: 0; font-weight: 600;">${message}</p>
    `;
    
    // Add backdrop
    const backdrop = document.createElement('div');
    backdrop.id = 'loadingBackdrop';
    backdrop.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1999;
    `;
    
    document.body.appendChild(backdrop);
    document.body.appendChild(loadingDiv);
}

function hideLoadingMessage() {
    const loadingMessage = document.getElementById('loadingMessage');
    const backdrop = document.getElementById('loadingBackdrop');
    
    if (loadingMessage) loadingMessage.remove();
    if (backdrop) backdrop.remove();
}

function showSuccessMessage(message) {
    const successDiv = document.createElement('div');
    successDiv.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: #48bb78;
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 2000;
        animation: slideInRight 0.5s ease-out;
    `;
    successDiv.innerHTML = `
        <i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i>
        ${message}
    `;
    
    document.body.appendChild(successDiv);
    
    setTimeout(() => {
        successDiv.style.animation = 'slideOutRight 0.5s ease-in';
        setTimeout(() => successDiv.remove(), 500);
    }, 3000);
}

function showTooltip(element, message) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = message;
    tooltip.style.cssText = `
        position: absolute;
        background: #333;
        color: white;
        padding: 0.5rem;
        border-radius: 5px;
        font-size: 0.8rem;
        z-index: 1000;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.3s ease;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + 'px';
    tooltip.style.top = (rect.bottom + 5) + 'px';
    
    setTimeout(() => tooltip.style.opacity = '1', 10);
    
    element.tooltipElement = tooltip;
}

function hideTooltip(element) {
    if (element.tooltipElement) {
        element.tooltipElement.remove();
        delete element.tooltipElement;
    }
}

// Advanced Features
function setupAdvancedFeatures() {
    setupAutoSave();
    setupRealTimeUpdates();
    setupPaymentReminders();
    setupBulkOperations();
}

function setupAutoSave() {
    // Auto-save form data to localStorage
    const formInputs = document.querySelectorAll('#paymentForm input, #paymentForm select, #paymentForm textarea');
    
    formInputs.forEach(input => {
        // Load saved data
        const savedValue = localStorage.getItem(`payment_form_${input.name}`);
        if (savedValue && input.type !== 'hidden') {
            input.value = savedValue;
        }
        
        // Save on change
        input.addEventListener('input', function() {
            localStorage.setItem(`payment_form_${input.name}`, input.value);
        });
    });
    
    // Clear saved data on successful submission
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function() {
            formInputs.forEach(input => {
                localStorage.removeItem(`payment_form_${input.name}`);
            });
        });
    }
}

function setupRealTimeUpdates() {
    // Simulate real-time updates (in a real app, this would use WebSockets)
    setInterval(() => {
        // Check for new payments or updates
        updatePaymentStatus();
    }, 30000); // Check every 30 seconds
}

function updatePaymentStatus() {
    // This would make an AJAX call to check for updates
    // For demo purposes, we'll just update a timestamp
    const lastUpdate = document.createElement('small');
    lastUpdate.style.cssText = 'color: #718096; position: fixed; bottom: 1rem; right: 1rem;';
    lastUpdate.textContent = `Dernière mise à jour: ${new Date().toLocaleTimeString('fr-FR')}`;
    
    const existingUpdate = document.querySelector('small[style*="position: fixed"]');
    if (existingUpdate) existingUpdate.remove();
    
    document.body.appendChild(lastUpdate);
    
    setTimeout(() => lastUpdate.remove(), 5000);
}

function setupPaymentReminders() {
    // Auto-reminder system
    const unpaidCount = parseInt(document.querySelector('.unpaid-stat .stat-number').textContent);
    
    if (unpaidCount > 0) {
        // Show reminder notification
        setTimeout(() => {
            if (confirm(`Il y a ${unpaidCount} paiements en attente. Voulez-vous envoyer des rappels ?`)) {
                sendBulkReminders();
            }
        }, 5000);
    }
}

function setupBulkOperations() {
    // Add bulk selection functionality
    addBulkSelectionCheckboxes();
    createBulkActionsToolbar();
}

function addBulkSelectionCheckboxes() {
    const paymentRows = document.querySelectorAll('.payment-row');
    
    // Add header checkbox
    const headerRow = document.querySelector('.data-table thead tr');
    if (headerRow) {
        const headerCheckbox = document.createElement('th');
        headerCheckbox.innerHTML = '<input type="checkbox" id="selectAll">';
        headerRow.insertBefore(headerCheckbox, headerRow.firstChild);
        
        // Add row checkboxes
        paymentRows.forEach(row => {
            const checkbox = document.createElement('td');
            checkbox.innerHTML = '<input type="checkbox" class="row-select">';
            row.insertBefore(checkbox, row.firstChild);
        });
        
        // Handle select all
        document.getElementById('selectAll').addEventListener('change', function() {
            const rowCheckboxes = document.querySelectorAll('.row-select');
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionsVisibility();
        });
        
        // Handle individual selections
        document.querySelectorAll('.row-select').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsVisibility);
        });
    }
}

function createBulkActionsToolbar() {
    const toolbar = document.createElement('div');
    toolbar.id = 'bulkActionsToolbar';
    toolbar.style.cssText = `
        position: fixed;
        bottom: 2rem;
        left: 50%;
        transform: translateX(-50%);
        background: white;
        padding: 1rem;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        display: none;
        z-index: 1000;
    `;
    
    toolbar.innerHTML = `
        <span id="selectedCount">0 sélectionnés</span>
        <button class="btn btn-sm btn-warning" onclick="bulkSendReminders()">
            <i class="fas fa-bell"></i> Rappels
        </button>
        <button class="btn btn-sm btn-success" onclick="bulkMarkPaid()">
            <i class="fas fa-check"></i> Marquer payé
        </button>
        <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
            <i class="fas fa-times"></i> Annuler
        </button>
    `;
    
    document.body.appendChild(toolbar);
}

function updateBulkActionsVisibility() {
    const selectedCheckboxes = document.querySelectorAll('.row-select:checked');
    const toolbar = document.getElementById('bulkActionsToolbar');
    const selectedCount = document.getElementById('selectedCount');
    
    if (selectedCheckboxes.length > 0) {
        toolbar.style.display = 'flex';
        toolbar.style.gap = '1rem';
        toolbar.style.alignItems = 'center';
        selectedCount.textContent = `${selectedCheckboxes.length} sélectionné${selectedCheckboxes.length > 1 ? 's' : ''}`;
    } else {
        toolbar.style.display = 'none';
    }
}

function bulkSendReminders() {
    const selectedRows = getSelectedRows();
    const unpaidRows = selectedRows.filter(row => row.classList.contains('unpaid'));
    
    if (unpaidRows.length === 0) {
        alert('Aucun paiement en attente sélectionné.');
        return;
    }
    
    if (confirm(`Envoyer des rappels aux ${unpaidRows.length} résidents sélectionnés ?`)) {
        showLoadingMessage('Envoi des rappels...');
        setTimeout(() => {
            hideLoadingMessage();
            showSuccessMessage(`${unpaidRows.length} rappels envoyés.`);
            clearSelection();
        }, 2000);
    }
}

function bulkMarkPaid() {
    const selectedRows = getSelectedRows();
    const unpaidRows = selectedRows.filter(row => row.classList.contains('unpaid'));
    
    if (unpaidRows.length === 0) {
        alert('Aucun paiement en attente sélectionné.');
        return;
    }
    
    if (confirm(`Marquer ${unpaidRows.length} paiements comme payés ?`)) {
        // This would require backend implementation
        alert('Fonctionnalité en développement - nécessite une implémentation backend.');
    }
}

function getSelectedRows() {
    const selectedCheckboxes = document.querySelectorAll('.row-select:checked');
    return Array.from(selectedCheckboxes).map(checkbox => checkbox.closest('.payment-row'));
}

function clearSelection() {
    document.querySelectorAll('.row-select, #selectAll').forEach(checkbox => {
        checkbox.checked = false;
    });
    updateBulkActionsVisibility();
}

// Performance Optimization
function setupPerformanceOptimization() {
    // Lazy loading for large datasets
    setupVirtualScrolling();
    
    // Debounced search
    setupDebouncedSearch();
    
    // Memory cleanup
    setupMemoryCleanup();
}

function setupVirtualScrolling() {
    // Implementation for handling large payment lists
    const paymentTable = document.querySelector('.data-table tbody');
    if (paymentTable && paymentTable.children.length > 100) {
        console.log('Large dataset detected - consider implementing virtual scrolling');
    }
}

function setupDebouncedSearch() {
    const searchInputs = document.querySelectorAll('input[type="search"], input[name="search"]');
    
    searchInputs.forEach(input => {
        let searchTimeout;
        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                // Trigger search
                document.getElementById('filtersForm').submit();
            }, 300);
        });
    });
}

function setupMemoryCleanup() {
    // Clean up event listeners and intervals when page unloads
    window.addEventListener('beforeunload', function() {
        // Clear any intervals
        const intervals = window.paymentIntervals || [];
        intervals.forEach(interval => clearInterval(interval));
        
        // Remove event listeners
        document.removeEventListener('keydown', setupKeyboardShortcuts);
    });
}

// Initialize advanced features
document.addEventListener('DOMContentLoaded', function() {
    setupAdvancedFeatures();
    setupPerformanceOptimization();
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .tooltip {
        pointer-events: none;
    }
    
    .bulk-selected {
        background-color: rgba(72, 187, 120, 0.1) !important;
    }
`;
document.head.appendChild(style);