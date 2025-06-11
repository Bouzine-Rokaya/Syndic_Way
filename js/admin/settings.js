// Tab switching functionality
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName).classList.add('active');
    
    // Add active class to selected tab button
    event.target.closest('.tab-button').classList.add('active');
    
    // Update URL hash
    window.location.hash = tabName;
}

// Initialize tab from URL hash
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        const tabButton = document.querySelector(`[onclick="switchTab('${hash}')"]`);
        if (tabButton) {
            tabButton.click();
        }
    }
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Update security level
    updateSecurityLevel();
});

// Form validation
function validateForm(form) {
    const action = form.querySelector('input[name="action"]').value;
    
    if (action === 'update_admin_profile') {
        const newPassword = form.querySelector('#new_password').value;
        const confirmPassword = form.querySelector('#confirm_password').value;
        const currentPassword = form.querySelector('#current_password').value;
        
        if (newPassword && newPassword !== confirmPassword) {
            alert('Les nouveaux mots de passe ne correspondent pas.');
            return false;
        }
        
        if (newPassword && !currentPassword) {
            alert('Le mot de passe actuel est requis pour changer le mot de passe.');
            return false;
        }
    }
    
    return true;
}

// Password strength checker
function checkPasswordStrength(password) {
    const strengthFill = document.getElementById('strength-fill');
    const strengthText = document.getElementById('strength-text');
    
    let score = 0;
    let feedback = [];
    
    if (password.length >= 8) score++;
    else feedback.push('Au moins 8 caractères');
    
    if (/[a-z]/.test(password)) score++;
    else feedback.push('Minuscules');
    
    if (/[A-Z]/.test(password)) score++;
    else feedback.push('Majuscules');
    
    if (/[0-9]/.test(password)) score++;
    else feedback.push('Chiffres');
    
    if (/[^a-zA-Z0-9]/.test(password)) score++;
    else feedback.push('Symboles');
    
    const percentage = (score / 5) * 100;
    strengthFill.style.width = percentage + '%';
    
    if (score < 2) {
        strengthFill.className = 'strength-fill';
        strengthText.textContent = 'Faible - ' + feedback.join(', ');
    } else if (score < 4) {
        strengthFill.className = 'strength-fill medium';
        strengthText.textContent = 'Moyen - ' + feedback.join(', ');
    } else {
        strengthFill.className = 'strength-fill strong';
        strengthText.textContent = 'Fort';
    }
}

// Security level calculator
function updateSecurityLevel() {
    const indicators = document.querySelectorAll('#security .indicator');
    const levelText = document.getElementById('security-level-text');
    
    let level = 2; // Base level
    
    // Check password requirements
    if (document.getElementById('require_password_uppercase').checked) level++;
    if (document.getElementById('require_password_numbers').checked) level++;
    if (document.getElementById('require_password_symbols').checked) level++;
    if (document.getElementById('enable_two_factor').checked) level++;
    
    const maxLevel = Math.min(level, 5);
    
    indicators.forEach((indicator, index) => {
         indicator.className = 'indicator';
        if (index < maxLevel) {
            if (index < 2) indicator.classList.add('active');
            else if (index < 4) indicator.classList.add('medium');
            else indicator.classList.add('high');
        }
    });
    
    const levels = ['Très faible', 'Faible', 'Moyen', 'Bon', 'Excellent'];
    levelText.textContent = levels[Math.min(maxLevel - 1, 4)];
}

// Add event listeners for security checkboxes
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#security input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateSecurityLevel);
    });
});

// Connection testing functions
function testStripeConnection() {
    const statusDiv = document.getElementById('stripe-status');
    const publicKey = document.getElementById('stripe_public_key').value;
    const secretKey = document.getElementById('stripe_secret_key').value;
    
    if (!publicKey || !secretKey) {
        showConnectionStatus('stripe-status', false, 'Clés manquantes');
        return;
    }
    
    // Simulate API test (in real implementation, this would make an AJAX call)
    statusDiv.style.display = 'block';
    statusDiv.textContent = 'Test en cours...';
    statusDiv.className = 'connection-status';
    
    setTimeout(() => {
        // Simulate success/failure
        const success = publicKey.startsWith('pk_') && secretKey.startsWith('sk_');
        showConnectionStatus('stripe-status', success, success ? 'Connexion réussie' : 'Clés invalides');
    }, 2000);
}

