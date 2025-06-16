// Messages.js - JavaScript for messages management

document.addEventListener('DOMContentLoaded', function() {
    initializeMessagesPage();
});

function initializeMessagesPage() {
    setupFilters();
    setupModals();
    setupAnimations();
    setupMobileMenu();
    setupContactsSearch();
    setupMessageSelection();
    setupCharacterCounter();
    animateStatistics();
    autoHideAlerts();
    setupKeyboardShortcuts();
}

// Filter Functions
function setupFilters() {
    const searchInput = document.getElementById('search');
    const statusSelect = document.getElementById('status');
    const contactSelect = document.getElementById('contact');
    
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
    [statusSelect, contactSelect].forEach(element => {
        if (element) {
            element.addEventListener('change', function() {
                document.getElementById('filtersForm').submit();
            });
        }
    });
}

// Contacts Search
function setupContactsSearch() {
    const contactsSearch = document.getElementById('contactsSearch');
    if (contactsSearch) {
        contactsSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const contactItems = document.querySelectorAll('.contact-item');
            
            contactItems.forEach(item => {
                const contactName = item.querySelector('.contact-name').textContent.toLowerCase();
                const contactEmail = item.querySelector('.contact-email').textContent.toLowerCase();
                const contactApartment = item.querySelector('.contact-apartment').textContent.toLowerCase();
                
                if (contactName.includes(searchTerm) || 
                    contactEmail.includes(searchTerm) || 
                    contactApartment.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
}

// Contact Selection
function selectContact(contactId, contactName) {
    // Remove active class from all contacts
    document.querySelectorAll('.contact-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to selected contact
    const selectedContact = document.querySelector(`[data-contact-id="${contactId}"]`);
    if (selectedContact) {
        selectedContact.classList.add('active');
    }
    
    // Filter messages for this contact
    filterMessagesByContact(contactId);
    
    // Show contact info or actions if needed
    showContactActions(contactId, contactName);
}

function filterMessagesByContact(contactId) {
    const messageItems = document.querySelectorAll('.message-item');
    
    messageItems.forEach(item => {
        const messageContactId = item.dataset.messageId.split('-')[0];
        if (messageContactId === contactId.toString()) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function showContactActions(contactId, contactName) {
    // You could show a header with contact info and quick actions
    const messagesHeader = document.querySelector('.messages-header');
    let contactHeader = messagesHeader.querySelector('.contact-header');
    
    if (!contactHeader) {
        contactHeader = document.createElement('div');
        contactHeader.className = 'contact-header';
        messagesHeader.appendChild(contactHeader);
    }
    
    contactHeader.innerHTML = `
        <div class="selected-contact-info">
            <h4><i class="fas fa-user"></i> ${contactName}</h4>
            <div class="contact-actions">
                <button class="btn btn-sm btn-primary" onclick="composeToContact(${contactId}, '${contactName}')">
                    <i class="fas fa-plus"></i> Nouveau message
                </button>
                <button class="btn btn-sm btn-secondary" onclick="clearContactSelection()">
                    <i class="fas fa-times"></i> Voir tout
                </button>
            </div>
        </div>
    `;
}

function clearContactSelection() {
    // Remove active class from all contacts
    document.querySelectorAll('.contact-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Show all messages
    document.querySelectorAll('.message-item').forEach(item => {
        item.style.display = 'flex';
    });
    
    // Remove contact header
    const contactHeader = document.querySelector('.contact-header');
    if (contactHeader) {
        contactHeader.remove();
    }
}

// Message Actions
function openComposeModal() {
    resetComposeForm();
    document.getElementById('composeModalTitle').innerHTML = 
        '<i class="fas fa-plus"></i> Nouveau message';
    document.getElementById('composeModal').style.display = 'block';
    
    // Focus on recipient select
    setTimeout(() => {
        document.getElementById('receiver_select').focus();
    }, 100);
}

function composeToContact(contactId, contactName) {
    resetComposeForm();
    
    // Pre-select the contact
    document.getElementById('receiver_select').value = contactId;
    document.getElementById('receiverId').value = contactId;
    
    // Update modal title
    document.getElementById('composeModalTitle').innerHTML = 
        `<i class="fas fa-plus"></i> Message à ${contactName}`;
    
    document.getElementById('composeModal').style.display = 'block';
    
    // Focus on subject field
    setTimeout(() => {
        document.getElementById('subject').focus();
    }, 100);
}

function replyToMessage(contactId, contactName) {
    resetComposeForm();
    
    // Pre-fill recipient
    document.getElementById('receiver_select').value = contactId;
    document.getElementById('receiverId').value = contactId;
    
    // Set subject with "Re:" prefix
    document.getElementById('subject').value = 'Re: ';
    
    // Update modal title
    document.getElementById('composeModalTitle').innerHTML = 
        `<i class="fas fa-reply"></i> Répondre à ${contactName}`;
    
    document.getElementById('composeModal').style.display = 'block';
    
    // Focus on subject field at the end
    setTimeout(() => {
        const subjectField = document.getElementById('subject');
        subjectField.focus();
        subjectField.setSelectionRange(subjectField.value.length, subjectField.value.length);
    }, 100);
}

function deleteMessage(messageId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce message ?')) {
        document.getElementById('deleteMessageIds').value = messageId;
        document.getElementById('deleteMessagesForm').submit();
    }
}

function resetComposeForm() {
    document.getElementById('composeForm').reset();
    document.getElementById('receiverId').value = '';
    updateCharacterCounter();
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Message Selection
function setupMessageSelection() {
    // Header checkbox for select all
    const selectAllCheckbox = document.createElement('input');
    selectAllCheckbox.type = 'checkbox';
    selectAllCheckbox.id = 'selectAllMessages';
    selectAllCheckbox.addEventListener('change', function() {
        const messageCheckboxes = document.querySelectorAll('.message-select');
        messageCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectionActions();
    });
    
    // Add to header if it doesn't exist
    const messagesHeader = document.querySelector('.messages-header');
    if (messagesHeader && !document.getElementById('selectAllMessages')) {
        const selectAllContainer = document.createElement('div');
        selectAllContainer.className = 'select-all-container';
        selectAllContainer.style.cssText = 'margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem;';
        selectAllContainer.innerHTML = `
            <input type="checkbox" id="selectAllMessages">
            <label for="selectAllMessages">Sélectionner tous les messages</label>
        `;
        messagesHeader.appendChild(selectAllContainer);
        
        // Re-attach event listener
        document.getElementById('selectAllMessages').addEventListener('change', function() {
            const messageCheckboxes = document.querySelectorAll('.message-select');
            messageCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectionActions();
        });
    }
    
    // Individual message checkboxes
    document.querySelectorAll('.message-select').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectionActions);
    });
}

function updateSelectionActions() {
    const selectedCheckboxes = document.querySelectorAll('.message-select:checked');
    const actionBar = getOrCreateActionBar();
    
    if (selectedCheckboxes.length > 0) {
        actionBar.style.display = 'flex';
        actionBar.querySelector('.selection-count').textContent = 
            `${selectedCheckboxes.length} message${selectedCheckboxes.length > 1 ? 's' : ''} sélectionné${selectedCheckboxes.length > 1 ? 's' : ''}`;
    } else {
        actionBar.style.display = 'none';
    }
}

function getOrCreateActionBar() {
    let actionBar = document.getElementById('messageActionBar');
    
    if (!actionBar) {
        actionBar = document.createElement('div');
        actionBar.id = 'messageActionBar';
        actionBar.style.cssText = `
            position: fixed;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 1rem 2rem;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 1000;
            border: 1px solid #e2e8f0;
        `;
        
        actionBar.innerHTML = `
            <span class="selection-count">0 messages sélectionnés</span>
            <button class="btn btn-sm btn-primary" onclick="markSelectedAsRead()">
                <i class="fas fa-check"></i> Marquer comme lu
            </button>
            <button class="btn btn-sm btn-danger" onclick="deleteSelectedMessages()">
                <i class="fas fa-trash"></i> Supprimer
            </button>
            <button class="btn btn-sm btn-secondary" onclick="clearSelection()">
                <i class="fas fa-times"></i> Annuler
            </button>
        `;
        
        document.body.appendChild(actionBar);
    }
    
    return actionBar;
}

function markSelectedAsRead() {
    const selectedCheckboxes = document.querySelectorAll('.message-select:checked');
    const messageIds = Array.from(selectedCheckboxes).map(checkbox => 
        checkbox.closest('.message-item').dataset.messageId
    );
    
    if (messageIds.length > 0) {
        document.getElementById('markReadIds').value = JSON.stringify(messageIds);
        document.getElementById('markReadForm').submit();
    }
}

function deleteSelectedMessages() {
    const selectedCheckboxes = document.querySelectorAll('.message-select:checked');
    const messageIds = Array.from(selectedCheckboxes).map(checkbox => 
        checkbox.closest('.message-item').dataset.messageId
    );
    
    if (messageIds.length > 0) {
        const confirmMessage = `Êtes-vous sûr de vouloir supprimer ${messageIds.length} message${messageIds.length > 1 ? 's' : ''} ?`;
        
        if (confirm(confirmMessage)) {
            document.getElementById('deleteMessageIds').value = JSON.stringify(messageIds);
            document.getElementById('deleteMessagesForm').submit();
        }
    }
}

function clearSelection() {
    document.querySelectorAll('.message-select, #selectAllMessages').forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelectionActions();
}

// Character Counter
function setupCharacterCounter() {
    const messageTextarea = document.getElementById('message_content');
    const charCounter = document.querySelector('.char-counter');
    
    if (messageTextarea && charCounter) {
        messageTextarea.addEventListener('input', updateCharacterCounter);
        updateCharacterCounter(); // Initial count
    }
}

function updateCharacterCounter() {
    const messageTextarea = document.getElementById('message_content');
    const charCounter = document.querySelector('.char-counter');
    
    if (messageTextarea && charCounter) {
        const currentLength = messageTextarea.value.length;
        const maxLength = 1000;
        
        charCounter.textContent = `${currentLength}/${maxLength} caractères`;
        
        if (currentLength > maxLength * 0.9) {
            charCounter.style.color = '#f56565';
        } else if (currentLength > maxLength * 0.7) {
            charCounter.style.color = '#ed8936';
        } else {
            charCounter.style.color = '#718096';
        }
        
        if (currentLength > maxLength) {
            messageTextarea.style.borderColor = '#f56565';
        } else {
            messageTextarea.style.borderColor = '#e2e8f0';
        }
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

    // Form validation
    const composeForm = document.getElementById('composeForm');
    if (composeForm) {
        composeForm.addEventListener('submit', function(e) {
            const receiverSelect = document.getElementById('receiver_select');
            const subjectInput = document.getElementById('subject');
            const messageTextarea = document.getElementById('message_content');
            
            // Validate recipient
            if (!receiverSelect.value) {
                e.preventDefault();
                alert('Veuillez sélectionner un destinataire.');
                receiverSelect.focus();
                return;
            }
            
            // Validate subject
            if (!subjectInput.value.trim()) {
                e.preventDefault();
                alert('Veuillez saisir un sujet.');
                subjectInput.focus();
                return;
            }
            
            // Validate message content
            if (!messageTextarea.value.trim()) {
                e.preventDefault();
                alert('Veuillez saisir un message.');
                messageTextarea.focus();
                return;
            }
            
            if (messageTextarea.value.length > 1000) {
                e.preventDefault();
                alert('Le message est trop long (maximum 1000 caractères).');
                messageTextarea.focus();
                return;
            }

            // Show loading state
            showFormLoadingState();
        });
    }
}

function showFormLoadingState() {
    const sendBtn = document.getElementById('sendBtn');
    if (sendBtn) {
        const originalText = sendBtn.innerHTML;
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
        sendBtn.classList.add('loading');
        
        // Store original text for potential restoration
        sendBtn.dataset.originalText = originalText;
    }
}

// Animations
function setupAnimations() {
    // Animate message items on load
    const messageItems = document.querySelectorAll('.message-item');
    messageItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 50);
    });

    // Animate contact items
    const contactItems = document.querySelectorAll('.contact-item');
    contactItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            item.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, index * 30);
    });

    // Hover effects for message items
    messageItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
            this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
            this.style.boxShadow = '';
        });
    });

    // Hover effects for contact items
    contactItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.querySelector('.contact-avatar').style.transform = 'scale(1.1)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.querySelector('.contact-avatar').style.transform = 'scale(1)';
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

