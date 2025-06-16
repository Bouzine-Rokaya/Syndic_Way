// Announcements.js - JavaScript for announcements management

document.addEventListener('DOMContentLoaded', function() {
    initializeAnnouncementsPage();
});

function initializeAnnouncementsPage() {
    setupFilters();
    setupModals();
    setupAnimations();
    setupMobileMenu();
    setupCharacterCounter();
    setupAudienceSelection();
    setupFormValidation();
    animateStatistics();
    autoHideAlerts();
    setupKeyboardShortcuts();
    createFloatingActionButton();
}

// Filter Functions
function setupFilters() {
    const searchInput = document.getElementById('search');
    const prioritySelect = document.getElementById('priority');
    const categorySelect = document.getElementById('category');
    const dateInput = document.getElementById('date');
    
    // Real-time search with debounce
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (e.target.value.length >= 2 || e.target.value.length === 0) {
                    document.getElementById('filtersForm').submit();
                }
            }, 500);
        });
    }

    // Auto-submit on filter change
    [prioritySelect, categorySelect, dateInput].forEach(element => {
        if (element) {
            element.addEventListener('change', function() {
                document.getElementById('filtersForm').submit();
            });
        }
    });
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
}

// Announcement Actions
function openCreateModal() {
    resetAnnouncementForm();
    document.getElementById('modalTitle').innerHTML = 
        '<i class="fas fa-plus"></i> Nouvelle annonce';
    document.getElementById('submitText').textContent = 'Publier l\'annonce';
    document.getElementById('announcementModal').style.display = 'block';
    
    // Focus on title field
    setTimeout(() => {
        document.getElementById('title').focus();
    }, 100);
}

function createUrgentAnnouncement() {
    openCreateModal();
    
    // Pre-fill for urgent announcement
    document.getElementById('title').value = 'URGENT - ';
    document.getElementById('category_select').value = 'emergency';
    document.getElementById('priority_select').value = 'urgent';
    document.getElementById('send_notification').checked = true;
    document.getElementById('pin_announcement').checked = true;
    
    // Focus at end of title
    const titleField = document.getElementById('title');
    setTimeout(() => {
        titleField.focus();
        titleField.setSelectionRange(titleField.value.length, titleField.value.length);
    }, 100);
}

function createMaintenanceAnnouncement() {
    openCreateModal();
    useTemplate('maintenance');
}

function createMeetingAnnouncement() {
    openCreateModal();
    useTemplate('meeting');
}

function createGeneralAnnouncement() {
    openCreateModal();
    useTemplate('general');
}

function openTemplatesModal() {
    document.getElementById('templatesModal').style.display = 'block';
}

