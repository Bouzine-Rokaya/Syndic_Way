/**
 * Dashboard JavaScript - Syndic Way
 * Handles dashboard interactions, real-time updates, and animations
 */

// Global variables
let dashboardData = {};
let updateInterval;
let charts = {};

// Initialize dashboard when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initDashboard();
    setupEventListeners();
    startRealTimeUpdates();
    initializeCharts();
});

// Main initialization function
function initDashboard() {
    // Add fade-in animation to all cards
    animateElements();
    
    // Initialize tooltips
    initTooltips();
    
    // Setup responsive handlers
    setupResponsive();
    
    // Load initial data
    loadDashboardData();
}

// Setup all event listeners
function setupEventListeners() {
    // Search functionality
    const searchBox = document.querySelector('.search-box input');
    if (searchBox) {
        searchBox.addEventListener('input', handleSearch);
    }

    // Message item clicks
    document.querySelectorAll('.message-item').forEach(item => {
        item.addEventListener('click', handleMessageClick);
    });

    // Announcement item clicks
    document.querySelectorAll('.announcement-item').forEach(item => {
        item.addEventListener('click', handleAnnouncementClick);
    });

    // Quick action clicks
    document.querySelectorAll('.quick-action').forEach(action => {
        action.addEventListener('click', handleQuickActionClick);
    });

    // Stat card hover effects
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', handleStatCardHover);
        card.addEventListener('mouseleave', handleStatCardLeave);
    });

    // Chart filter clicks
    document.querySelectorAll('.chart-filter').forEach(filter => {
        filter.addEventListener('click', handleChartFilterClick);
    });

    // Refresh button
    const refreshBtn = document.querySelector('[data-action="refresh"]');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshDashboard);
    }

    // Window resize
    window.addEventListener('resize', handleResize);

    // Bell notification click
    const bellIcon = document.querySelector('.fa-bell');
    if (bellIcon) {
        bellIcon.addEventListener('click', toggleNotifications);
    }
}

// Animate dashboard elements on load
function animateElements() {
    // Animate stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Animate content cards
    const contentCards = document.querySelectorAll('.content-card');
    contentCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 200 + index * 150);
    });
}

// Handle search functionality
function handleSearch(event) {
    const query = event.target.value.toLowerCase();
    
    // Filter messages
    const messages = document.querySelectorAll('.message-item');
    messages.forEach(message => {
        const sender = message.querySelector('.message-sender').textContent.toLowerCase();
        const text = message.querySelector('.message-text').textContent.toLowerCase();
        
        if (sender.includes(query) || text.includes(query)) {
            message.style.display = 'flex';
        } else {
            message.style.display = 'none';
        }
    });

    // Filter announcements
    const announcements = document.querySelectorAll('.announcement-item');
    announcements.forEach(announcement => {
        const title = announcement.querySelector('.announcement-title').textContent.toLowerCase();
        const text = announcement.querySelector('.announcement-text').textContent.toLowerCase();
        
        if (title.includes(query) || text.includes(query)) {
            announcement.style.display = 'flex';
        } else {
            announcement.style.display = 'none';
        }
    });
}

// Handle message item clicks
function handleMessageClick(event) {
    const messageItem = event.currentTarget;
    const messageId = messageItem.dataset.messageId;
    
    // Mark as read
    messageItem.classList.remove('unread');
    
    // Add click animation
    messageItem.style.transform = 'scale(0.98)';
    setTimeout(() => {
        messageItem.style.transform = 'scale(1)';
    }, 150);
    
    // Navigate to message details
    if (messageId) {
        window.location.href = `messages.php?id=${messageId}`;
    }
}

// Handle announcement item clicks
function handleAnnouncementClick(event) {
    const announcementItem = event.currentTarget;
    const announcementId = announcementItem.dataset.announcementId;
    
    // Add click animation
    announcementItem.style.transform = 'scale(0.98)';
    setTimeout(() => {
        announcementItem.style.transform = 'scale(1)';
    }, 150);
    
    // Navigate to announcement details
    if (announcementId) {
        window.location.href = `announcements.php?id=${announcementId}`;
    }
}

