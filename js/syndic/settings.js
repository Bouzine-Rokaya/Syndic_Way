// Settings.js - JavaScript for settings page management

document.addEventListener('DOMContentLoaded', function() {
    initializeSettingsPage();
});

function initializeSettingsPage() {
    setupTabNavigation();
    setupPasswordValidation();
    setupFormValidation();
    setupToggleSwitches();
    setupFileUploads();
    setupModals();
    setupBackupFunctions();
    setupSystemTools();
    setupAnimations();
    setupKeyboardShortcuts();
    setupNotificationSystem();
    autoHideAlerts();
    loadUserPreferences();
}

// Tab Navigation
function setupTabNavigation() {
    const tabs = document.querySelectorAll('.settings-tab');
    const sections = document.querySelectorAll('.settings-section');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and sections
            tabs.forEach(t => t.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));
            
            // Add active class to current tab and section
            this.classList.add('active');
            const targetSection = document.getElementById(targetTab + '-settings');
            if (targetSection) {
                targetSection.classList.add('active');
                
                // Save current tab to localStorage
                localStorage.setItem('activeSettingsTab', targetTab);
                
                // Trigger section-specific initialization
                initializeTabSection(targetTab);
            }
        });
    });
    
    // Load saved tab or default to profile
    const savedTab = localStorage.getItem('activeSettingsTab') || 'profile';
    const savedTabElement = document.querySelector(`[data-tab="${savedTab}"]`);
    if (savedTabElement) {
        savedTabElement.click();
    }
}

function initializeTabSection(tabName) {
    switch(tabName) {
        case 'profile':
            initializeProfileSection();
            break;
        case 'building':
            initializeBuildingSection();
            break;
        case 'notifications':
            initializeNotificationsSection();
            break;
        case 'security':
            initializeSecuritySection();
            break;
        case 'system':
            initializeSystemSection();
            break;
        case 'backup':
            initializeBackupSection();
            break;
    }
}

// Password Validation
function setupPasswordValidation() {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const strengthIndicator = document.getElementById('passwordStrength');
    
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            updatePasswordStrength(password);
            updatePasswordRequirements(password);
            
            if (confirmPasswordInput.value) {
                validatePasswordMatch();
            }
        });
    }
    
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
    }
}

function updatePasswordStrength(password) {
    const strengthIndicator = document.getElementById('passwordStrength');
    if (!strengthIndicator) return;
    
    let score = 0;
    
    // Length check
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    
    // Character type checks
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    // Update strength indicator
    strengthIndicator.className = 'password-strength';
    if (password.length === 0) {
        strengthIndicator.className += ' empty';
    } else if (score <= 2) {
        strengthIndicator.className += ' weak';
    } else if (score <= 4) {
        strengthIndicator.className += ' fair';
    } else if (score <= 5) {
        strengthIndicator.className += ' good';
    } else {
        strengthIndicator.className += ' strong';
    }
}

function updatePasswordRequirements(password) {
    const requirements = [
        { id: 'length-req', test: password.length >= 8 },
        { id: 'uppercase-req', test: /[A-Z]/.test(password) },
        { id: 'lowercase-req', test: /[a-z]/.test(password) },
        { id: 'number-req', test: /[0-9]/.test(password) },
        { id: 'special-req', test: /[^A-Za-z0-9]/.test(password) }
    ];
    
    requirements.forEach(req => {
        const element = document.getElementById(req.id);
        if (element) {
            const icon = element.querySelector('i');
            if (req.test) {
                element.classList.add('valid');
                icon.className = 'fas fa-check';
            } else {
                element.classList.remove('valid');
                icon.className = 'fas fa-times';
            }
        }
    });
}

function validatePasswordMatch() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const confirmInput = document.getElementById('confirm_password');
    
    if (confirmPassword && newPassword !== confirmPassword) {
        confirmInput.parentElement.classList.add('error');
        showFieldError(confirmInput, 'Les mots de passe ne correspondent pas');
    } else {
        confirmInput.parentElement.classList.remove('error');
        hideFieldError(confirmInput);
    }
}