function useTemplate(templateType) {
    const templates = {
        maintenance: {
            title: 'Maintenance programmée',
            category: 'maintenance',
            priority: 'normal',
            content: `Chers résidents,

Nous vous informons qu'une maintenance est programmée le [DATE] de [HEURE] à [HEURE].

Travaux concernés :
- [DÉTAILS DES TRAVAUX]

Pendant cette période, [SERVICES AFFECTÉS] seront temporairement indisponibles.

Merci de votre compréhension.

Cordialement,
La gestion`
        },
        meeting: {
            title: 'Convocation - Assemblée Générale',
            category: 'meeting',
            priority: 'high',
            content: `Chers copropriétaires,

Vous êtes convoqués à l'Assemblée Générale qui se tiendra le [DATE] à [HEURE] en [LIEU].

Ordre du jour :
1. Approbation des comptes de l'exercice précédent
2. Présentation du budget prévisionnel
3. Travaux de rénovation
4. Questions diverses

Votre présence est importante pour les décisions concernant notre copropriété.

Cordialement,
Le syndic`
        },
        urgent: {
            title: 'URGENT - Action immédiate requise',
            category: 'emergency',
            priority: 'urgent',
            content: `ATTENTION - Message urgent

[DESCRIPTION DE LA SITUATION]

Actions à entreprendre immédiatement :
- [ACTION 1]
- [ACTION 2]

Pour toute urgence, contactez : [NUMÉRO D'URGENCE]

Merci de votre coopération.`
        },
        payment: {
            title: 'Rappel - Charges de copropriété',
            category: 'financial',
            priority: 'high',
            content: `Chers résidents,

Nous vous rappelons que le paiement des charges pour le trimestre [PÉRIODE] est dû.

Montant : [MONTANT] €
Date limite : [DATE LIMITE]

Modalités de paiement :
- Virement bancaire : [IBAN]
- Chèque à l'ordre de : [ORDRE]

Merci de régulariser votre situation dans les délais.

Cordialement,
La gestion`
        },
        rules: {
            title: 'Rappel du règlement intérieur',
            category: 'rules',
            priority: 'normal',
            content: `Chers résidents,

Nous souhaitons vous rappeler quelques points importants du règlement intérieur :

- [POINT 1]
- [POINT 2]  
- [POINT 3]

Le respect de ces règles assure le bien-vivre ensemble dans notre copropriété.

Merci de votre collaboration.

Cordialement,
Le syndic`
        },
        event: {
            title: 'Invitation - Événement',
            category: 'general',
            priority: 'normal',
            content: `Chers résidents,

Nous avons le plaisir de vous inviter à [ÉVÉNEMENT] qui aura lieu le [DATE] à [HEURE].

Au programme :
- [ACTIVITÉ 1]
- [ACTIVITÉ 2]

Lieu : [LIEU]
Inscription : [MODALITÉS]

Nous espérons vous voir nombreux !

Cordialement,
L'équipe de gestion`
        },
        general: {
            title: 'Information importante',
            category: 'general',
            priority: 'normal',
            content: `Chers résidents,

Nous souhaitons vous informer que [INFORMATION].

[DÉTAILS COMPLÉMENTAIRES]

Pour toute question, n'hésitez pas à nous contacter.

Cordialement,
La gestion`
        }
    };
    
    if (templates[templateType]) {
        const template = templates[templateType];
        document.getElementById('title').value = template.title;
        document.getElementById('category_select').value = template.category;
        document.getElementById('priority_select').value = template.priority;
        document.getElementById('content').value = template.content;
        
        updateCharacterCounter();
        
        // Close templates modal and open create modal
        closeModal('templatesModal');
        openCreateModal();
    }
}

function viewAnnouncement(announcementDate) {
    // In a real implementation, you would fetch the full announcement details
    const viewContent = document.getElementById('view-content');
    viewContent.innerHTML = `
        <div class="announcement-details">
            <div class="detail-header">
                <h3>Annonce du ${formatDate(announcementDate)}</h3>
                <span class="priority-badge normal">
                    <i class="fas fa-info-circle"></i> Normale
                </span>
            </div>
            
            <div class="detail-content">
                <p><strong>Publié le :</strong> ${formatDateTime(announcementDate)}</p>
                <p><strong>Catégorie :</strong> Information générale</p>
                <p><strong>Contenu :</strong></p>
                <div class="content-text">
                    Annonce publiée pour tous les résidents de la copropriété.
                </div>
            </div>
            
            <div class="detail-stats">
                <div class="stat-item">
                    <i class="fas fa-users"></i>
                    <span>Destinataires: Tous les résidents</span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-eye"></i>
                    <span>Lectures: En attente de développement</span>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('viewModal').style.display = 'block';
}

function duplicateAnnouncement(announcementDate) {
    openCreateModal();
    
    // In a real implementation, you would fetch the announcement details
    // For now, we'll just populate with placeholder data
    document.getElementById('title').value = 'Copie - Annonce du ' + formatDate(announcementDate);
    document.getElementById('content').value = 'Contenu dupliqué de l\'annonce précédente...';
    
    updateCharacterCounter();
    showTemporaryMessage('Annonce dupliquée - Modifiez le contenu avant publication', 'info');
}

function deleteAnnouncement(announcementDate) {
    const confirmMessage = `Êtes-vous sûr de vouloir supprimer l'annonce du ${formatDate(announcementDate)} ?\n\nCette action est irréversible.`;
    
    if (confirm(confirmMessage)) {
        document.getElementById('deleteAnnouncementDate').value = announcementDate;
        document.getElementById('deleteForm').submit();
    }
}