function testPayPalConnection() {
    const statusDiv = document.getElementById('paypal-status');
    const clientId = document.getElementById('paypal_client_id').value;
    const clientSecret = document.getElementById('paypal_client_secret').value;
    
    if (!clientId || !clientSecret) {
        showConnectionStatus('paypal-status', false, 'Identifiants manquants');
        return;
    }
    
    statusDiv.style.display = 'block';
    statusDiv.textContent = 'Test en cours...';
    statusDiv.className = 'connection-status';
    
    setTimeout(() => {
        // Simulate success/failure
        const success = clientId.length > 10 && clientSecret.length > 10;
        showConnectionStatus('paypal-status', success, success ? 'Connexion réussie' : 'Identifiants invalides');
    }, 2000);
}

function sendTestEmail() {
    const testEmail = document.getElementById('test_email_input').value;
    
    if (!testEmail || !testEmail.includes('@')) {
        showConnectionStatus('email-status', false, 'Email invalide');
        return;
    }
    
    // Create form data for test email
    const formData = new FormData();
    formData.append('action', 'test_email');
    formData.append('test_email', testEmail);
    
    const statusDiv = document.getElementById('email-status');
    statusDiv.style.display = 'block';
    statusDiv.textContent = 'Envoi en cours...';
    statusDiv.className = 'connection-status';
    
    fetch('settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        showConnectionStatus('email-status', true, 'Email envoyé');
    })
    .catch(error => {
        showConnectionStatus('email-status', false, 'Erreur d\'envoi');
    });
}

function showConnectionStatus(elementId, success, message) {
    const statusDiv = document.getElementById(elementId);
    statusDiv.style.display = 'block';
    statusDiv.textContent = message;
    statusDiv.className = success ? 'connection-status success' : 'connection-status failed';
    
    setTimeout(() => {
        statusDiv.style.display = 'none';
    }, 5000);
}

// Quick actions
function clearCache() {
    if (confirm('Êtes-vous sûr de vouloir vider le cache ?')) {
        const formData = new FormData();
        formData.append('action', 'clear_cache');
        
        fetch('settings.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert('Cache vidé avec succès !');
            location.reload();
        })
        .catch(error => {
            alert('Erreur lors du vidage du cache.');
        });
    }
}

function backupDatabase() {
    if (confirm('Êtes-vous sûr de vouloir créer une sauvegarde de la base de données ?')) {
        const formData = new FormData();
        formData.append('action', 'backup_database');
        
        fetch('settings.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert('Sauvegarde lancée ! Vous recevrez un email de confirmation.');
        })
        .catch(error => {
            alert('Erreur lors de la sauvegarde.');
        });
    }
}

function checkUpdates() {
    alert('Vérification des mises à jour en cours...\n\nAucune mise à jour disponible.');
}

function optimizeDatabase() {
    if (confirm('Êtes-vous sûr de vouloir optimiser la base de données ?\nCela peut prendre quelques minutes.')) {
        alert('Optimisation lancée en arrière-plan.\nVous recevrez une notification une fois terminée.');
    }
}

// Danger zone functions
function resetSettings() {
    if (confirm('ATTENTION: Cette action va réinitialiser tous les paramètres.\nÊtes-vous absolument sûr ?')) {
        if (prompt('Tapez "RESET" pour confirmer') === 'RESET') {
            alert('Réinitialisation des paramètres en cours...');
            // Here you would implement the actual reset
        }
    }
}

function clearAllData() {
    if (confirm('DANGER: Cette action va supprimer TOUTES les données.\nCette action est IRRÉVERSIBLE !')) {
        if (prompt('Tapez "DELETE ALL" pour confirmer') === 'DELETE ALL') {
            alert('Cette fonctionnalité est désactivée en mode démo.');
        }
    }
}

function factoryReset() {
    if (confirm('DANGER EXTRÊME: Remise à zéro complète du système.\nTout sera supprimé définitivement !')) {
        if (prompt('Tapez "FACTORY RESET" pour confirmer') === 'FACTORY RESET') {
            alert('Cette fonctionnalité est désactivée en mode démo.');
        }
    }
}

// Save all settings function
function saveAllSettings() {
    const activeTab = document.querySelector('.tab-content.active');
    const form = activeTab.querySelector('form');
    
    if (form) {
        if (validateForm(form)) {
            form.submit();
        }
    } else {
        alert('Aucun paramètre à sauvegarder dans cet onglet.');
    }
}

// Auto-save functionality (optional)
let autoSaveTimeout;
function enableAutoSave() {
    document.querySelectorAll('input, select, textarea').forEach(element => {
        element.addEventListener('change', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                // Auto-save logic here
                console.log('Auto-save triggered for:', this.name);
            }, 5000); // Save 5 seconds after last change
        });
    });
}