// Handle quick action clicks
function handleQuickActionClick(event) {
    event.preventDefault();
    const action = event.currentTarget;
    const actionType = action.dataset.action;
    
    // Add loading state
    action.style.opacity = '0.7';
    action.style.pointerEvents = 'none';
    
    setTimeout(() => {
        action.style.opacity = '1';
        action.style.pointerEvents = 'auto';
        
        // Navigate based on action type
        switch(actionType) {
            case 'add-resident':
                window.location.href = 'residents.php?action=add';
                break;
            case 'new-message':
                window.location.href = 'messages.php?action=compose';
                break;
            case 'create-announcement':
                window.location.href = 'announcements.php?action=create';
                break;
            case 'view-payments':
                window.location.href = 'payments.php';
                break;
            default:
                console.log('Unknown action:', actionType);
        }
    }, 300);
}

// Handle stat card hover effects
function handleStatCardHover(event) {
    const card = event.currentTarget;
    const icon = card.querySelector('.stat-icon');
    
    if (icon) {
        icon.style.transform = 'scale(1.1) rotate(5deg)';
        icon.style.transition = 'all 0.3s ease';
    }
}

function handleStatCardLeave(event) {
    const card = event.currentTarget;
    const icon = card.querySelector('.stat-icon');
    
    if (icon) {
        icon.style.transform = 'scale(1) rotate(0deg)';
    }
}

// Handle chart filter clicks
function handleChartFilterClick(event) {
    const filter = event.currentTarget;
    const filterType = filter.dataset.filter;
    
    // Remove active class from all filters
    document.querySelectorAll('.chart-filter').forEach(f => {
        f.classList.remove('active');
    });
    
    // Add active class to clicked filter
    filter.classList.add('active');
    
    // Update chart based on filter
    updateChart(filterType);
}

// Load dashboard data
function loadDashboardData() {
    showLoading();
    
    // Simulate API call
    setTimeout(() => {
        dashboardData = {
            stats: {
                residents: 42,
                apartments: 36,
                payments: 34,
                unpaid: 2
            },
            messages: getRecentMessages(),
            announcements: getRecentAnnouncements(),
            activity: getRecentActivity()
        };
        
        updateDashboardContent();
        hideLoading();
    }, 1000);
}

// Update dashboard content with new data
function updateDashboardContent() {
    // Update stat numbers with animation
    updateStatNumbers();
    
    // Update message list
    updateMessageList();
    
    // Update announcement list
    updateAnnouncementList();
    
    // Update activity feed
    updateActivityFeed();
}

// Update stat numbers with counting animation
function updateStatNumbers() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach(statElement => {
        const finalValue = parseInt(statElement.textContent);
        animateNumber(statElement, 0, finalValue, 1000);
    });
}

// Animate number counting
function animateNumber(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        
        if (current >= end) {
            current = end;
            clearInterval(timer);
        }
        
        element.textContent = Math.floor(current);
    }, 16);
}

// Update message list
function updateMessageList() {
    const messageList = document.querySelector('.message-list');
    if (!messageList || !dashboardData.messages) return;
    
    // Clear existing messages
    messageList.innerHTML = '';
    
    // Add new messages
    dashboardData.messages.forEach(message => {
        const messageElement = createMessageElement(message);
        messageList.appendChild(messageElement);
    });
}