function resetAnnouncementForm() {
    document.getElementById('announcementForm').reset();
    document.getElementById('formAction').value = 'create_announcement';
    
    // Reset audience selection
    document.getElementById('audience_all').checked = true;
    document.getElementById('specific_residents_section').style.display = 'none';
    
    // Set default publication date
    const now = new Date();
    const formattedDate = now.toISOString().slice(0, 16);
    document.getElementById('publication_date').value = formattedDate;
    
    updateCharacterCounter();
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Character Counter
function setupCharacterCounter() {
    const contentTextarea = document.getElementById('content');
    const charCounter = document.querySelector('.char-counter');
    
    if (contentTextarea && charCounter) {
        contentTextarea.addEventListener('input', updateCharacterCounter);
        updateCharacterCounter(); // Initial count
    }
}

function updateCharacterCounter() {
    const contentTextarea = document.getElementById('content');
    const charCounter = document.querySelector('.char-counter');
    
    if (contentTextarea && charCounter) {
        const currentLength = contentTextarea.value.length;
        const maxLength = 2000;
        
        charCounter.textContent = `${currentLength}/${maxLength} caractères`;
        
        // Update counter color based on usage
        charCounter.className = 'char-counter';
        if (currentLength > maxLength * 0.9) {
            charCounter.classList.add('danger');
        } else if (currentLength > maxLength * 0.7) {
            charCounter.classList.add('warning');
        }
        
        // Update textarea border
        if (currentLength > maxLength) {
            contentTextarea.style.borderColor = '#f56565';
        } else {
            contentTextarea.style.borderColor = '#e2e8f0';
        }
    }
}

// Audience Selection
function setupAudienceSelection() {
    const audienceRadios = document.querySelectorAll('input[name="target_audience"]');
    const specificSection = document.getElementById('specific_residents_section');
    
    audienceRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'specific') {
                specificSection.style.display = 'block';
            } else {
                specificSection.style.display = 'none';
                // Uncheck all specific residents
                document.querySelectorAll('input[name="specific_residents[]"]').forEach(checkbox => {
                    checkbox.checked = false;
                });
            }
        });
    });
    
    // Add select all functionality for specific residents
    if (specificSection) {
        const selectAllBtn = document.createElement('button');
        selectAllBtn.type = 'button';
        selectAllBtn.className = 'btn btn-sm btn-secondary mb-3';
        selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Tout sélectionner';
        selectAllBtn.onclick = toggleAllResidents;
        
        specificSection.insertBefore(selectAllBtn, specificSection.firstChild);
    }
}

function toggleAllResidents() {
    const checkboxes = document.querySelectorAll('input[name="specific_residents[]"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });
    
    const btn = event.target;
    if (allChecked) {
        btn.innerHTML = '<i class="fas fa-check-square"></i> Tout sélectionner';
    } else {
        btn.innerHTML = '<i class="fas fa-square"></i> Tout désélectionner';
    }
}

// Form Validation
function setupFormValidation() {
    const announcementForm = document.getElementById('announcementForm');
    if (announcementForm) {
        announcementForm.addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const content = document.getElementById('content').value.trim();
            const category = document.getElementById('category_select').value;
            const priority = document.getElementById('priority_select').value;
            const targetAudience = document.querySelector('input[name="target_audience"]:checked').value;
            
            // Validate title
            if (!title) {
                e.preventDefault();
                alert('Veuillez saisir un titre pour l\'annonce.');
                document.getElementById('title').focus();
                return;
            }
            
            if (title.length < 5) {
                e.preventDefault();
                alert('Le titre doit contenir au moins 5 caractères.');
                document.getElementById('title').focus();
                return;
            }
            
            // Validate content
            if (!content) {
                e.preventDefault();
                alert('Veuillez saisir le contenu de l\'annonce.');
                document.getElementById('content').focus();
                return;
            }
            
            if (content.length < 20) {
                e.preventDefault();
                alert('Le contenu doit contenir au moins 20 caractères.');
                document.getElementById('content').focus();
                return;
            }
            
            if (content.length > 2000) {
                e.preventDefault();
                alert('Le contenu est trop long (maximum 2000 caractères).');
                document.getElementById('content').focus();
                return;
            }
            
            // Validate category and priority
            if (!category) {
                e.preventDefault();
                alert('Veuillez sélectionner une catégorie.');
                document.getElementById('category_select').focus();
                return;
            }
            
            if (!priority) {
                e.preventDefault();
                alert('Veuillez sélectionner une priorité.');
                document.getElementById('priority_select').focus();
                return;
            }
            
            // Validate specific residents selection
            if (targetAudience === 'specific') {
                const selectedResidents = document.querySelectorAll('input[name="specific_residents[]"]:checked');
                if (selectedResidents.length === 0) {
                    e.preventDefault();
                    alert('Veuillez sélectionner au moins un résident.');
                    return;
                }
            }
            
            // Confirm urgent announcements
            if (priority === 'urgent') {
                if (!confirm('Vous êtes sur le point de publier une annonce URGENTE. Confirmer ?')) {
                    e.preventDefault();
                    return;
                }
            }

            // Show loading state
            showFormLoadingState();
        });
    }
}

