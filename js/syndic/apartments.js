// Apartments.js - JavaScript for apartment management

document.addEventListener('DOMContentLoaded', function() {
    initializeApartmentsPage();
});

function initializeApartmentsPage() {
    setupViewToggle();
    setupFilters();
    setupModals();
    setupAnimations();
    setupMobileMenu();
    animateStatistics();
    autoHideAlerts();
}

// View Toggle Functionality
function setupViewToggle() {
    const viewButtons = document.querySelectorAll('.view-btn');
    const gridView = document.getElementById('gridView');
    const tableView = document.getElementById('tableView');

    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const view = this.dataset.view;
            
            // Update active button
            viewButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Toggle views
            if (view === 'grid') {
                gridView.style.display = 'grid';
                tableView.style.display = 'none';
                localStorage.setItem('apartmentView', 'grid');
            } else {
                gridView.style.display = 'none';
                tableView.style.display = 'block';
                localStorage.setItem('apartmentView', 'table');
            }
        });
    });

    // Restore saved view preference
    const savedView = localStorage.getItem('apartmentView') || 'grid';
    document.querySelector(`[data-view="${savedView}"]`).click();
}

// Filter Functions
function setupFilters() {
    const searchInput = document.getElementById('search');
    const filterSelects = document.querySelectorAll('#floor, #type, #status');
    
    // Real-time search
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
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            document.getElementById('filtersForm').submit();
        });
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

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + N to create new apartment
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            openCreateModal();
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal[style*="display: block"]');
            openModals.forEach(modal => {
                modal.style.display = 'none';
            });
        }
    });

    // Form validation
    const apartmentForm = document.getElementById('apartmentForm');
    if (apartmentForm) {
        apartmentForm.addEventListener('submit', function(e) {
            const apartmentNumber = document.getElementById('apartment_number').value;
            const apartmentFloor = document.getElementById('apartment_floor').value;
            
            if (!apartmentNumber || apartmentNumber < 1) {
                e.preventDefault();
                alert('Veuillez entrer un numéro d\'appartement valide.');
                return;
            }
            
            if (!apartmentFloor.trim()) {
                e.preventDefault();
                alert('Veuillez entrer l\'étage de l\'appartement.');
                return;
            }

            // Show loading state
            showLoadingState();
        });
    }

    // Assign form validation
    const assignForm = document.getElementById('assignForm');
    if (assignForm) {
        assignForm.addEventListener('submit', function(e) {
            const residentId = document.getElementById('assign_resident_id').value;
            
            if (!residentId) {
                e.preventDefault();
                alert('Veuillez sélectionner un résident.');
                return;
            }

            showLoadingState();
        });
    }
}

// Apartment Actions
function openCreateModal() {
    document.getElementById('formAction').value = 'create_apartment';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> Nouvel appartement';
    document.getElementById('submitText').textContent = 'Créer l\'appartement';
    
    // Reset form
    document.getElementById('apartmentForm').reset();
    document.getElementById('apartmentId').value = '';
    
    document.getElementById('apartmentModal').style.display = 'block';
}

function editApartment(apartment) {
    document.getElementById('formAction').value = 'update_apartment';
    document.getElementById('apartmentId').value = apartment.id_apartment;
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Modifier l\'appartement';
    document.getElementById('submitText').textContent = 'Mettre à jour';
    
    // Fill form with apartment data
    document.getElementById('apartment_number').value = apartment.apartment_number;
    document.getElementById('apartment_floor').value = apartment.floor;
    document.getElementById('apartment_type').value = apartment.apartment_type;
    
    // Set resident if occupied
    if (apartment.role == 1) {
        document.getElementById('resident_id').value = apartment.id_member;
    } else {
        document.getElementById('resident_id').value = '';
    }
    
    document.getElementById('apartmentModal').style.display = 'block';
}

function assignResident(apartmentId) {
    document.getElementById('assignApartmentId').value = apartmentId;
    document.getElementById('assign_resident_id').value = '';
    document.getElementById('assignModal').style.display = 'block';
}

function deleteApartment(apartmentId, apartmentNumber) {
    const confirmMessage = `Êtes-vous sûr de vouloir supprimer l'appartement ${apartmentNumber} ?\n\nCette action est irréversible.`;
    
    if (confirm(confirmMessage)) {
        document.getElementById('deleteApartmentId').value = apartmentId;
        document.getElementById('deleteForm').submit();
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Utility Functions
function showLoadingState() {
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
        
        // Re-enable button after 10 seconds as fallback
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }, 10000);
    }
}

function setupAnimations() {
    // Animate apartment cards on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe apartment cards
    const apartmentCards = document.querySelectorAll('.apartment-card');
    apartmentCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(card);
    });

    // Enhanced card interactions
    apartmentCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.02)';
            this.style.boxShadow = '0 15px 35px rgba(0,0,0,0.2)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.08)';
        });
    });

    // Table row animations
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f7fafc';
            this.style.transform = 'scale(1.01)';
            this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
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

// Input Validation and Formatting
function setupInputValidation() {
    // Apartment number validation
    const apartmentInput = document.getElementById('apartment_number');
    if (apartmentInput) {
        apartmentInput.addEventListener('input', function(e) {
            if (e.target.value < 1) {
                e.target.value = 1;
            }
            if (e.target.value > 9999) {
                e.target.value = 9999;
            }
        });
    }

    // Floor input validation
    const floorInput = document.getElementById('apartment_floor');
    if (floorInput) {
        floorInput.addEventListener('input', function(e) {
            // Allow letters and numbers for floors like "RDC", "1er", "2ème", etc.
            const value = e.target.value;
            if (value && !/^[a-zA-Z0-9èéêë\s-]+$/.test(value)) {
                e.target.value = value.slice(0, -1);
            }
        });
    }
}

