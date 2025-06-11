function openModal() {
    document.getElementById('syndicModal').classList.add('show');
    document.getElementById('full_name').focus();
}

function closeModal() {
    document.getElementById('syndicModal').classList.remove('show');
}

function updateStatus(memberId, newStatus) {
    const statusText = {
        'active': 'activer',
        'inactive': 'suspendre',
        'pending': 'mettre en attente'
    };
    
    if (confirm(`Êtes-vous sûr de vouloir ${statusText[newStatus]} ce compte syndic ?`)) {
       document.getElementById('statusMemberId').value = memberId;
       document.getElementById('newStatus').value = newStatus;
       document.getElementById('statusForm').submit();
   }
}

function confirmDelete(memberId, syndicName) {
   if (confirm(`Êtes-vous sûr de vouloir supprimer le compte de "${syndicName}" ?\n\nCette action est irréversible et supprimera toutes les données associées.`)) {
       document.getElementById('deleteMemberId').value = memberId;
       document.getElementById('deleteForm').submit();
   }
}

// Auto-submit form when filters change
document.getElementById('search').addEventListener('input', function() {
   clearTimeout(this.searchTimeout);
   this.searchTimeout = setTimeout(() => {
       document.getElementById('filtersForm').submit();
   }, 500);
});

document.getElementById('status').addEventListener('change', function() {
   document.getElementById('filtersForm').submit();
});

document.getElementById('city').addEventListener('change', function() {
   document.getElementById('filtersForm').submit();
});

// Close modal when clicking outside
window.onclick = function(event) {
   const modal = document.getElementById('syndicModal');
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
});

// Form validation
document.querySelector('#syndicModal form').addEventListener('submit', function(event) {
   const email = document.getElementById('email').value;
   const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
   
   if (!emailRegex.test(email)) {
       event.preventDefault();
       alert('Veuillez entrer une adresse email valide.');
       document.getElementById('email').focus();
       return;
   }
});

// Format phone number input
document.getElementById('phone').addEventListener('input', function() {
   let value = this.value.replace(/\D/g, '');
   if (value.length >= 10) {
       value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
   }
   this.value = value;
});

// Auto-capitalize city and company name
document.getElementById('city').addEventListener('input', function() {
   this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
});

document.getElementById('company_name').addEventListener('input', function() {
   this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
});

// Show loading state on form submission
document.querySelector('#syndicModal form').addEventListener('submit', function() {
   const submitBtn = this.querySelector('button[type="submit"]');
   const originalText = submitBtn.innerHTML;
   
   submitBtn.disabled = true;
   submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création en cours...';
   
   // Reset after timeout if form doesn't redirect
   setTimeout(() => {
       submitBtn.disabled = false;
       submitBtn.innerHTML = originalText;
   }, 5000);
});

// Enhanced table interactions
document.querySelectorAll('.data-table tbody tr').forEach(row => {
   row.addEventListener('mouseenter', function() {
       this.style.backgroundColor = 'rgba(244, 185, 66, 0.1)';
   });
   
   row.addEventListener('mouseleave', function() {
       this.style.backgroundColor = '';
   });
});

// Quick search functionality
let searchTimer;
document.getElementById('search').addEventListener('keyup', function(event) {
   clearTimeout(searchTimer);
   
   // Submit on Enter key
   if (event.key === 'Enter') {
       document.getElementById('filtersForm').submit();
       return;
   }
   
   // Auto-submit after 800ms of no typing
   searchTimer = setTimeout(() => {
       if (this.value.length >= 3 || this.value.length === 0) {
           document.getElementById('filtersForm').submit();
       }
   }, 800);
});

// Keyboard shortcuts
document.addEventListener('keydown', function(event) {
   // Ctrl/Cmd + N for new syndic
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

// Progressive form enhancement
function validateForm() {
   const requiredFields = document.querySelectorAll('#syndicModal input[required], #syndicModal select[required]');
   let isValid = true;
   
   requiredFields.forEach(field => {
       if (!field.value.trim()) {
           field.style.borderColor = '#dc3545';
           isValid = false;
       } else {
           field.style.borderColor = 'var(--color-green)';
       }
   });
   
   return isValid;
}

// Real-time form validation
document.querySelectorAll('#syndicModal input, #syndicModal select').forEach(field => {
   field.addEventListener('blur', function() {
       if (this.hasAttribute('required')) {
           if (!this.value.trim()) {
               this.style.borderColor = '#dc3545';
           } else {
               this.style.borderColor = 'var(--color-green)';
           }
       }
   });

   field.addEventListener('input', function() {
       if (this.style.borderColor === 'rgb(220, 53, 69)' && this.value.trim()) {
           this.style.borderColor = 'var(--color-green)';
       }
   });
});

// Enhanced status update with animation
function updateStatusWithAnimation(memberId, newStatus) {
   const row = document.querySelector(`tr[data-member-id="${memberId}"]`);
   if (row) {
       row.style.transition = 'all 0.3s ease';
       row.style.transform = 'scale(1.02)';
       row.style.backgroundColor = 'rgba(244, 185, 66, 0.2)';
       
       setTimeout(() => {
           updateStatus(memberId, newStatus);
       }, 150);
   } else {
       updateStatus(memberId, newStatus);
   }
}

// Add smooth transitions to buttons
document.querySelectorAll('.btn').forEach(btn => {
   btn.addEventListener('mouseenter', function() {
       this.style.transform = 'translateY(-2px)';
   });
   
   btn.addEventListener('mouseleave', function() {
       this.style.transform = 'translateY(0)';
   });
});

// Statistics update
function updateStatistics() {
   const totalRows = document.querySelectorAll('.data-table tbody tr').length;
   const activeCount = document.querySelectorAll('.status-active').length;
   const pendingCount = document.querySelectorAll('.status-pending').length;
   const inactiveCount = document.querySelectorAll('.status-inactive').length;
   
   // Update header with live count
   const header = document.querySelector('.table-header h3');
   const currentText = header.textContent;
   const newText = currentText.replace(/\(\d+\)/, `(${totalRows})`);
   header.innerHTML = newText;
}

// Call statistics update on page load
document.addEventListener('DOMContentLoaded', updateStatistics);