// Mobile Menu
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
                background: #667eea;
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

// Keyboard Shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + N to compose new message
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            openComposeModal();
        }
        
        // Ctrl/Cmd + A to select all messages
        if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !isInInputField(e.target)) {
            e.preventDefault();
            document.getElementById('selectAllMessages').checked = true;
            document.getElementById('selectAllMessages').dispatchEvent(new Event('change'));
        }
        
        // Delete key to delete selected messages
        if (e.key === 'Delete' && !isInInputField(e.target)) {
            const selectedMessages = document.querySelectorAll('.message-select:checked');
            if (selectedMessages.length > 0) {
                e.preventDefault();
                deleteSelectedMessages();
            }
        }
        
        // Escape to close modals and clear selections
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal[style*="display: block"]');
            if (openModals.length > 0) {
                openModals.forEach(modal => {
                    modal.style.display = 'none';
                });
            } else {
                clearSelection();
                clearContactSelection();
            }
        }
        
        // Ctrl/Cmd + Enter to send message in compose modal
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const composeModal = document.getElementById('composeModal');
            if (composeModal && composeModal.style.display === 'block') {
                e.preventDefault();
                document.getElementById('composeForm').submit();
            }
        }
        
        // Arrow keys for navigation
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            if (!isInInputField(e.target)) {
                e.preventDefault();
                navigateMessages(e.key === 'ArrowUp' ? -1 : 1);
            }
        }
    });
}