// Create message element
function createMessageElement(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message-item ${message.unread ? 'unread' : ''}`;
    messageDiv.dataset.messageId = message.id;
    
    messageDiv.innerHTML = `
        <div class="message-avatar">${message.sender.charAt(0).toUpperCase()}</div>
        <div class="message-content">
            <div class="message-sender">${message.sender}</div>
            <div class="message-text">${message.text}</div>
            <div class="message-time">
                ${message.unread ? '<div class="message-status unread"></div>' : ''}
                ${message.time}
            </div>
        </div>
    `;
    
    messageDiv.addEventListener('click', handleMessageClick);
    return messageDiv;
}

// Update announcement list
function updateAnnouncementList() {
    const announcementList = document.querySelector('.announcement-list');
    if (!announcementList || !dashboardData.announcements) return;
    
    // Clear existing announcements
    announcementList.innerHTML = '';
    
    // Add new announcements
    dashboardData.announcements.forEach(announcement => {
        const announcementElement = createAnnouncementElement(announcement);
        announcementList.appendChild(announcementElement);
    });
}

// Create announcement element
function createAnnouncementElement(announcement) {
    const announcementDiv = document.createElement('div');
    announcementDiv.className = `announcement-item ${announcement.urgent ? 'urgent' : ''}`;
    announcementDiv.dataset.announcementId = announcement.id;
    
    announcementDiv.innerHTML = `
        <div class="announcement-date ${announcement.urgent ? 'urgent' : ''}">${announcement.date}</div>
        <div class="announcement-content">
            <div class="announcement-title">
                ${announcement.title}
                ${announcement.urgent ? '<span class="priority-badge">Urgent</span>' : ''}
            </div>
            <div class="announcement-text">${announcement.text}</div>
        </div>
    `;
    
    announcementDiv.addEventListener('click', handleAnnouncementClick);
    return announcementDiv;
}

// Update activity feed
function updateActivityFeed() {
    const activityList = document.querySelector('.activity-list');
    if (!activityList || !dashboardData.activity) return;
    
    // Clear existing activities
    activityList.innerHTML = '';
    
    // Add new activities
    dashboardData.activity.forEach(activity => {
        const activityElement = createActivityElement(activity);
        activityList.appendChild(activityElement);
    });
}

// Create activity element
function createActivityElement(activity) {
    const activityDiv = document.createElement('div');
    activityDiv.className = 'activity-item';
    
    activityDiv.innerHTML = `
        <div class="activity-icon ${activity.type}">
            <i class="${activity.icon}"></i>
        </div>
        <div class="activity-content">
            <div class="activity-text">${activity.text}</div>
            <div class="activity-time">${activity.time}</div>
        </div>
    `;
    
    return activityDiv;
}

// Start real-time updates
function startRealTimeUpdates() {
    updateInterval = setInterval(() => {
        // Check for new messages
        checkNewMessages();
        
        // Update time displays
        updateTimeDisplays();
        
        // Update stats if needed
        updateStatsIfNeeded();
        
    }, 30000); // Update every 30 seconds
}

// Check for new messages
function checkNewMessages() {
    // Simulate checking for new messages
    const hasNewMessages = Math.random() > 0.8;
    
    if (hasNewMessages) {
        showNotificationBadge();
        playNotificationSound();
    }
}

// Update time displays
function updateTimeDisplays() {
    const timeElements = document.querySelectorAll('.message-time, .activity-time');
    
    timeElements.forEach(element => {
        const timeText = element.textContent;
        // Update relative time (e.g., "Il y a 2h" -> "Il y a 3h")
        // This would require parsing and updating the time
    });
}

// Show notification badge
function showNotificationBadge() {
    const bellIcon = document.querySelector('.fa-bell');
    if (bellIcon) {
        const badge = document.createElement('span');
        badge.className = 'notification-badge';
        badge.textContent = '1';
        badge.style.cssText = `
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        bellIcon.parentElement.style.position = 'relative';
        bellIcon.parentElement.appendChild(badge);
    }
}

// Play notification sound
function playNotificationSound() {
    // Create audio element for notification
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhCSZ+zPLZjC4EHXjE89OKOQgXaLnq559NEAxQp+DutGEcBj2O2+7JbSoEKIPQ8dKJNwgZaL3u5Z5NEAxPod7uo2MZCjOCzPLWhC0EsI');
    audio.volume = 0.3;
    audio.play().catch(e => console.log('Cannot play notification sound'));
}

// Toggle notifications dropdown
function toggleNotifications() {
    const existingDropdown = document.querySelector('.notifications-dropdown');
    
    if (existingDropdown) {
        existingDropdown.remove();
        return;
    }
    
    const dropdown = createNotificationsDropdown();
    document.body.appendChild(dropdown);
    
    // Position dropdown
    const bellIcon = document.querySelector('.fa-bell');
    const rect = bellIcon.getBoundingClientRect();
    dropdown.style.top = (rect.bottom + 10) + 'px';
    dropdown.style.right = (window.innerWidth - rect.right) + 'px';
    
    // Close on outside click
    setTimeout(() => {
        document.addEventListener('click', function closeDropdown(e) {
            if (!dropdown.contains(e.target)) {
                dropdown.remove();
                document.removeEventListener('click', closeDropdown);
            }
        });
    }, 100);
}

// Create notifications dropdown
function createNotificationsDropdown() {
    const dropdown = document.createElement('div');
    dropdown.className = 'notifications-dropdown';
    dropdown.style.cssText = `
        position: fixed;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        width: 300px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
    `;
    
    dropdown.innerHTML = `
        <div style="padding: 16px; border-bottom: 1px solid #e2e8f0;">
            <h4 style="margin: 0; font-size: 16px; font-weight: 600;">Notifications</h4>
        </div>
        <div style="padding: 8px 0;">
            <div style="padding: 12px 16px; border-left: 3px solid #3b82f6; background: rgba(59, 130, 246, 0.05);">
                <div style="font-weight: 500; font-size: 14px; margin-bottom: 4px;">Nouveau message</div>
                <div style="font-size: 12px; color: #64748b;">Jean Dupont vous a envoyé un message</div>
                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Il y a 5 min</div>
            </div>
            <div style="padding: 12px 16px;">
                <div style="font-weight: 500; font-size: 14px; margin-bottom: 4px;">Paiement reçu</div>
                <div style="font-size: 12px; color: #64748b;">Marie Leroy a effectué son paiement</div>
                <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">Il y a 1h</div>
            </div>
        </div>
        <div style="padding: 12px 16px; border-top: 1px solid #e2e8f0; text-align: center;">
            <a href="notifications.php" style="color: #3b82f6; text-decoration: none; font-size: 14px;">Voir toutes les notifications</a>
        </div>
    `;
    
    return dropdown;
}