function showFormLoadingState() {
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publication...';
        submitBtn.classList.add('loading');
        
        // Store original text for potential restoration
        submitBtn.dataset.originalText = originalText;
    }
}

// Preview Functionality
function previewAnnouncement() {
    const title = document.getElementById('title').value.trim();
    const content = document.getElementById('content').value.trim();
    const category = document.getElementById('category_select').value;
    const priority = document.getElementById('priority_select').value;
    const targetAudience = document.querySelector('input[name="target_audience"]:checked').value;
    
    if (!title || !content) {
        alert('Veuillez remplir au moins le titre et le contenu pour voir l\'aperçu.');
        return;
    }
    
    const categoryNames = {
        'general': 'Information générale',
        'maintenance': 'Maintenance / Travaux',
        'meeting': 'Assemblée générale',
        'emergency': 'Urgence',
        'financial': 'Financier',
        'rules': 'Règlement'
    };
    
    const priorityNames = {
        'low': 'Faible',
        'normal': 'Normale',
        'high': 'Haute',
        'urgent': 'Urgente'
    };
    
    const audienceText = targetAudience === 'all' ? 'Tous les résidents' : 'Résidents sélectionnés';
    const selectedCount = targetAudience === 'specific' 
        ? document.querySelectorAll('input[name="specific_residents[]"]:checked').length 
        : document.querySelectorAll('input[name="specific_residents[]"]').length;
    
    const previewContent = document.getElementById('preview-content');
    previewContent.innerHTML = `
        <div class="preview-header">
            <div class="preview-title">${escapeHtml(title)}</div>
            <div class="preview-date">${new Date().toLocaleDateString('fr-FR')}</div>
        </div>
        
        <div class="preview-meta">
            <div class="preview-category">
                <strong>Catégorie:</strong> ${categoryNames[category] || category}
            </div>
            <div class="preview-priority">
                <strong>Priorité:</strong> 
                <span class="priority-badge ${priority}">
                    <i class="fas fa-${priority === 'urgent' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    ${priorityNames[priority] || priority}
                </span>
            </div>
        </div>
        
        <div class="preview-content">
            ${escapeHtml(content).replace(/\n/g, '<br>')}
        </div>
        
        <div class="preview-meta">
            <div><strong>Destinataires:</strong> ${audienceText} (${selectedCount} personne${selectedCount > 1 ? 's' : ''})</div>
            <div><strong>Publié par:</strong> Syndic</div>
        </div>
    `;
    
    document.getElementById('previewModal').style.display = 'block';
}

function publishFromPreview() {
    // Submit the form from preview
    document.getElementById('announcementForm').submit();
}

// Animations
function setupAnimations() {
    // Animate announcement cards on load
    const announcementCards = document.querySelectorAll('.announcement-card');
    announcementCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
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

    // Template cards animation
    const templateCards = document.querySelectorAll('.template-card');
    templateCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.05)';
            this.querySelector('.template-icon').style.transform = 'scale(1.2) rotate(5deg)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.querySelector('.template-icon').style.transform = 'scale(1) rotate(0deg)';
        });
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

// Mobile Menu and Floating Action Button
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
                background: #9f7aea;
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

function createFloatingActionButton() {
    if (window.innerWidth <= 768) {
        const existingFab = document.querySelector('.fab');
        if (existingFab) return;

        const fab = document.createElement('button');
        fab.className = 'fab';
        fab.innerHTML = '<i class="fas fa-plus"></i>';
        fab.onclick = openCreateModal;
        
        document.body.appendChild(fab);
    }
}