function isInInputField(element) {
    return element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT';
}

function navigateMessages(direction) {
    const messages = Array.from(document.querySelectorAll('.message-item:not([style*="display: none"])'));
    const currentFocused = document.querySelector('.message-item.keyboard-focus');
    
    let currentIndex = -1;
    if (currentFocused) {
        currentIndex = messages.indexOf(currentFocused);
    }
    
    let newIndex = currentIndex + direction;
    
    if (newIndex < 0) newIndex = 0;
    if (newIndex >= messages.length) newIndex = messages.length - 1;
    
    if (messages[newIndex]) {
        // Remove previous focus
        if (currentFocused) {
            currentFocused.classList.remove('keyboard-focus');
        }
        
        // Add new focus
        messages[newIndex].classList.add('keyboard-focus');
        messages[newIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Style for keyboard focus
        messages[newIndex].style.outline = '2px solid #667eea';
        messages[newIndex].style.outlineOffset = '2px';
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

// Advanced Features
function setupAdvancedFeatures() {
    setupAutoSave();
    setupRealTimeUpdates();
    setupMessageTemplates();
    setupTypingIndicator();
}

function setupAutoSave() {
    // Auto-save draft messages
    const messageTextarea = document.getElementById('message_content');
    const subjectInput = document.getElementById('subject');
    const receiverSelect = document.getElementById('receiver_select');
    
    [messageTextarea, subjectInput, receiverSelect].forEach(input => {
        if (input) {
            input.addEventListener('input', debounce(() => {
                saveDraft();
            }, 1000));
        }
    });
    
    // Load draft on modal open
    const composeModal = document.getElementById('composeModal');
    if (composeModal) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    if (composeModal.style.display === 'block') {
                        loadDraft();
                    }
                }
            });
        });
        
        observer.observe(composeModal, { attributes: true });
    }
}