// Initialize charts
function initializeCharts() {
    // Initialize payment trends chart
    initPaymentChart();
    
    // Initialize occupancy chart
    initOccupancyChart();
}

// Initialize payment trends chart
function initPaymentChart() {
    const chartContainer = document.querySelector('#paymentChart');
    if (!chartContainer) return;
    
    // Simulate chart data
    const data = {
        labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun'],
        datasets: [{
            label: 'Paiements reçus',
            data: [85, 90, 88, 92, 87, 94],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4
        }]
    };
    
    // This would normally use Chart.js or similar library
    createSimpleChart(chartContainer, data);
}

// Create simple chart visualization
function createSimpleChart(container, data) {
    container.innerHTML = `
        <div style="display: flex; align-items: end; gap: 8px; height: 200px; padding: 20px;">
            ${data.datasets[0].data.map((value, index) => `
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <div style="
                        height: ${value * 2}px;
                        background: linear-gradient(to top, #3b82f6, #60a5fa);
                        width: 100%;
                        border-radius: 4px 4px 0 0;
                        transition: all 0.3s ease;
                        margin-bottom: 8px;
                    " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'"></div>
                    <div style="font-size: 12px; color: #64748b;">${data.labels[index]}</div>
                </div>
            `).join('')}
        </div>
    `;
}

// Update chart based on filter
function updateChart(filterType) {
    const chartContainer = document.querySelector('#paymentChart');
    if (!chartContainer) return;
    
    // Simulate different data based on filter
    let data;
    switch(filterType) {
        case '7d':
            data = { labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'], datasets: [{ data: [12, 8, 15, 10, 14, 6, 9] }] };
            break;
        case '30d':
            data = { labels: ['S1', 'S2', 'S3', 'S4'], datasets: [{ data: [45, 52, 48, 55] }] };
            break;
        case '90d':
        default:
            data = { labels: ['Jan', 'Fév', 'Mar'], datasets: [{ data: [85, 90, 88] }] };
            break;
    }
    
    createSimpleChart(chartContainer, data);
}

// Refresh dashboard
function refreshDashboard() {
    const refreshBtn = document.querySelector('[data-action="refresh"]');
    if (refreshBtn) {
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualisation...';
        refreshBtn.disabled = true;
    }
    
    setTimeout(() => {
        loadDashboardData();
        
        if (refreshBtn) {
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Actualiser';
            refreshBtn.disabled = false;
        }
        
        showToast('Dashboard actualisé avec succès!', 'success');
    }, 1500);
}

// Show loading state
function showLoading() {
    const loadingElements = document.querySelectorAll('.card-body');
    loadingElements.forEach(element => {
        if (!element.querySelector('.loading')) {
            const loading = document.createElement('div');
            loading.className = 'loading';
            loading.innerHTML = '<div class="loading-spinner"></div>Chargement...';
            element.appendChild(loading);
        }
    });
}

// Hide loading state
function hideLoading() {
    const loadingElements = document.querySelectorAll('.loading');
    loadingElements.forEach(element => element.remove());
}

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => toast.style.transform = 'translateX(0)', 100);
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Initialize tooltips
function initTooltips() {
    const elementsWithTooltip = document.querySelectorAll('[data-tooltip]');
    
    elementsWithTooltip.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

// Show tooltip
function showTooltip(event) {
    const element = event.currentTarget;
    const text = element.dataset.tooltip;
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        white-space: nowrap;
        z-index: 9999;
        pointer-events: none;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
    tooltip.style.left = (rect.left + rect.width / 2 - tooltip.offsetWidth / 2) + 'px';
    
    element._tooltip = tooltip;
}

// Hide tooltip
function hideTooltip(event) {
    const element = event.currentTarget;
    if (element._tooltip) {
        element._tooltip.remove();
        delete element._tooltip;
    }
}

// Setup responsive behavior
function setupResponsive() {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
        });
    }
}