// Form Validation
function setupFormValidation() {
    const forms = document.querySelectorAll('.settings-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                showTemporaryMessage('Veuillez corriger les erreurs dans le formulaire', 'error');
                return;
            }
            
            showFormLoadingState(this);
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.parentElement.classList.contains('error')) {
                    validateField(this);
                }
            });
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    return isValid;
}

function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const isRequired = field.hasAttribute('required');
    
    // Clear previous errors
    field.parentElement.classList.remove('error', 'success');
    hideFieldError(field);
    
    // Required field validation
    if (isRequired && !value) {
        showFieldError(field, 'Ce champ est obligatoire');
        return false;
    }
    
    // Type-specific validation
    if (value) {
        switch(type) {
            case 'email':
                if (!isValidEmail(value)) {
                    showFieldError(field, 'Adresse email invalide');
                    return false;
                }
                break;
            case 'tel':
                if (!isValidPhone(value)) {
                    showFieldError(field, 'Numéro de téléphone invalide');
                    return false;
                }
                break;
            case 'password':
                if (field.id === 'new_password' && value.length < 8) {
                    showFieldError(field, 'Le mot de passe doit contenir au moins 8 caractères');
                    return false;
                }
                break;
        }
    }
    
    // Show success state
    field.parentElement.classList.add('success');
    return true;
}

function showFieldError(field, message) {
    field.parentElement.classList.add('error');
    
    let errorElement = field.parentElement.querySelector('.error-message');
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        field.parentElement.appendChild(errorElement);
    }
    
    errorElement.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
}

function hideFieldError(field) {
    const errorElement = field.parentElement.querySelector('.error-message');
    if (errorElement) {
        errorElement.remove();
    }
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPhone(phone) {
    const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
    return phoneRegex.test(phone);
}

function showFormLoadingState(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
        submitBtn.dataset.originalText = originalText;
    }
}

// Toggle Switches
function setupToggleSwitches() {
    const toggles = document.querySelectorAll('.toggle-switch input');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const setting = this.name;
            const value = this.checked;
            
            // Save setting to localStorage
            localStorage.setItem(`setting_${setting}`, value);
            
            // Show feedback
            showTemporaryMessage(
                `Paramètre ${value ? 'activé' : 'désactivé'}`, 
                'success', 
                2000
            );
            
            // Handle specific settings
            handleToggleChange(setting, value);
        });
    });
}

function handleToggleChange(setting, value) {
    switch(setting) {
        case 'maintenance_mode':
            if (value) {
                showConfirmDialog(
                    'Mode maintenance',
                    'Activer le mode maintenance empêchera les résidents d\'accéder au système. Continuer ?',
                    () => {
                        showTemporaryMessage('Mode maintenance activé', 'warning');
                    },
                    () => {
                        // Revert toggle
                        document.querySelector(`input[name="${setting}"]`).checked = false;
                    }
                );
            }
            break;
        case 'debug_mode':
            if (value) {
                showTemporaryMessage('Mode débogage activé - Performance réduite', 'warning');
            }
            break;
    }
}

// File Uploads
function setupFileUploads() {
    const fileInput = document.getElementById('backup-file');
    const uploadZone = document.querySelector('.upload-zone');
    
    if (uploadZone && fileInput) {
        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#805ad5';
            this.style.background = 'rgba(128, 90, 213, 0.05)';
        });
        
        uploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#cbd5e0';
            this.style.background = '';
        });
        
        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#cbd5e0';
            this.style.background = '';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileUpload(files[0]);
            }
        });
        
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFileUpload(this.files[0]);
            }
        });
    }
}

function handleFileUpload(file) {
    // Validate file type
    const allowedTypes = ['.zip', '.sql'];
    const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
    
    if (!allowedTypes.includes(fileExtension)) {
        showTemporaryMessage('Type de fichier non supporté', 'error');
        return;
    }
    
    // Validate file size (max 100MB)
    const maxSize = 100 * 1024 * 1024;
    if (file.size > maxSize) {
        showTemporaryMessage('Fichier trop volumineux (max 100MB)', 'error');
        return;
    }
    
    // Show upload progress
    showUploadProgress(file);
}