function saveDraft() {
    const draft = {
        receiver_id: document.getElementById('receiver_select')?.value || '',
        subject: document.getElementById('subject')?.value || '',
        message_content: document.getElementById('message_content')?.value || '',
        timestamp: Date.now()
    };
    
    localStorage.setItem('message_draft', JSON.stringify(draft));
    showDraftSavedIndicator();
}

function loadDraft() {
    const draft = localStorage.getItem('message_draft');
    if (draft) {
        try {
            const draftData = JSON.parse(draft);
            
            // Only load if draft is less than 24 hours old
            if (Date.now() - draftData.timestamp < 24 * 60 * 60 * 1000) {
                if (draftData.receiver_id) document.getElementById('receiver_select').value = draftData.receiver_id;
                if (draftData.subject) document.getElementById('subject').value = draftData.subject;
                if (draftData.message_content) document.getElementById('message_content').value = draftData.message_content;
                
                updateCharacterCounter();
                showDraftLoadedIndicator();
            }
        } catch (e) {
            console.warn('Failed to load draft:', e);
        }
    }
}

function clearDraft() {
    localStorage.removeItem('message_draft');
}

function showDraftSavedIndicator() {
    showTemporaryMessage('Brouillon sauvegardé', 'info', 2000);
}

function showDraftLoadedIndicator() {
    showTemporaryMessage('Brouillon restauré', 'success', 3000);
}