// Handle window resize
function handleResize() {
    // Recalculate chart dimensions
    const charts = document.querySelectorAll('.chart-wrapper');
    charts.forEach(chart => {
        // Redraw chart with new dimensions
        if (chart.chart) {
            chart.chart.resize();
        }
    });
    
    // Close dropdowns on resize
    const dropdowns = document.querySelectorAll('.notifications-dropdown');
    dropdowns.forEach(dropdown => dropdown.remove());
}

// Get sample data functions
function getRecentMessages() {
    return [
        {
            id: 1,
            sender: 'Jean Dupont',
            text: 'Problème de chauffage dans l\'appartement 12...',
            time: 'Il y a 2h',
            unread: true
        },
        {
            id: 2,
            sender: 'Marie Leroy',
            text: 'Question sur les charges communes...',
            time: 'Il y a 4h',
            unread: false
        },
        {
            id: 3,
            sender: 'Pierre Martin',
            text: 'Demande de justificatif de domicile...',
            time: 'Hier',
            unread: false
        }
    ];
}

function getRecentAnnouncements() {
    return [
        {
            id: 1,
            title: 'Maintenance ascenseur',
            text: 'L\'ascenseur sera en maintenance le 20 janvier...',
            date: '15 Jan',
            urgent: false
        },
        {
            id: 2,
            title: 'Assemblée générale',
            text: 'Prochaine AG prévue le 1er février...',
            date: '12 Jan',
            urgent: true
        },
        {
            id: 3,
            title: 'Travaux parking',
            text: 'Rénovation du parking souterrain...',
            date: '10 Jan',
            urgent: false
        }
    ];
}

function getRecentActivity() {
    return [
        {
            type: 'payment',
            icon: 'fas fa-credit-card',
            text: 'Marie Leroy a effectué son paiement mensuel',
            time: 'Il y a 1h'
        },
        {
            type: 'message',
            icon: 'fas fa-envelope',
            text: 'Nouveau message de Jean Dupont',
            time: 'Il y a 2h'
        },
        {
            type: 'user',
            icon: 'fas fa-user-plus',
            text: 'Nouveau résident: Sophie Bernard',
            time: 'Il y a 3h'
        }
    ];
}

// Update stats if needed
function updateStatsIfNeeded() {
    // Check if stats need updating based on time
    const lastUpdate = localStorage.getItem('lastStatsUpdate');
    const now = Date.now();
    
    if (!lastUpdate || now - parseInt(lastUpdate) > 300000) { // 5 minutes
        // Fetch new stats
        fetchLatestStats();
        localStorage.setItem('lastStatsUpdate', now.toString());
    }
}

// Fetch latest statistics
function fetchLatestStats() {
    // Simulate API call to get latest stats
    setTimeout(() => {
        const newStats = {
            residents: 42 + Math.floor(Math.random() * 3) - 1,
            apartments: 36,
            payments: 34 + Math.floor(Math.random() * 2),
            unpaid: Math.max(0, 2 + Math.floor(Math.random() * 3) - 1)
        };
        
        updateStatCards(newStats);
    }, 500);
}

// Update stat cards with new values
function updateStatCards(newStats) {
    Object.keys(newStats).forEach(key => {
        const statCard = document.querySelector(`.stat-icon.${key}`)?.closest('.stat-card');
        if (statCard) {
            const numberElement = statCard.querySelector('.stat-number');
            const currentValue = parseInt(numberElement.textContent);
            const newValue = newStats[key];
            
            if (currentValue !== newValue) {
                animateNumber(numberElement, currentValue, newValue, 800);
                
                // Add highlight effect
                statCard.style.background = 'rgba(59, 130, 246, 0.05)';
                setTimeout(() => {
                    statCard.style.background = '';
                }, 1000);
            }
        }
    });
}

// Cleanup function
function cleanup() {
    // Clear intervals
    if (updateInterval) {
        clearInterval(updateInterval);
    }
    
    // Remove event listeners
    window.removeEventListener('resize', handleResize);
    
    // Clear any pending timeouts
    const timeouts = window.timeouts || [];
    timeouts.forEach(timeout => clearTimeout(timeout));
}