// Keyboard Shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + N to create new announcement
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            openCreateModal();
        }
        
        // Ctrl/Cmd + U for urgent announcement
        if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
            e.preventDefault();
            createUrgentAnnouncement();
        }
        
        // Ctrl/Cmd + T for templates
        if ((e.ctrlKey || e.metaKey) && e.key === 't') {
            e.preventDefault();
            openTemplatesModal();
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal[style*="display: block"]');
            openModals.forEach(modal => {
                modal.style.display = 'none';
            });
        }
        
        // Ctrl/Cmd + Enter to submit form in modal
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const announcementModal = document.getElementById('announcementModal');
            if (announcementModal && announcementModal.style.display === 'block') {
                e.preventDefault();
                document.getElementById('announcementForm').submit();
            }
        }
        
        // Ctrl/Cmd + P for preview
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            const announcementModal = document.getElementById('announcementModal');
            if (announcementModal && announcementModal.style.display === 'block') {
                e.preventDefault();
                previewAnnouncement();
            }
        }
    });
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

// Utility Functions
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

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showTemporaryMessage(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = 'success-feedback';
    notification.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: ${type === 'success' ? '#48bb78' : type === 'warning' ? '#ed8936' : type === 'error' ? '#f56565' : '#9f7aea'};
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 9999;
        font-size: 0.9rem;
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

// Export Functions
function exportAnnouncements() {
    const announcements = Array.from(document.querySelectorAll('.announcement-card')).map(card => ({
        date: card.querySelector('.announcement-date .date-day').textContent + '/' + 
              card.querySelector('.announcement-date .date-month').textContent + '/' +
              card.querySelector('.announcement-date .date-year').textContent,
        time: card.querySelector('.announcement-time').textContent.replace(/.*(\d{2}:\d{2}).*/, '$1'),
        title: card.querySelector('.announcement-title').textContent.trim(),
        priority: card.querySelector('.priority-badge').textContent.trim(),
        recipients: card.querySelector('.audience-count').textContent,
        preview: card.querySelector('.announcement-preview').textContent.trim()
    }));
    
    downloadCSV(announcements, 'annonces');
}

function downloadCSV(data, filename) {
    const headers = ['Date', 'Heure', 'Titre', 'Priorité', 'Destinataires', 'Aperçu'];
    const csvContent = [
        headers.join(','),
        ...data.map(row => [
            `"${row.date}"`,
            `"${row.time}"`,
            `"${row.title}"`,
            `"${row.priority}"`,
            `"${row.recipients}"`,
            `"${row.preview}"`
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

// Advanced Features
function setupAdvancedFeatures() {
    setupAutoSave();
    setupRealTimeUpdates();
    setupNotificationPermissions();
    setupBulkOperations();
}

function setupAutoSave() {
    // Auto-save draft announcements
    const titleInput = document.getElementById('title');
    const contentTextarea = document.getElementById('content');
    const categorySelect = document.getElementById('category_select');
    const prioritySelect = document.getElementById('priority_select');
    
    [titleInput, contentTextarea, categorySelect, prioritySelect].forEach(input => {
        if (input) {
            input.addEventListener('input', debounce(() => {
                saveDraft();
            }, 1000));
        }
    });
    
    // Load draft on modal open
    const announcementModal = document.getElementById('announcementModal');
    if (announcementModal) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    if (announcementModal.style.display === 'block') {
                        loadDraft();
                    }
                }
            });
        });
        
        observer.observe(announcementModal, { attributes: true });
    }
}

function saveDraft() {
    const draft = {
        title: document.getElementById('title')?.value || '',
        content: document.getElementById('content')?.value || '',
        category: document.getElementById('category_select')?.value || '',
        priority: document.getElementById('priority_select')?.value || '',
        timestamp: Date.now()
    };
    
    localStorage.setItem('announcement_draft', JSON.stringify(draft));
    showDraftSavedIndicator();
}