function showTemporaryMessage(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: ${type === 'success' ? '#48bb78' : type === 'warning' ? '#ed8936' : '#667eea'};
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

function setupRealTimeUpdates() {
    // Simulate real-time message updates
    setInterval(() => {
        updateMessageCounts();
        checkForNewMessages();
    }, 30000); // Check every 30 seconds
}

function updateMessageCounts() {
    // This would typically fetch new counts from the server
    // For now, we'll simulate minor updates
    const unreadStat = document.querySelector('.unread-stat .stat-number');
    if (unreadStat && Math.random() > 0.8) {
        const currentCount = parseInt(unreadStat.textContent);
        const newCount = Math.max(0, currentCount + (Math.random() > 0.5 ? 1 : -1));
        animateNumber(unreadStat, newCount);
        
        if (newCount > currentCount) {
            showNewMessageNotification();
        }
    }
}

function checkForNewMessages() {
    // This would check for new messages from the server
    // For demo purposes, we'll simulate occasionally
    if (Math.random() > 0.9) {
        showNewMessageNotification();
    }
}

function showNewMessageNotification() {
    if (Notification.permission === 'granted') {
        new Notification('Nouveau message reçu', {
            body: 'Vous avez reçu un nouveau message.',
            icon: '/path/to/icon.png'
        });
    } else if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showNewMessageNotification();
            }
        });
    }
    
    // Also show in-app notification
    showTemporaryMessage('Nouveau message reçu', 'info', 4000);
}

function setupMessageTemplates() {
    // Add template functionality to compose modal
    const composeForm = document.getElementById('composeForm');
    if (composeForm) {
        const templateSection = document.createElement('div');
        templateSection.className = 'template-section';
        templateSection.innerHTML = `
            <div class="form-group">
                <label for="messageTemplate">Modèles de messages</label>
                <select id="messageTemplate" onchange="applyTemplate(this.value)">
                    <option value="">Sélectionner un modèle</option>
                    <option value="maintenance">Maintenance programmée</option>
                    <option value="payment_reminder">Rappel de paiement</option>
                    <option value="general_info">Information générale</option>
                    <option value="urgent">Message urgent</option>
                </select>
            </div>
        `;
        
        const messageGroup = composeForm.querySelector('textarea').closest('.form-group');
        messageGroup.parentNode.insertBefore(templateSection, messageGroup);
    }
}