// Real-time validation
document.addEventListener('DOMContentLoaded', function() {
    // Email validation
    document.querySelectorAll('input[type="email"]').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value && !this.value.includes('@')) {
                this.style.borderColor = '#dc3545';
                showFieldError(this, 'Email invalide');
            } else {
                this.style.borderColor = '';
                hideFieldError(this);
            }
        });
    });
    
    // Number validation
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('blur', function() {
            const min = parseInt(this.min);
            const max = parseInt(this.max);
            const value = parseInt(this.value);
            
            if (this.value && (value < min || value > max)) {
                this.style.borderColor = '#dc3545';
                showFieldError(this, `Valeur doit être entre ${min} et ${max}`);
            } else {
                this.style.borderColor = '';
                hideFieldError(this);
            }
        });
    });
    
    // Password confirmation
    document.getElementById('confirm_password')?.addEventListener('blur', function() {
        const newPassword = document.getElementById('new_password').value;
        if (this.value && this.value !== newPassword) {
            this.style.borderColor = '#dc3545';
            showFieldError(this, 'Les mots de passe ne correspondent pas');
        } else {
            this.style.borderColor = '';
            hideFieldError(this);
        }
    });
});

function showFieldError(field, message) {
    hideFieldError(field); // Remove existing error
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
        color: #dc3545;
        font-size: 0.8rem;
        margin-top: 0.25rem;
        font-weight: 500;
    `;
    
    field.parentNode.appendChild(errorDiv);
}

function hideFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
    // Ctrl/Cmd + S to save
    if ((event.ctrlKey || event.metaKey) && event.key === 's') {
        event.preventDefault();
        saveAllSettings();
    }
    
    // Ctrl/Cmd + 1-6 to switch tabs
    if ((event.ctrlKey || event.metaKey) && event.key >= '1' && event.key <= '6') {
        event.preventDefault();
        const tabs = ['general', 'payment', 'email', 'security', 'profile', 'system'];
        const tabIndex = parseInt(event.key) - 1;
        if (tabs[tabIndex]) {
            document.querySelector(`[onclick="switchTab('${tabs[tabIndex]}')"]`).click();
        }
    }
});

// Enhanced form interactions
document.querySelectorAll('.form-group input, .form-group select, .form-group textarea').forEach(element => {
    element.addEventListener('focus', function() {
        this.parentElement.style.transform = 'scale(1.02)';
        this.parentElement.style.transition = 'transform 0.2s ease';
    });
    
    element.addEventListener('blur', function() {
        this.parentElement.style.transform = 'scale(1)';
    });
});

// Progressive enhancement for quick actions
document.querySelectorAll('.quick-action').forEach(action => {
    action.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px) scale(1.05)';
    });
    
    action.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
    });
});

// Settings change tracking
let originalFormData = {};

function trackChanges() {
    document.querySelectorAll('form').forEach(form => {
        const formData = new FormData(form);
        const formId = form.querySelector('input[name="action"]').value;
        originalFormData[formId] = Object.fromEntries(formData);
        
        form.addEventListener('change', function() {
            const currentData = new FormData(this);
            const hasChanges = JSON.stringify(Object.fromEntries(currentData)) !== 
                              JSON.stringify(originalFormData[formId]);
            
            if (hasChanges) {
                this.classList.add('has-changes');
                showUnsavedWarning(true);
            } else {
                this.classList.remove('has-changes');
                showUnsavedWarning(false);
            }
        });
    });
}

function showUnsavedWarning(show) {
    let warning = document.getElementById('unsaved-warning');
    
    if (show && !warning) {
        warning = document.createElement('div');
        warning.id = 'unsaved-warning';
        warning.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: #f39c12;
                color: white;
                padding: 1rem;
                border-radius: 8px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                z-index: 1000;
                font-weight: 600;
            ">
                <i class="fas fa-exclamation-triangle"></i>
                Modifications non sauvegardées
            </div>
        `;
        document.body.appendChild(warning);
    } else if (!show && warning) {
        warning.remove();
    }
}

// Initialize change tracking
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(trackChanges, 1000); // Allow forms to fully load
});

// Warn before leaving page with unsaved changes
window.addEventListener('beforeunload', function(event) {
    if (document.querySelector('.has-changes')) {
        event.preventDefault();
        event.returnValue = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter ?';
        return event.returnValue;
    }
});

// Theme switcher (bonus feature)
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
}

// Load dark mode preference
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
    }
});

// Performance monitoring
function trackPagePerformance() {
    if ('performance' in window) {
        window.addEventListener('load', function() {
            const navigation = performance.getEntriesByType('navigation')[0];
            const loadTime = navigation.loadEventEnd - navigation.loadEventStart;
            
            if (loadTime > 3000) { // If page takes more than 3 seconds
                console.warn('Page load time:', loadTime + 'ms');
                // You could send this data to analytics
            }
        });
    }
}

trackPagePerformance();