function loadDraft() {
    const draft = localStorage.getItem('announcement_draft');
    if (draft) {
        try {
            const draftData = JSON.parse(draft);
            
            // Only load if draft is less than 24 hours old
            if (Date.now() - draftData.timestamp < 24 * 60 * 60 * 1000) {
                if (draftData.title) document.getElementById('title').value = draftData.title;
                if (draftData.content) document.getElementById('content').value = draftData.content;
                if (draftData.category) document.getElementById('category_select').value = draftData.category;
                if (draftData.priority) document.getElementById('priority_select').value = draftData.priority;
                
                updateCharacterCounter();
                showDraftLoadedIndicator();
            }
        } catch (e) {
            console.warn('Failed to load draft:', e);
        }
    }
}

function clearDraft() {
    localStorage.removeItem('announcement_draft');
}

function showDraftSavedIndicator() {
    showTemporaryMessage('Brouillon sauvegardé', 'info', 2000);
}

function showDraftLoadedIndicator() {
    showTemporaryMessage('Brouillon restauré', 'success', 3000);
}

function setupRealTimeUpdates() {
    // Simulate real-time updates
    setInterval(() => {
        updateAnnouncementCounts();
    }, 30000); // Check every 30 seconds
}

function updateAnnouncementCounts() {
    // This would typically fetch new counts from the server
    // For now, we'll simulate minor updates
    const totalStat = document.querySelector('.total-stat .stat-number');
    if (totalStat && Math.random() > 0.9) {
        const currentCount = parseInt(totalStat.textContent);
        const newCount = currentCount + (Math.random() > 0.7 ? 1 : 0);
        if (newCount > currentCount) {
            animateNumber(totalStat, newCount);
            showTemporaryMessage('Nouvelle annonce publiée', 'info');
        }
    }
}

function setupNotificationPermissions() {
    // Request notification permissions for browser notifications
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showTemporaryMessage('Notifications activées', 'success');
            }
        });
    }
}

function setupBulkOperations() {
    // Add bulk operations for announcement management
    // This would be expanded in a full implementation
    console.log('Bulk operations setup ready for implementation');
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Performance Monitoring
function setupPerformanceMonitoring() {
    // Monitor load times
    window.addEventListener('load', function() {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        console.log(`Announcements page loaded in ${loadTime}ms`);
        
        if (loadTime > 3000) {
            console.warn('Slow page load detected');
        }
    });
}

// Form submission cleanup
document.getElementById('announcementForm')?.addEventListener('submit', function() {
    clearDraft();
});

// Window resize handler
window.addEventListener('resize', function() {
    createFloatingActionButton();
    const existingFab = document.querySelector('.fab');
    if (window.innerWidth > 768 && existingFab) {
        existingFab.remove();
    }
});

// Initialize advanced features
document.addEventListener('DOMContentLoaded', function() {
    setupAdvancedFeatures();
    setupPerformanceMonitoring();
});

// Add CSS for additional features
const style = document.createElement('style');
style.textContent = `
    .draft-indicator {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: #9f7aea;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .keyboard-shortcuts {
        position: fixed;
        bottom: 1rem;
        left: 1rem;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 0.5rem;
        border-radius: 6px;
        font-size: 0.7rem;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 1000;
    }
    
    .keyboard-shortcuts.show {
        opacity: 1;
    }
    
    .announcement-card.featured {
        border-color: #9f7aea;
        box-shadow: 0 8px 25px rgba(159, 122, 234, 0.3);
    }
    
    .announcement-card.featured::before {
        content: '⭐';
        position: absolute;
        top: 1rem;
        right: 1rem;
        font-size: 1.2rem;
        z-index: 1;
    }
`;
document.head.appendChild(style);

// Add keyboard shortcuts helper
document.addEventListener('keydown', function(e) {
    if (e.key === 'F1') {
        e.preventDefault();
        showKeyboardShortcuts();
    }
});

function showKeyboardShortcuts() {
    const shortcuts = document.createElement('div');
    shortcuts.className = 'keyboard-shortcuts show';
    shortcuts.innerHTML = `
        <strong>Raccourcis clavier:</strong><br>
        Ctrl+N: Nouvelle annonce<br>
        Ctrl+U: Annonce urgente<br>
        Ctrl+T: Modèles<br>
        Ctrl+P: Aperçu<br>
        Échap: Fermer<br>
        F1: Aide
    `;
    
    document.body.appendChild(shortcuts);
    
    setTimeout(() => {
        shortcuts.classList.remove('show');
        setTimeout(() => shortcuts.remove(), 300);
    }, 5000);
}