function applyTemplate(templateType) {
    const templates = {
        maintenance: {
            subject: 'Maintenance programmée',
            content: 'Cher(e) résident(e),\n\nNous vous informons qu\'une maintenance est programmée le [DATE] de [HEURE] à [HEURE].\n\nMerci de votre compréhension.\n\nCordialement,\nLa gestion',
            priority: 'normal'
        },
        payment_reminder: {
            subject: 'Rappel - Charges de copropriété',
            content: 'Cher(e) résident(e),\n\nNous vous rappelons que le paiement des charges pour le mois de [MOIS] n\'a pas encore été reçu.\n\nMerci de régulariser votre situation dans les plus brefs délais.\n\nCordialement,\nLa gestion',
            priority: 'high'
        },
        general_info: {
            subject: 'Information importante',
            content: 'Cher(e) résident(e),\n\nNous souhaitons vous informer que [INFORMATION].\n\nMerci de votre attention.\n\nCordialement,\nLa gestion',
            priority: 'normal'
        },
        urgent: {
            subject: 'URGENT - ',
            content: 'Cher(e) résident(e),\n\nMessage urgent : [DÉTAILS]\n\nMerci de prendre les mesures nécessaires immédiatement.\n\nCordialement,\nLa gestion',
            priority: 'urgent'
        }
    };
    
    if (templates[templateType]) {
        const template = templates[templateType];
        document.getElementById('subject').value = template.subject;
        document.getElementById('message_content').value = template.content;
        document.getElementById('priority').value = template.priority;
        updateCharacterCounter();
    }
}

function setupTypingIndicator() {
    // This would show when someone is typing (in a real-time system)
    const messageTextarea = document.getElementById('message_content');
    if (messageTextarea) {
        let typingTimer;
        
        messageTextarea.addEventListener('input', () => {
            showTypingIndicator();
            clearTimeout(typingTimer);
            typingTimer = setTimeout(hideTypingIndicator, 3000);
        });
    }
}

function showTypingIndicator() {
    // This would send typing status to server in real implementation
    console.log('User is typing...');
}

function hideTypingIndicator() {
    // This would stop typing status
    console.log('User stopped typing');
}

// Utility Functions
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

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) {
        return 'Hier';
    } else if (diffDays < 7) {
        return `Il y a ${diffDays} jours`;
    } else {
        return date.toLocaleDateString('fr-FR');
    }
}

function exportMessages() {
    const messages = Array.from(document.querySelectorAll('.message-item')).map(item => ({
        contact: item.querySelector('.message-contact strong').textContent,
        type: item.querySelector('.message-type-badge').textContent,
        subject: item.querySelector('.message-subject').textContent,
        preview: item.querySelector('.message-preview').textContent,
        time: item.querySelector('.message-time').textContent
    }));
    
    downloadCSV(messages, 'messages');
}

function downloadCSV(data, filename) {
    const headers = ['Contact', 'Type', 'Sujet', 'Aperçu', 'Date'];
    const csvContent = [
        headers.join(','),
        ...data.map(row => [
            `"${row.contact}"`,
            `"${row.type}"`,
            `"${row.subject}"`,
            `"${row.preview}"`,
            `"${row.time}"`
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

// Initialize advanced features
document.addEventListener('DOMContentLoaded', function() {
    setupAdvancedFeatures();
});

// Form submission cleanup
document.getElementById('composeForm')?.addEventListener('submit', function() {
    clearDraft();
});

// Add CSS for keyboard focus
const style = document.createElement('style');
style.textContent = `
    .message-item.keyboard-focus {
        background: rgba(102, 126, 234, 0.1) !important;
        transform: translateX(5px);
    }
    
    .template-section {
        margin-bottom: 1rem;
        padding: 1rem;
        background: #f7fafc;
        border-radius: 8px;
        border-left: 4px solid #667eea;
    }
    
    .draft-indicator {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: #667eea;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
    }
`;
document.head.appendChild(style);