function showUploadProgress(file) {
    const progressModal = document.getElementById('progressModal');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const progressMessage = document.getElementById('progressMessage');
    const progressTitle = document.getElementById('progressTitle');
    
    progressTitle.innerHTML = '<i class="fas fa-upload"></i> Upload en cours';
    progressMessage.textContent = `Upload de ${file.name}...`;
    progressModal.style.display = 'block';
    
    // Simulate upload progress
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress >= 100) {
            progress = 100;
            clearInterval(interval);
            
            setTimeout(() => {
                progressModal.style.display = 'none';
                showTemporaryMessage('Fichier uploadé avec succès', 'success');
            }, 1000);
        }
        
        progressFill.style.width = progress + '%';
        progressText.textContent = Math.round(progress) + '%';
    }, 200);
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
    
    // Close buttons
    const closeButtons = document.querySelectorAll('.close');
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function showConfirmDialog(title, message, onConfirm, onCancel) {
    const modal = document.getElementById('confirmModal');
    const titleElement = document.getElementById('confirmTitle');
    const messageElement = document.getElementById('confirmMessage');
    const confirmButton = document.getElementById('confirmAction');
    
    titleElement.innerHTML = `<i class="fas fa-question-circle"></i> ${title}`;
    messageElement.textContent = message;
    
    // Remove previous event listeners
    const newConfirmButton = confirmButton.cloneNode(true);
    confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);
    
    newConfirmButton.addEventListener('click', function() {
        modal.style.display = 'none';
        if (onConfirm) onConfirm();
    });
    
    // Handle cancel
    const cancelButton = modal.querySelector('.btn-secondary');
    const newCancelButton = cancelButton.cloneNode(true);
    cancelButton.parentNode.replaceChild(newCancelButton, cancelButton);
    
    newCancelButton.addEventListener('click', function() {
        modal.style.display = 'none';
        if (onCancel) onCancel();
    });
    
    modal.style.display = 'block';
}

// Backup Functions
function setupBackupFunctions() {
    // Backup type selection
    const backupTypes = document.querySelectorAll('input[name="backup_type"]');
    backupTypes.forEach(radio => {
        radio.addEventListener('change', function() {
            updateBackupEstimate(this.value);
        });
    });
}

function updateBackupEstimate(type) {
    const estimates = {
        'full': '~50 MB',
        'data': '~15 MB',
        'config': '~2 MB'
    };
    
    // Update size display (implementation would depend on UI structure)
    console.log(`Backup estimate: ${estimates[type]}`);
}

function backupData() {
    const selectedType = document.querySelector('input[name="backup_type"]:checked');
    if (!selectedType) {
        showTemporaryMessage('Veuillez sélectionner un type de sauvegarde', 'error');
        return;
    }
    
    showConfirmDialog(
        'Créer une sauvegarde',
        `Créer une sauvegarde ${selectedType.value} ? Cette opération peut prendre quelques minutes.`,
        () => {
            startBackupProcess(selectedType.value);
        }
    );
}

function startBackupProcess(type) {
    const progressModal = document.getElementById('progressModal');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const progressMessage = document.getElementById('progressMessage');
    const progressTitle = document.getElementById('progressTitle');
    
    progressTitle.innerHTML = '<i class="fas fa-database"></i> Sauvegarde en cours';
    progressMessage.textContent = 'Préparation de la sauvegarde...';
    progressModal.style.display = 'block';
    
    const steps = [
        'Préparation de la sauvegarde...',
        'Sauvegarde des données...',
        'Compression des fichiers...',
        'Finalisation...'
    ];
    
    let progress = 0;
    let stepIndex = 0;
    
    const interval = setInterval(() => {
        progress += Math.random() * 10;
        
        if (progress >= (stepIndex + 1) * 25 && stepIndex < steps.length - 1) {
            stepIndex++;
            progressMessage.textContent = steps[stepIndex];
        }
        
        if (progress >= 100) {
            progress = 100;
            clearInterval(interval);
            
            setTimeout(() => {
                progressModal.style.display = 'none';
                showTemporaryMessage('Sauvegarde créée avec succès', 'success');
                refreshBackupHistory();
            }, 1000);
        }
        
        progressFill.style.width = progress + '%';
        progressText.textContent = Math.round(progress) + '%';
    }, 300);
}