// Keyboard shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(event) {
        // Ctrl/Cmd + K for search
        if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
            event.preventDefault();
            const searchInput = document.querySelector('.search-box input');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Ctrl/Cmd + R for refresh
        if ((event.ctrlKey || event.metaKey) && event.key === 'r') {
            event.preventDefault();
            refreshDashboard();
        }
        
        // Escape to close dropdowns
        if (event.key === 'Escape') {
            const dropdowns = document.querySelectorAll('.notifications-dropdown');
            dropdowns.forEach(dropdown => dropdown.remove());
        }
        
        // Number keys for quick navigation
        if (event.altKey && event.key >= '1' && event.key <= '8') {
            event.preventDefault();
            const navItems = document.querySelectorAll('.nav-item');
            const index = parseInt(event.key) - 1;
            if (navItems[index]) {
                navItems[index].click();
            }
        }
    });
}

// Add keyboard shortcuts on init
document.addEventListener('DOMContentLoaded', function() {
    setupKeyboardShortcuts();
});

// Export functions for use in other modules
window.dashboardUtils = {
    showToast,
    animateNumber,
    loadDashboardData,
    refreshDashboard,
    updateStatCards,
    createMessageElement,
    createAnnouncementElement,
    handleSearch
};

// Auto-save functionality for forms
function setupAutoSave() {
    const forms = document.querySelectorAll('form[data-autosave]');
    
    forms.forEach(form => {
        const formId = form.dataset.autosave;
        const inputs = form.querySelectorAll('input, textarea, select');
        
        // Load saved data
        loadFormData(form, formId);
        
        // Save on input
        inputs.forEach(input => {
            input.addEventListener('input', debounce(() => {
                saveFormData(form, formId);
            }, 500));
        });
    });
}

// Save form data to localStorage
function saveFormData(form, formId) {
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    localStorage.setItem(`form_${formId}`, JSON.stringify(data));
    
    // Show save indicator
    showSaveIndicator(form);
}

// Load form data from localStorage
function loadFormData(form, formId) {
    const savedData = localStorage.getItem(`form_${formId}`);
    
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            
            Object.keys(data).forEach(key => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = data[key];
                }
            });
        } catch (e) {
            console.warn('Failed to load saved form data:', e);
        }
    }
}