// Advanced Features
function setupAdvancedFeatures() {
    // Bulk actions
    setupBulkActions();
    
    // Export functionality
    setupExportFeatures();
    
    // Search highlighting
    setupSearchHighlighting();
    
    // Keyboard navigation
    setupKeyboardNavigation();
}

function setupBulkActions() {
    // Add checkboxes for bulk selection
    const apartmentCards = document.querySelectorAll('.apartment-card');
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    
    // This could be extended to add bulk operations
    // like bulk assign, bulk delete, etc.
}

function setupExportFeatures() {
    // Add export button functionality
    const exportBtn = document.createElement('button');
    exportBtn.className = 'btn btn-secondary';
    exportBtn.innerHTML = '<i class="fas fa-download"></i> Exporter';
    exportBtn.addEventListener('click', exportApartmentData);
    
    const headerActions = document.querySelector('.header-actions');
    if (headerActions) {
        headerActions.appendChild(exportBtn);
    }
}

function exportApartmentData() {
    // Simple CSV export functionality
    const apartments = Array.from(document.querySelectorAll('.apartment-card')).map(card => {
        const number = card.querySelector('.apartment-number').textContent.replace('Apt. ', '');
        const details = Array.from(card.querySelectorAll('.detail-item span')).map(span => span.textContent);
        const status = card.classList.contains('occupied') ? 'Occupé' : 'Vacant';
        
        return {
            number,
            floor: details[0]?.replace('Étage ', '') || '',
            type: details[1] || '',
            resident: details[2] || '',
            email: details[3] || '',
            status
        };
    });
    
    const csvContent = "data:text/csv;charset=utf-8," 
        + "Numéro,Étage,Type,Résident,Email,Statut\n"
        + apartments.map(apt => Object.values(apt).join(',')).join('\n');
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', 'appartements.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function setupSearchHighlighting() {
    const searchInput = document.getElementById('search');
    if (!searchInput) return;
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const apartmentCards = document.querySelectorAll('.apartment-card');
        
        apartmentCards.forEach(card => {
            const textContent = card.textContent.toLowerCase();
            if (searchTerm && textContent.includes(searchTerm)) {
                card.style.border = '2px solid #667eea';
                card.style.boxShadow = '0 0 20px rgba(102, 126, 234, 0.3)';
            } else {
                card.style.border = '';
                card.style.boxShadow = '';
            }
        });
    });
}

function setupKeyboardNavigation() {
    document.addEventListener('keydown', function(e) {
        // Arrow key navigation for apartment cards
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || 
            e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            
            const focusedCard = document.activeElement.closest('.apartment-card');
            if (focusedCard) {
                e.preventDefault();
                navigateCards(focusedCard, e.key);
            }
        }
        
        // Enter to edit focused apartment
        if (e.key === 'Enter') {
            const focusedCard = document.activeElement.closest('.apartment-card');
            if (focusedCard) {
                const editBtn = focusedCard.querySelector('.btn-secondary');
                if (editBtn) editBtn.click();
            }
        }
    });
    
    // Make apartment cards focusable
    const apartmentCards = document.querySelectorAll('.apartment-card');
    apartmentCards.forEach((card, index) => {
        card.setAttribute('tabindex', index === 0 ? '0' : '-1');
        card.addEventListener('focus', function() {
            this.style.outline = '2px solid #667eea';
            this.style.outlineOffset = '2px';
        });
        card.addEventListener('blur', function() {
            this.style.outline = '';
            this.style.outlineOffset = '';
        });
    });
}

function navigateCards(currentCard, direction) {
    const cards = Array.from(document.querySelectorAll('.apartment-card'));
    const currentIndex = cards.indexOf(currentCard);
    let newIndex;
    
    switch(direction) {
        case 'ArrowLeft':
            newIndex = currentIndex > 0 ? currentIndex - 1 : cards.length - 1;
            break;
        case 'ArrowRight':
            newIndex = currentIndex < cards.length - 1 ? currentIndex + 1 : 0;
            break;
        case 'ArrowUp':
            newIndex = currentIndex - 3 >= 0 ? currentIndex - 3 : currentIndex;
            break;
        case 'ArrowDown':
            newIndex = currentIndex + 3 < cards.length ? currentIndex + 3 : currentIndex;
            break;
    }
    
    if (newIndex !== undefined && cards[newIndex]) {
        currentCard.setAttribute('tabindex', '-1');
        cards[newIndex].setAttribute('tabindex', '0');
        cards[newIndex].focus();
    }
}

// Ripple Effect for Buttons
function addRippleEffect() {
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.5);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
}

// Performance Monitoring
function setupPerformanceMonitoring() {
    // Monitor load times
    window.addEventListener('load', function() {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        console.log(`Page loaded in ${loadTime}ms`);
        
        // Log slow loading apartments
        if (loadTime > 3000) {
            console.warn('Slow page load detected. Consider optimizing apartment data loading.');
        }
    });
    
    // Monitor memory usage (if available)
    if ('memory' in performance) {
        setInterval(() => {
            const memory = performance.memory;
            if (memory.usedJSHeapSize > 50 * 1024 * 1024) { // 50MB
                console.warn('High memory usage detected');
            }
        }, 30000);
    }
}

// Initialize all features
document.addEventListener('DOMContentLoaded', function() {
    initializeApartmentsPage();
    setupInputValidation();
    setupAdvancedFeatures();
    addRippleEffect();
    setupPerformanceMonitoring();
});

// Add CSS for ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(2);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);