function downloadBackup(backupId) {
    showTemporaryMessage('Téléchargement de la sauvegarde...', 'info');
    
    // Simulate download
    setTimeout(() => {
        showTemporaryMessage('Sauvegarde téléchargée avec succès', 'success');
    }, 2000);
}

function restoreBackup(backupId) {
    showConfirmDialog(
        'Restaurer la sauvegarde',
        'ATTENTION: Cette action remplacera toutes les données actuelles. Cette action est irréversible. Continuer ?',
        () => {
            startRestoreProcess(backupId);
        }
    );
}

function startRestoreProcess(backupId) {
    const progressModal = document.getElementById('progressModal');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const progressMessage = document.getElementById('progressMessage');
    const progressTitle = document.getElementById('progressTitle');
    
    progressTitle.innerHTML = '<i class="fas fa-undo"></i> Restauration en cours';
    progressMessage.textContent = 'Préparation de la restauration...';
    progressModal.style.display = 'block';
    
    const steps = [
        'Préparation de la restauration...',
        'Suppression des données actuelles...',
        'Restauration des données...',
        'Reconfiguration du système...',
        'Finalisation...'
    ];
    
    let progress = 0;
    let stepIndex = 0;
    
    const interval = setInterval(() => {
        progress += Math.random() * 8;
        
        if (progress >= (stepIndex + 1) * 20 && stepIndex < steps.length - 1) {
            stepIndex++;
            progressMessage.textContent = steps[stepIndex];
        }
        
        if (progress >= 100) {
            progress = 100;
            clearInterval(interval);
            
            setTimeout(() => {
                progressModal.style.display = 'none';
                showTemporaryMessage('Restauration terminée avec succès', 'success');
            }, 1000);
        }
        
        progressFill.style.width = progress + '%';
        progressText.textContent = Math.round(progress) + '%';
    }, 400);
}

function deleteBackup(backupId) {
    showConfirmDialog(
        'Supprimer la sauvegarde',
        'Êtes-vous sûr de vouloir supprimer cette sauvegarde ? Cette action est irréversible.',
        () => {
            showTemporaryMessage('Sauvegarde supprimée avec succès', 'success');
            refreshBackupHistory();
        }
    );
}

function refreshBackupHistory() {
    // In a real implementation, this would fetch updated backup list
    console.log('Refreshing backup history...');
}

// System Tools
function setupSystemTools() {
    // Tool buttons are already set up in HTML with onclick handlers
}

function clearCache() {
    showConfirmDialog(
        'Vider le cache',
        'Vider le cache peut temporairement ralentir le système. Continuer ?',
        () => {
            simulateSystemOperation('Vidage du cache...', () => {
                showTemporaryMessage('Cache vidé avec succès', 'success');
            });
        }
    );
}

function optimizeDatabase() {
    showConfirmDialog(
        'Optimiser la base de données',
        'L\'optimisation peut prendre plusieurs minutes. Le système sera temporairement indisponible. Continuer ?',
        () => {
            simulateSystemOperation('Optimisation de la base de données...', () => {
                showTemporaryMessage('Base de données optimisée avec succès', 'success');
            });
        }
    );
}

function generateReport() {
    simulateSystemOperation('Génération du rapport système...', () => {
        showTemporaryMessage('Rapport système généré avec succès', 'success');
        // In a real implementation, this would trigger a download
    });
}

function checkUpdates() {
    simulateSystemOperation('Vérification des mises à jour...', () => {
        showTemporaryMessage('Système à jour - Aucune mise à jour disponible', 'success');
    });
}

function simulateSystemOperation(message, onComplete) {
    const progressModal = document.getElementById('progressModal');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const progressMessage = document.getElementById('progressMessage');
    const progressTitle = document.getElementById('progressTitle');
    
    progressTitle.innerHTML = '<i class="fas fa-cog fa-spin"></i> Opération système';
    progressMessage.textContent = message;
    progressModal.style.display = 'block';
    
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 20;
        
        if (progress >= 100) {
            progress = 100;
            clearInterval(interval);
            
            setTimeout(() => {
                progressModal.style.display = 'none';
                if (onComplete) onComplete();
            }, 500);
        }
        
        progressFill.style.width = progress + '%';
        progressText.textContent = Math.round(progress) + '%';
    }, 200);
}