// Show save indicator
function showSaveIndicator(form) {
    let indicator = form.querySelector('.save-indicator');
    
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.className = 'save-indicator';
        indicator.style.cssText = `
            position: absolute;
            top: 10px;
            right: 10px;
            background: #10b981;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;
        indicator.textContent = 'Sauvegardé';
        form.style.position = 'relative';
        form.appendChild(indicator);
    }
    
    indicator.style.opacity = '1';
    setTimeout(() => {
        indicator.style.opacity = '0';
    }, 2000);
}

// Debounce function
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

// Theme switching functionality
function initThemeSwitch() {
    const themeToggle = document.querySelector('.theme-toggle');
    if (!themeToggle) return;
    
    // Get saved theme or default to light
    const savedTheme = localStorage.getItem('theme') || 'light';
    applyTheme(savedTheme);
    
    themeToggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.dataset.theme || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        applyTheme(newTheme);
        localStorage.setItem('theme', newTheme);
    });
}

// Apply theme
function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    
    // Update theme toggle icon
    const themeToggle = document.querySelector('.theme-toggle i');
    if (themeToggle) {
        themeToggle.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
    }
}

// Print functionality
function initPrintFunction() {
    const printBtn = document.querySelector('[data-action="print"]');
    if (!printBtn) return;
    
    printBtn.addEventListener('click', () => {
        // Prepare for printing
        document.body.classList.add('printing');
        
        // Print
        window.print();
        
        // Clean up after printing
        setTimeout(() => {
            document.body.classList.remove('printing');
        }, 1000);
    });
}

// Export data functionality
function initExportFunction() {
    const exportBtn = document.querySelector('[data-action="export"]');
    if (!exportBtn) return;
    
    exportBtn.addEventListener('click', () => {
        const exportMenu = createExportMenu();
        document.body.appendChild(exportMenu);
        
        // Position menu
        const rect = exportBtn.getBoundingClientRect();
        exportMenu.style.top = (rect.bottom + 5) + 'px';
        exportMenu.style.left = rect.left + 'px';
        
        // Close on outside click
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!exportMenu.contains(e.target)) {
                    exportMenu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 100);
    });
}

// Create export menu
function createExportMenu() {
    const menu = document.createElement('div');
    menu.className = 'export-menu';
    menu.style.cssText = `
        position: fixed;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        min-width: 150px;
    `;
    
    const options = [
        { label: 'Export PDF', action: 'pdf', icon: 'fas fa-file-pdf' },
        { label: 'Export Excel', action: 'excel', icon: 'fas fa-file-excel' },
        { label: 'Export CSV', action: 'csv', icon: 'fas fa-file-csv' }
    ];
    
    menu.innerHTML = options.map(option => `
        <div class="export-option" data-action="${option.action}" style="
            padding: 12px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        ">
            <i class="${option.icon}" style="width: 16px; color: #64748b;"></i>
            ${option.label}
        </div>
    `).join('');
    
    // Add hover effects
    menu.querySelectorAll('.export-option').forEach(option => {
        option.addEventListener('mouseenter', () => {
            option.style.background = '#f8fafc';
        });
        
        option.addEventListener('mouseleave', () => {
            option.style.background = '';
        });
        
        option.addEventListener('click', () => {
            const action = option.dataset.action;
            exportData(action);
            menu.remove();
        });
    });
    
    return menu;
}

// Export data in different formats
function exportData(format) {
    showToast(`Export ${format.toUpperCase()} en cours...`, 'info');
    
    // Simulate export process
    setTimeout(() => {
        switch(format) {
            case 'pdf':
                generatePDF();
                break;
            case 'excel':
                generateExcel();
                break;
            case 'csv':
                generateCSV();
                break;
        }
        
        showToast(`Export ${format.toUpperCase()} terminé!`, 'success');
    }, 2000);
}

// Generate PDF export
function generatePDF() {
    // This would typically use a library like jsPDF
    console.log('Generating PDF export...');
    
    // Create download link
    const link = document.createElement('a');
    link.download = `dashboard-${new Date().toISOString().split('T')[0]}.pdf`;
    link.href = '#'; // Would be actual PDF blob URL
    link.click();
}

// Generate Excel export
function generateExcel() {
    // This would typically use a library like SheetJS
    console.log('Generating Excel export...');
    
    // Create download link
    const link = document.createElement('a');
    link.download = `dashboard-${new Date().toISOString().split('T')[0]}.xlsx`;
    link.href = '#'; // Would be actual Excel blob URL
    link.click();
}

// Generate CSV export
function generateCSV() {
    // Extract data from dashboard
    const csvData = [
        ['Résidents', '42'],
        ['Appartements', '36'],
        ['Paiements à jour', '34'],
        ['Impayés', '2']
    ];
    
    const csvContent = csvData.map(row => row.join(',')).join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    
    const link = document.createElement('a');
    link.download = `dashboard-${new Date().toISOString().split('T')[0]}.csv`;
    link.href = url;
    link.click();
    
    // Clean up
    URL.revokeObjectURL(url);
}

// Fullscreen functionality
function initFullscreenToggle() {
    const fullscreenBtn = document.querySelector('[data-action="fullscreen"]');
    if (!fullscreenBtn) return;
    
    fullscreenBtn.addEventListener('click', toggleFullscreen);
    
    // Listen for fullscreen changes
    document.addEventListener('fullscreenchange', updateFullscreenButton);
}

// Toggle fullscreen
function toggleFullscreen() {
    if (document.fullscreenElement) {
        document.exitFullscreen();
    } else {
        document.documentElement.requestFullscreen();
    }
}

// Update fullscreen button
function updateFullscreenButton() {
    const fullscreenBtn = document.querySelector('[data-action="fullscreen"] i');
    if (fullscreenBtn) {
        fullscreenBtn.className = document.fullscreenElement ? 'fas fa-compress' : 'fas fa-expand';
    }
}

// Advanced search functionality
function initAdvancedSearch() {
    const searchInput = document.querySelector('.search-box input');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        
        searchTimeout = setTimeout(() => {
            const query = e.target.value.trim();
            
            if (query.length > 2) {
                performAdvancedSearch(query);
            } else {
                clearSearchResults();
            }
        }, 300);
    });
    
    // Add search suggestions
    createSearchSuggestions(searchInput);
}

// Perform advanced search
function performAdvancedSearch(query) {
    // Show loading in search
    const searchBox = document.querySelector('.search-box');
    searchBox.classList.add('searching');
    
    // Simulate search API call
    setTimeout(() => {
        const results = searchAllContent(query);
        displaySearchResults(results);
        searchBox.classList.remove('searching');
    }, 500);
}

// Search all dashboard content
function searchAllContent(query) {
    const results = [];
    
    // Search messages
    document.querySelectorAll('.message-item').forEach(item => {
        const sender = item.querySelector('.message-sender').textContent;
        const text = item.querySelector('.message-text').textContent;
        
        if (sender.toLowerCase().includes(query.toLowerCase()) || 
            text.toLowerCase().includes(query.toLowerCase())) {
            results.push({
                type: 'message',
                title: `Message de ${sender}`,
                content: text,
                element: item
            });
        }
    });
    
    // Search announcements
    document.querySelectorAll('.announcement-item').forEach(item => {
        const title = item.querySelector('.announcement-title').textContent;
        const text = item.querySelector('.announcement-text').textContent;
        
        if (title.toLowerCase().includes(query.toLowerCase()) || 
            text.toLowerCase().includes(query.toLowerCase())) {
            results.push({
                type: 'announcement',
                title: title,
                content: text,
                element: item
            });
        }
    });
    
    return results;
}

// Display search results
function displaySearchResults(results) {
    let dropdown = document.querySelector('.search-results');
    
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.className = 'search-results';
        dropdown.style.cssText = `
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            margin-top: 5px;
        `;
        
        document.querySelector('.search-box').appendChild(dropdown);
    }
    
    if (results.length === 0) {
        dropdown.innerHTML = '<div style="padding: 16px; text-align: center; color: #64748b;">Aucun résultat trouvé</div>';
        return;
    }
    
    dropdown.innerHTML = results.map(result => `
        <div class="search-result" style="
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
        ">
            <div style="font-weight: 500; font-size: 14px; margin-bottom: 4px;">${result.title}</div>
            <div style="font-size: 12px; color: #64748b; line-height: 1.3;">${result.content.substring(0, 80)}...</div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 4px;">${result.type === 'message' ? 'Message' : 'Annonce'}</div>
        </div>
    `).join('');
    
    // Add click handlers
    dropdown.querySelectorAll('.search-result').forEach((resultEl, index) => {
        resultEl.addEventListener('click', () => {
            const result = results[index];
            result.element.scrollIntoView({ behavior: 'smooth' });
            result.element.style.background = 'rgba(59, 130, 246, 0.1)';
            setTimeout(() => {
                result.element.style.background = '';
            }, 2000);
            
            clearSearchResults();
        });
        
        resultEl.addEventListener('mouseenter', () => {
            resultEl.style.background = '#f8fafc';
        });
        
        resultEl.addEventListener('mouseleave', () => {
            resultEl.style.background = '';
        });
    });
}

// Clear search results
function clearSearchResults() {
    const dropdown = document.querySelector('.search-results');
    if (dropdown) {
        dropdown.remove();
    }
}

// Create search suggestions
function createSearchSuggestions(searchInput) {
    const suggestions = [
        'Jean Dupont',
        'Marie Leroy',
        'Pierre Martin',
        'paiement',
        'chauffage',
        'maintenance',
        'assemblée générale'
    ];
    
    searchInput.addEventListener('focus', () => {
        if (!searchInput.value) {
            showSearchSuggestions(suggestions, searchInput);
        }
    });
}

// Show search suggestions
function showSearchSuggestions(suggestions, searchInput) {
    const dropdown = document.createElement('div');
    dropdown.className = 'search-suggestions';
    dropdown.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        margin-top: 5px;
    `;
    
    dropdown.innerHTML = `
        <div style="padding: 8px 16px; font-size: 12px; color: #64748b; border-bottom: 1px solid #f1f5f9;">Suggestions de recherche</div>
        ${suggestions.map(suggestion => `
            <div class="suggestion-item" style="
                padding: 8px 16px;
                cursor: pointer;
                font-size: 14px;
            ">${suggestion}</div>
        `).join('')}
    `;
    
    // Add click handlers
    dropdown.querySelectorAll('.suggestion-item').forEach(item => {
        item.addEventListener('click', () => {
            searchInput.value = item.textContent;
            searchInput.dispatchEvent(new Event('input'));
            dropdown.remove();
        });
        
        item.addEventListener('mouseenter', () => {
            item.style.background = '#f8fafc';
        });
        
        item.addEventListener('mouseleave', () => {
            item.style.background = '';
        });
    });
    
    document.querySelector('.search-box').appendChild(dropdown);
    
    // Remove on outside click
    setTimeout(() => {
        document.addEventListener('click', function removeSuggestions(e) {
            if (!dropdown.contains(e.target) && e.target !== searchInput) {
                dropdown.remove();
                document.removeEventListener('click', removeSuggestions);
            }
        });
    }, 100);
}

// Initialize all features
document.addEventListener('DOMContentLoaded', function() {
    setupAutoSave();
    initThemeSwitch();
    initPrintFunction();
    initExportFunction();
    initFullscreenToggle();
    initAdvancedSearch();
});

// Cleanup on page unload
window.addEventListener('beforeunload', cleanup);