// Animations
function setupAnimations() {
    // Animate settings cards on load
    const settingsCards = document.querySelectorAll('.settings-card');
    settingsCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Animate stat items
    const statItems = document.querySelectorAll('.stat-item');
    statItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-30px)';
        
        setTimeout(() => {
            item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, (index + 2) * 150);
    });

    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-value');
    statNumbers.forEach(stat => {
        const target = parseInt(stat.textContent);
        if (!isNaN(target)) {
            animateNumber(stat, target);
        }
    });
}

function animateNumber(element, target) {
    let current = 0;
    const increment = target / 30;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, 50);
}

// Keyboard Shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + S to save current form
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            saveCurrentForm();
        }
        
        // Ctrl/Cmd + B for backup
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            backupData();
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal[style*="display: block"]');
            openModals.forEach(modal => {
                modal.style.display = 'none';
            });
        }
        
        // Tab navigation with numbers
        if (e.key >= '1' && e.key <= '6' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            const tabIndex = parseInt(e.key) - 1;
            const tabs = document.querySelectorAll('.settings-tab');
            if (tabs[tabIndex]) {
                tabs[tabIndex].click();
            }
        }
        
        // F1 for help
        if (e.key === 'F1') {
            e.preventDefault();
            showKeyboardShortcuts();
        }
    });
}

function saveCurrentForm() {
    const activeSection = document.querySelector('.settings-section.active');
    if (activeSection) {
        const form = activeSection.querySelector('.settings-form');
        if (form) {
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    }
}

function showKeyboardShortcuts() {
    const shortcuts = document.createElement('div');
    shortcuts.className = 'keyboard-shortcuts show';
    shortcuts.innerHTML = `
        <h4>Raccourcis clavier</h4>
        <ul>
            <li><span class="key">Ctrl+S</span> Sauvegarder</li>
            <li><span class="key">Ctrl+B</span> Sauvegarde</li>
            <li><span class="key">Ctrl+1-6</span> Onglets</li>
            <li><span class="key">Échap</span> Fermer</li>
            <li><span class="key">F1</span> Aide</li>
        </ul>
    `;
    
    document.body.appendChild(shortcuts);
    
    setTimeout(() => {
        shortcuts.classList.remove('show');
        setTimeout(() => shortcuts.remove(), 300);
    }, 5000);
}

// Notification System
function setupNotificationSystem() {
    // Request browser notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showTemporaryMessage('Notifications activées', 'success');
            }
        });
    }
}

function showBrowserNotification(title, message, icon = '/favicon.ico') {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification(title, {
            body: message,
            icon: icon,
            tag: 'settings-notification'
        });
    }
}

// Auto-hide alerts
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

// User Preferences
function loadUserPreferences() {
    // Load toggle states
    const toggles = document.querySelectorAll('.toggle-switch input');
    toggles.forEach(toggle => {
        const setting = toggle.name;
        const savedValue = localStorage.getItem(`setting_${setting}`);
        if (savedValue !== null) {
            toggle.checked = savedValue === 'true';
        }
    });
    
    // Load other preferences
    loadNotificationPreferences();
    loadSystemPreferences();
}

function loadNotificationPreferences() {
    const startTime = localStorage.getItem('notification_start_time');
    const endTime = localStorage.getItem('notification_end_time');
    
    if (startTime) {
        const startInput = document.getElementById('notification_start');
        if (startInput) startInput.value = startTime;
    }
    
    if (endTime) {
        const endInput = document.getElementById('notification_end');
        if (endInput) endInput.value = endTime;
    }
}

function loadSystemPreferences() {
    // Load system-specific preferences
    const theme = localStorage.getItem('app_theme');
    if (theme) {
        document.body.className = theme;
    }
}

function saveAllSettings() {
    showConfirmDialog(
        'Sauvegarder tous les paramètres',
        'Sauvegarder tous les paramètres modifiés ? Cela appliquera tous les changements.',
        () => {
            // Simulate saving all settings
            simulateSystemOperation('Sauvegarde des paramètres...', () => {
                showTemporaryMessage('Tous les paramètres ont été sauvegardés', 'success');
                showBrowserNotification('Paramètres', 'Tous les paramètres ont été sauvegardés avec succès');
            });
        }
    );
}

// Section-specific initializations
function initializeProfileSection() {
    // Initialize profile-specific features
    setupAvatarUpload();
    validateProfileForm();
}

function initializeBuildingSection() {
    // Initialize building-specific features
    setupBuildingConfiguration();
}

function initializeNotificationsSection() {
    // Initialize notification preferences
    setupNotificationSchedule();
}

function initializeSecuritySection() {
    // Initialize security features
    setupSecurityMonitoring();
    loadSecurityLog();
}

function initializeSystemSection() {
    // Initialize system monitoring
    updateSystemInfo();
    setupPerformanceMonitoring();
}

function initializeBackupSection() {
    // Initialize backup features
    loadBackupSchedule();
    checkBackupHealth();
}

function setupAvatarUpload() {
    // Implementation for avatar upload
    console.log('Avatar upload setup');
}

function validateProfileForm() {
    // Additional profile validation
    console.log('Profile form validation setup');
}

function setupBuildingConfiguration() {
    // Building configuration setup
    console.log('Building configuration setup');
}

function setupNotificationSchedule() {
    const startInput = document.getElementById('notification_start');
    const endInput = document.getElementById('notification_end');
    
    if (startInput) {
        startInput.addEventListener('change', function() {
            localStorage.setItem('notification_start_time', this.value);
            showTemporaryMessage('Horaires de notification mis à jour', 'success');
        });
    }
    
    if (endInput) {
        endInput.addEventListener('change', function() {
            localStorage.setItem('notification_end_time', this.value);
            showTemporaryMessage('Horaires de notification mis à jour', 'success');
        });
    }
}

function setupSecurityMonitoring() {
    // Monitor security events
    console.log('Security monitoring setup');
}

function loadSecurityLog() {
    // Load recent security events
    console.log('Loading security log');
}

function updateSystemInfo() {
    // Update system information display
    console.log('Updating system info');
}

function setupPerformanceMonitoring() {
    // Monitor system performance
    setInterval(() => {
        updatePerformanceMetrics();
    }, 30000); // Every 30 seconds
}

function updatePerformanceMetrics() {
    // Update performance metrics
    console.log('Updating performance metrics');
}

function loadBackupSchedule() {
    // Load backup schedule configuration
    console.log('Loading backup schedule');
}

function checkBackupHealth() {
    // Check backup system health
    console.log('Checking backup health');
}

// Utility Functions
function showTemporaryMessage(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = 'success-feedback';
    notification.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: ${type === 'success' ? '#48bb78' : type === 'warning' ? '#ed8936' : type === 'error' ? '#f56565' : '#805ad5'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 9999;
        font-size: 0.9rem;
        font-weight: 600;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.style.transform = 'translateX(0)', 100);
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    }, duration);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Error Handling
window.addEventListener('error', function(e) {
    console.error('Settings page error:', e.error);
    showTemporaryMessage('Une erreur s\'est produite. Veuillez actualiser la page.', 'error', 10000);
});

// Cleanup
window.addEventListener('beforeunload', function() {
    // Save any pending changes
    const activeForm = document.querySelector('.settings-section.active .settings-form');
    if (activeForm) {
        // Auto-save form data to localStorage
        const formData = new FormData(activeForm);
        for (let [key, value] of formData.entries()) {
            localStorage.setItem(`autosave_${key}`, value);
        }
    }
});

// Performance monitoring
window.addEventListener('load', function() {
    const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
    console.log(`Settings page loaded in ${loadTime}ms`);
    
    if (loadTime > 3000) {
        console.warn('Slow page load detected');
    }
});

// Export main functions for external use
window.SettingsManager = {
    showConfirmDialog,
    showTemporaryMessage,
    backupData,
    clearCache,
    optimizeDatabase,
    generateReport,
    checkUpdates,
    saveAllSettings,
    formatDate,
    formatDateTime,
    formatFileSize
};

console.log('Settings.js loaded successfully');