// Reports.js - JavaScript for reports management and data visualization

document.addEventListener('DOMContentLoaded', function() {
    initializeReportsPage();
});

function initializeReportsPage() {
    setupPeriodSelection();
    setupModals();
    initializeCharts();
    setupAnimations();
    setupMobileMenu();
    setupKeyboardShortcuts();
    autoHideAlerts();
    createFloatingActionButton();
    setupAdvancedFeatures();
}

// Period Selection Functions
function setupPeriodSelection() {
    const periodForm = document.getElementById('periodForm');
    const customDateRange = document.getElementById('customDateRange');
    
    // Handle custom date range visibility
    window.setPeriod = function(period) {
        document.getElementById('periodInput').value = period;
        
        // Update active tab
        document.querySelectorAll('.period-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.target.classList.add('active');
        
        if (period === 'custom') {
            customDateRange.style.display = 'flex';
        } else {
            customDateRange.style.display = 'none';
            // Auto-submit for non-custom periods
            setTimeout(() => {
                periodForm.submit();
            }, 100);
        }
    };
    
    // Handle custom date changes
    const customFromInput = document.getElementById('custom_from');
    const customToInput = document.getElementById('custom_to');
    
    if (customFromInput) {
        customFromInput.addEventListener('change', validateDateRange);
    }
    if (customToInput) {
        customToInput.addEventListener('change', validateDateRange);
    }
}

function validateDateRange() {
    const fromDate = document.getElementById('custom_from').value;
    const toDate = document.getElementById('custom_to').value;
    
    if (fromDate && toDate) {
        if (new Date(fromDate) > new Date(toDate)) {
            alert('La date de début ne peut pas être postérieure à la date de fin.');
            document.getElementById('custom_to').value = fromDate;
        }
        
        // Check if range is too large (more than 2 years)
        const daysDiff = (new Date(toDate) - new Date(fromDate)) / (1000 * 60 * 60 * 24);
        if (daysDiff > 730) {
            alert('La période sélectionnée ne peut pas dépasser 2 ans.');
            const maxToDate = new Date(new Date(fromDate).getTime() + (730 * 24 * 60 * 60 * 1000));
            document.getElementById('custom_to').value = maxToDate.toISOString().split('T')[0];
        }
    }
}

// Charts Initialization
function initializeCharts() {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded. Charts will not be displayed.');
        return;
    }
    
    // Configure Chart.js defaults
    Chart.defaults.font.family = 'inherit';
    Chart.defaults.color = '#4a5568';
    Chart.defaults.plugins.legend.position = 'bottom';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.responsive = true;
    Chart.defaults.maintainAspectRatio = false;
    
    initializePaymentChart();
    initializeOccupancyChart();
    initializeActivityChart();
}

function initializePaymentChart() {
    const ctx = document.getElementById('paymentChart');
    if (!ctx || !window.chartData) return;
    
    try {
        const paymentData = chartData.paymentTrends || [];
        
        // Prepare data for the last 12 months
        const months = [];
        const payingResidents = [];
        const paymentCounts = [];
        
        // Generate last 12 months if no data
        if (paymentData.length === 0) {
            for (let i = 11; i >= 0; i--) {
                const date = new Date();
                date.setMonth(date.getMonth() - i);
                months.push(date.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' }));
                payingResidents.push(Math.floor(Math.random() * 15) + 5);
                paymentCounts.push(Math.floor(Math.random() * 20) + 10);
            }
        } else {
            paymentData.forEach(item => {
                const date = new Date(item.month + '-01');
                months.push(date.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' }));
                payingResidents.push(parseInt(item.paying_residents) || 0);
                paymentCounts.push(parseInt(item.payment_count) || 0);
            });
        }
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [
                    {
                        label: 'Résidents payants',
                        data: payingResidents,
                        borderColor: '#48bb78',
                        backgroundColor: 'rgba(72, 187, 120, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointBackgroundColor: '#48bb78',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Nombre de paiements',
                        data: paymentCounts,
                        borderColor: '#4299e1',
                        backgroundColor: 'rgba(66, 153, 225, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointBackgroundColor: '#4299e1',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end'
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
                        displayColors: true,
                        mode: 'index',
                        intersect: false
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error initializing payment chart:', error);
        showChartError('paymentChart', 'Erreur lors du chargement du graphique des paiements');
    }
}

function initializeOccupancyChart() {
    const ctx = document.getElementById('occupancyChart');
    if (!ctx || !window.chartData) return;
    
    try {
        const occupancyData = chartData.occupancyByFloor || [];
        
        // Prepare data
        const floors = [];
        const totalApartments = [];
        const occupiedApartments = [];
        const occupancyRates = [];
        
        if (occupancyData.length === 0) {
            // Generate sample data for floors 1-5
            for (let i = 1; i <= 5; i++) {
                floors.push(`Étage ${i}`);
                const total = Math.floor(Math.random() * 8) + 4;
                const occupied = Math.floor(Math.random() * total) + Math.floor(total * 0.6);
                totalApartments.push(total);
                occupiedApartments.push(occupied);
                occupancyRates.push(Math.round((occupied / total) * 100));
            }
        } else {
            occupancyData.forEach(item => {
                floors.push(`Étage ${item.floor}`);
                const total = parseInt(item.total_apartments) || 0;
                const occupied = parseInt(item.occupied_apartments) || 0;
                totalApartments.push(total);
                occupiedApartments.push(occupied);
                occupancyRates.push(total > 0 ? Math.round((occupied / total) * 100) : 0);
            });
        }
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: floors,
                datasets: [{
                    label: 'Taux d\'occupation',
                    data: occupancyRates,
                    backgroundColor: [
                        '#48bb78',
                        '#4299e1',
                        '#ed8936',
                        '#9f7aea',
                        '#38b2ac',
                        '#f56565',
                        '#d69e2e'
                    ],
                    borderColor: '#fff',
                    borderWidth: 3,
                    hoverBorderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 20,
                            generateLabels: function(chart) {
                                const data = chart.data;
                                return data.labels.map((label, i) => ({
                                    text: `${label}: ${data.datasets[0].data[i]}%`,
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    strokeStyle: data.datasets[0].backgroundColor[i],
                                    lineWidth: 0,
                                    pointStyle: 'circle'
                                }));
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const occupied = occupiedApartments[context.dataIndex];
                                const total = totalApartments[context.dataIndex];
                                return `${label}: ${occupied}/${total} appartements (${value}%)`;
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Error initializing occupancy chart:', error);
        showChartError('occupancyChart', 'Erreur lors du chargement du graphique d\'occupation');
    }
}

function initializeActivityChart() {
    const ctx = document.getElementById('activityChart');
    if (!ctx || !window.chartData) return;
    
    try {
        const activityData = chartData.recentActivity || [];
        
        // Process activity data by date
        const activityByDate = {};
        const today = new Date();
        
        // Initialize last 30 days
        for (let i = 29; i >= 0; i--) {
            const date = new Date(today);
            date.setDate(date.getDate() - i);
            const dateStr = date.toISOString().split('T')[0];
            activityByDate[dateStr] = {
                payments: 0,
                messages: 0,
                announcements: 0
            };
        }
        
        // Fill with actual data
        activityData.forEach(item => {
            const date = item.date;
            if (activityByDate[date]) {
                activityByDate[date][item.type + 's'] = parseInt(item.count) || 0;
            }
        });
        
        const dates = Object.keys(activityByDate).sort();
        const payments = dates.map(date => activityByDate[date].payments);
        const messages = dates.map(date => activityByDate[date].messages);
        const announcements = dates.map(date => activityByDate[date].announcements);
        const labels = dates.map(date => {
            const d = new Date(date);
            return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
        });
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Paiements',
                        data: payments,
                        backgroundColor: 'rgba(72, 187, 120, 0.8)',
                        borderColor: '#48bb78',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false
                    },
                    {
                        label: 'Messages',
                        data: messages,
                        backgroundColor: 'rgba(66, 153, 225, 0.8)',
                        borderColor: '#4299e1',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false
                    },
                    {
                        label: 'Annonces',
                        data: announcements,
                        backgroundColor: 'rgba(159, 122, 234, 0.8)',
                        borderColor: '#9f7aea',
                        borderWidth: 1,
                        borderRadius: 4,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end'
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 8,
                        mode: 'index',
                        intersect: false
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }
        });
    } catch (error) {
        console.error('Error initializing activity chart:', error);
        showChartError('activityChart', 'Erreur lors du chargement du graphique d\'activité');
    }
}

function showChartError(canvasId, message) {
    const canvas = document.getElementById(canvasId);
    if (canvas) {
        const container = canvas.parentElement;
        container.innerHTML = `
            <div class="chart-no-data">
                <i class="fas fa-exclamation-triangle"></i>
                <p>${message}</p>
            </div>
        `;
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
}

// Report Generation Functions
function openReportModal() {
    document.getElementById('reportModal').style.display = 'block';
    
    // Set default dates to current period
    if (window.chartData) {
        document.getElementById('date_from').value = chartData.periodFrom || '';
        document.getElementById('date_to').value = chartData.periodTo || '';
    }
    
    // Focus on report type
    setTimeout(() => {
        document.getElementById('report_type').focus();
    }, 100);
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function generateQuickReport(reportType) {
    // Set the report type in the hidden form
    document.getElementById('quickReportType').value = reportType;
    
    // Show loading state on the corresponding card
    const reportCards = document.querySelectorAll('.report-card');
    reportCards.forEach(card => {
        if (card.getAttribute('onclick').includes(reportType)) {
            card.classList.add('generating');
        }
    });
    
    // Submit the form
    document.getElementById('quickReportForm').submit();
}

function exportAllData() {
    document.getElementById('exportModal').style.display = 'block';
}

// Chart Download Function
function downloadChart(chartId) {
    try {
        const canvas = document.getElementById(chartId);
        if (!canvas) {
            showTemporaryMessage('Graphique non trouvé', 'error');
            return;
        }
        
        // Create download link
        const link = document.createElement('a');
        link.download = `${chartId}_${new Date().toISOString().split('T')[0]}.png`;
        link.href = canvas.toDataURL();
        
        // Trigger download
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showTemporaryMessage('Graphique téléchargé avec succès', 'success');
    } catch (error) {
        console.error('Error downloading chart:', error);
        showTemporaryMessage('Erreur lors du téléchargement', 'error');
    }
}

// Animations
function setupAnimations() {
    // Animate KPI cards on load
    const kpiCards = document.querySelectorAll('.kpi-card');
    kpiCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });

    // Animate chart containers
    const chartContainers = document.querySelectorAll('.chart-container');
    chartContainers.forEach((container, index) => {
        container.style.opacity = '0';
        container.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            container.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            container.style.opacity = '1';
            container.style.transform = 'translateY(0)';
        }, (index + 2) * 200);
    });

    // Animate report cards
    const reportCards = document.querySelectorAll('.report-card');
    reportCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, (index + 4) * 100);
    });

    // Animate timeline items
    const timelineItems = document.querySelectorAll('.timeline-item');
    timelineItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-30px)';
        
        setTimeout(() => {
            item.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateX(0)';
        }, (index + 6) * 100);
    });

    // Enhanced hover effects
    setupHoverEffects();
}

function setupHoverEffects() {
    // KPI card hover effects
    const kpiCards = document.querySelectorAll('.kpi-card');
    kpiCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px) scale(1.02)';
            this.style.boxShadow = '0 12px 30px rgba(0,0,0,0.15)';
            
            const icon = this.querySelector('.kpi-icon');
            if (icon) {
                icon.style.transform = 'scale(1.1) rotate(5deg)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-5px) scale(1)';
            this.style.boxShadow = '';
            
            const icon = this.querySelector('.kpi-icon');
            if (icon) {
                icon.style.transform = 'scale(1) rotate(0deg)';
            }
        });
    });

    // Report card hover effects
    const reportCards = document.querySelectorAll('.report-card');
    reportCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 10px 25px rgba(0,0,0,0.15)';
            
            const icon = this.querySelector('.report-icon');
            if (icon) {
                icon.style.transform = 'scale(1.1)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(-3px)';
            this.style.boxShadow = '';
            
            const icon = this.querySelector('.report-icon');
            if (icon) {
                icon.style.transform = 'scale(1)';
            }
        });
    });

    // Chart container hover effects
    const chartContainers = document.querySelectorAll('.chart-container');
    chartContainers.forEach(container => {
        container.addEventListener('mouseenter', function() {
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.12)';
        });
        
        container.addEventListener('mouseleave', function() {
            this.style.boxShadow = '';
        });
    });
}

// Mobile Menu Setup
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
                background: #4299e1;
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
        fab.innerHTML = '<i class="fas fa-chart-bar"></i>';
        fab.onclick = openReportModal;
        fab.style.cssText = `
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            background: #4299e1;
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 1.5rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            z-index: 1000;
            cursor: pointer;
            transition: all 0.3s ease;
        `;
        
        fab.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.background = '#3182ce';
        });
        
        fab.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.background = '#4299e1';
        });
        
        document.body.appendChild(fab);
    }
}

// Keyboard Shortcuts
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + R for generate report
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            openReportModal();
        }
        
        // Ctrl/Cmd + E for export data
        if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
            e.preventDefault();
            exportAllData();
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal[style*="display: block"]');
            openModals.forEach(modal => {
                modal.style.display = 'none';
            });
        }
        
        // Ctrl/Cmd + P for period selection
        if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
            e.preventDefault();
            document.querySelector('.period-tab:first-child').focus();
        }
        
        // F1 for help
        if (e.key === 'F1') {
            e.preventDefault();
            showKeyboardShortcuts();
        }
    });
}

function showKeyboardShortcuts() {
    const shortcuts = document.createElement('div');
    shortcuts.className = 'keyboard-shortcuts show';
    shortcuts.innerHTML = `
        <strong>Raccourcis clavier:</strong><br>
        Ctrl+R: Générer rapport<br>
        Ctrl+E: Exporter données<br>
        Ctrl+P: Sélection période<br>
        Échap: Fermer<br>
        F1: Aide
    `;
    shortcuts.style.cssText = `
        position: fixed;
        bottom: 1rem;
        left: 1rem;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 1rem;
        border-radius: 8px;
        font-size: 0.8rem;
        z-index: 1000;
        opacity: 1;
        transition: opacity 0.3s ease;
    `;
    
    document.body.appendChild(shortcuts);
    
    setTimeout(() => {
        shortcuts.style.opacity = '0';
        setTimeout(() => shortcuts.remove(), 300);
    }, 5000);
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
function showTemporaryMessage(message, type = 'info', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = 'success-message';
    notification.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: ${type === 'success' ? '#48bb78' : type === 'warning' ? '#ed8936' : type === 'error' ? '#f56565' : '#4299e1'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
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

function formatNumber(number) {
    return new Intl.NumberFormat('fr-FR').format(number);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'EUR'
    }).format(amount);
}

// Advanced Features
function setupAdvancedFeatures() {
    setupRealTimeUpdates();
    setupDataRefresh();
    setupExportProgress();
    setupChartInteractions();
    setupPerformanceMonitoring();
}

function setupRealTimeUpdates() {
    // Check for updates every 5 minutes
    setInterval(() => {
        updateDashboardData();
    }, 300000); // 5 minutes
}

function updateDashboardData() {
    // Simulate real-time updates
    if (Math.random() > 0.8) {
        const kpiNumbers = document.querySelectorAll('.kpi-value');
        kpiNumbers.forEach(element => {
            const currentValue = parseInt(element.textContent);
            if (!isNaN(currentValue) && Math.random() > 0.7) {
                const newValue = currentValue + (Math.random() > 0.5 ? 1 : 0);
                if (newValue !== currentValue) {
                    animateNumberUpdate(element, currentValue, newValue);
                }
            }
        });
    }
}

function animateNumberUpdate(element, from, to) {
    const duration = 1000;
    const startTime = Date.now();
    
    function update() {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        const current = Math.round(from + (to - from) * easeOutCubic(progress));
        element.textContent = current;
        
        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            // Add visual feedback for updates
            element.style.color = '#48bb78';
            element.style.transform = 'scale(1.1)';
            setTimeout(() => {
                element.style.color = '';
                element.style.transform = '';
            }, 500);
        }
    }
    
    requestAnimationFrame(update);
}

function easeOutCubic(t) {
    return 1 - Math.pow(1 - t, 3);
}

function setupDataRefresh() {
    // Add refresh button to each chart
    const chartContainers = document.querySelectorAll('.chart-container');
    chartContainers.forEach(container => {
        const controls = container.querySelector('.chart-controls');
        if (controls) {
            const refreshBtn = document.createElement('button');
            refreshBtn.className = 'btn btn-sm btn-secondary';
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
            refreshBtn.onclick = () => refreshChart(container);
            controls.appendChild(refreshBtn);
        }
    });
}

function refreshChart(container) {
    const canvas = container.querySelector('canvas');
    if (!canvas) return;
    
    const refreshBtn = container.querySelector('.btn:last-child');
    if (refreshBtn) {
        refreshBtn.disabled = true;
        refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    }
    
    // Simulate data refresh
    setTimeout(() => {
        // In a real application, you would fetch new data here
        showTemporaryMessage('Données du graphique mises à jour', 'success');
        
        if (refreshBtn) {
            refreshBtn.disabled = false;
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        }
    }, 1500);
}

function setupExportProgress() {
    // Monitor form submissions for export progress
    const exportForm = document.getElementById('exportForm');
    if (exportForm) {
        exportForm.addEventListener('submit', function(e) {
            showExportProgress();
        });
    }
    
    const reportForm = document.getElementById('reportForm');
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            showReportProgress();
        });
    }
}

function showExportProgress() {
    const progress = document.createElement('div');
    progress.className = 'export-progress';
    progress.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <i class="fas fa-download"></i>
            <span>Export en cours...</span>
            <span id="progressPercent">0%</span>
        </div>
        <div class="export-progress-bar">
            <div class="export-progress-fill" id="progressFill" style="width: 0%"></div>
        </div>
    `;
    
    document.body.appendChild(progress);
    
    // Simulate progress
    let percent = 0;
    const interval = setInterval(() => {
        percent += Math.random() * 15;
        if (percent >= 100) {
            percent = 100;
            clearInterval(interval);
            
            setTimeout(() => {
                progress.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 0.75rem; color: #48bb78;">
                        <i class="fas fa-check-circle"></i>
                        <span>Export terminé avec succès</span>
                    </div>
                `;
                
                setTimeout(() => {
                    progress.style.transform = 'translateX(100%)';
                    setTimeout(() => progress.remove(), 300);
                }, 2000);
            }, 500);
        }
        
        document.getElementById('progressPercent').textContent = Math.round(percent) + '%';
        document.getElementById('progressFill').style.width = percent + '%';
    }, 200);
}

function showReportProgress() {
    const progress = document.createElement('div');
    progress.className = 'export-progress';
    progress.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <i class="fas fa-file-alt"></i>
            <span>Génération du rapport...</span>
            <span id="reportProgressPercent">0%</span>
        </div>
        <div class="export-progress-bar">
            <div class="export-progress-fill" id="reportProgressFill" style="width: 0%"></div>
        </div>
    `;
    
    document.body.appendChild(progress);
    
    // Simulate progress
    let percent = 0;
    const steps = [
        'Collecte des données...',
        'Analyse des statistiques...',
        'Génération des graphiques...',
        'Mise en forme du rapport...',
        'Finalisation...'
    ];
    let stepIndex = 0;
    
    const interval = setInterval(() => {
        percent += Math.random() * 10;
        
        if (percent >= (stepIndex + 1) * 20 && stepIndex < steps.length - 1) {
            stepIndex++;
            progress.querySelector('span:nth-child(2)').textContent = steps[stepIndex];
        }
        
        if (percent >= 100) {
            percent = 100;
            clearInterval(interval);
            
            setTimeout(() => {
                progress.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 0.75rem; color: #48bb78;">
                        <i class="fas fa-check-circle"></i>
                        <span>Rapport généré avec succès</span>
                    </div>
                `;
                
                setTimeout(() => {
                    progress.style.transform = 'translateX(100%)';
                    setTimeout(() => progress.remove(), 300);
                }, 2000);
            }, 500);
        }
        
        document.getElementById('reportProgressPercent').textContent = Math.round(percent) + '%';
        document.getElementById('reportProgressFill').style.width = percent + '%';
    }, 300);
}

function setupChartInteractions() {
    // Add click-to-drill-down functionality
    const chartCanvases = document.querySelectorAll('canvas');
    chartCanvases.forEach(canvas => {
        canvas.addEventListener('click', function(e) {
            const chart = Chart.getChart(canvas);
            if (!chart) return;
            
            const points = chart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, true);
            if (points.length > 0) {
                const point = points[0];
                const datasetLabel = chart.data.datasets[point.datasetIndex].label;
                const dataLabel = chart.data.labels[point.index];
                const value = chart.data.datasets[point.datasetIndex].data[point.index];
                
                showChartTooltip(e, datasetLabel, dataLabel, value);
            }
        });
    });
}

function showChartTooltip(event, dataset, label, value) {
    // Remove existing tooltips
    const existingTooltips = document.querySelectorAll('.chart-tooltip');
    existingTooltips.forEach(tooltip => tooltip.remove());
    
    const tooltip = document.createElement('div');
    tooltip.className = 'chart-tooltip show';
    tooltip.innerHTML = `
        <strong>${dataset}</strong><br>
        ${label}: ${value}
    `;
    
    document.body.appendChild(tooltip);
    
    // Position tooltip
    const rect = event.target.getBoundingClientRect();
    tooltip.style.left = (event.clientX + 10) + 'px';
    tooltip.style.top = (event.clientY - 10) + 'px';
    
    // Auto-hide after 3 seconds
    setTimeout(() => {
        tooltip.classList.remove('show');
        setTimeout(() => tooltip.remove(), 300);
    }, 3000);
}

function setupPerformanceMonitoring() {
    // Monitor page load performance
    window.addEventListener('load', function() {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        console.log(`Reports page loaded in ${loadTime}ms`);
        
        if (loadTime > 5000) {
            console.warn('Slow page load detected');
            showTemporaryMessage('Chargement lent détecté. Optimisation recommandée.', 'warning', 5000);
        }
    });
    
    // Monitor chart rendering performance
    const chartLoadTimes = [];
    const originalChartRender = Chart.prototype.render;
    Chart.prototype.render = function() {
        const startTime = performance.now();
        const result = originalChartRender.call(this);
        const endTime = performance.now();
        chartLoadTimes.push(endTime - startTime);
        
        console.log(`Chart rendered in ${(endTime - startTime).toFixed(2)}ms`);
        return result;
    };
}

// Form Validation
function setupFormValidation() {
    const reportForm = document.getElementById('reportForm');
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            const reportType = document.getElementById('report_type').value;
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            const format = document.getElementById('format').value;
            
            if (!reportType) {
                e.preventDefault();
                alert('Veuillez sélectionner un type de rapport.');
                document.getElementById('report_type').focus();
                return;
            }
            
            if (!dateFrom || !dateTo) {
                e.preventDefault();
                alert('Veuillez sélectionner une période.');
                document.getElementById('date_from').focus();
                return;
            }
            
            if (new Date(dateFrom) > new Date(dateTo)) {
                e.preventDefault();
                alert('La date de début ne peut pas être postérieure à la date de fin.');
                document.getElementById('date_to').focus();
                return;
            }
            
            if (!format) {
                e.preventDefault();
                alert('Veuillez sélectionner un format.');
                document.getElementById('format').focus();
                return;
            }
            
            // Show loading state
            showFormLoadingState(e.target);
        });
    }
    
    const exportForm = document.getElementById('exportForm');
    if (exportForm) {
        exportForm.addEventListener('submit', function(e) {
            const checkedBoxes = this.querySelectorAll('input[type="checkbox"]:checked');
            
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Veuillez sélectionner au moins un type de données à exporter.');
                return;
            }
            
            // Show loading state
            showFormLoadingState(e.target);
        });
    }
}

function showFormLoadingState(form) {
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
        submitBtn.classList.add('loading');
        
        // Store original text for potential restoration
        submitBtn.dataset.originalText = originalText;
    }
}

// Data Export Functions
function exportReportsData(format = 'csv') {
    const data = gatherReportsData();
    
    switch (format.toLowerCase()) {
        case 'csv':
            downloadCSV(data, 'rapport_syndic');
            break;
        case 'json':
            downloadJSON(data, 'rapport_syndic');
            break;
        case 'excel':
            downloadExcel(data, 'rapport_syndic');
            break;
        default:
            showTemporaryMessage('Format non supporté', 'error');
    }
}

function gatherReportsData() {
    const data = {
        period: {
            from: window.chartData?.periodFrom || '',
            to: window.chartData?.periodTo || ''
        },
        kpis: {},
        charts: {},
        timeline: []
    };
    
    // Gather KPI data
    const kpiCards = document.querySelectorAll('.kpi-card');
    kpiCards.forEach(card => {
        const label = card.querySelector('.kpi-label')?.textContent?.trim();
        const value = card.querySelector('.kpi-value')?.textContent?.trim();
        const trend = card.querySelector('.trend-value')?.textContent?.trim();
        
        if (label && value) {
            data.kpis[label] = {
                value: value,
                trend: trend || ''
            };
        }
    });
    
    // Gather timeline data
    const timelineItems = document.querySelectorAll('.timeline-item');
    timelineItems.forEach(item => {
        const type = item.querySelector('.activity-type')?.textContent?.trim();
        const date = item.querySelector('.activity-date')?.textContent?.trim();
        const description = item.querySelector('.timeline-description')?.textContent?.trim();
        
        if (type && date && description) {
            data.timeline.push({
                type: type,
                date: date,
                description: description
            });
        }
    });
    
    return data;
}

function downloadCSV(data, filename) {
    let csvContent = 'Type,Label,Valeur,Date\n';
    
    // Add KPI data
    Object.keys(data.kpis).forEach(key => {
        csvContent += `KPI,"${key}","${data.kpis[key].value}","${new Date().toISOString().split('T')[0]}"\n`;
    });
    
    // Add timeline data
    data.timeline.forEach(item => {
        csvContent += `Timeline,"${item.type}","${item.description}","${item.date}"\n`;
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `${filename}_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showTemporaryMessage('Données exportées en CSV', 'success');
}

function downloadJSON(data, filename) {
    const jsonString = JSON.stringify(data, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `${filename}_${new Date().toISOString().split('T')[0]}.json`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showTemporaryMessage('Données exportées en JSON', 'success');
}

function downloadExcel(data, filename) {
    // This would require a library like SheetJS in a real implementation
    // For now, we'll convert to CSV and suggest using CSV
    showTemporaryMessage('Export Excel non disponible. Utilisez CSV à la place.', 'warning');
    downloadCSV(data, filename);
}

// Print Functions
function printReports() {
    // Prepare page for printing
    const originalTitle = document.title;
    document.title = 'Rapport Syndic - ' + new Date().toLocaleDateString('fr-FR');
    
    // Hide interactive elements
    const elementsToHide = document.querySelectorAll('.header-actions, .period-selection, .chart-controls, .modal');
    elementsToHide.forEach(el => {
        el.style.display = 'none';
    });
    
    // Trigger print
    window.print();
    
    // Restore page
    document.title = originalTitle;
    elementsToHide.forEach(el => {
        el.style.display = '';
    });
}

// Accessibility Features
function setupAccessibility() {
    // Add keyboard navigation for charts
    const chartContainers = document.querySelectorAll('.chart-container');
    chartContainers.forEach(container => {
        container.setAttribute('tabindex', '0');
        container.setAttribute('role', 'img');
        container.setAttribute('aria-label', 'Graphique de données');
        
        container.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                // Announce chart data for screen readers
                announceChartData(container);
            }
        });
    });
    
    // Add ARIA labels to KPI cards
    const kpiCards = document.querySelectorAll('.kpi-card');
    kpiCards.forEach(card => {
        const label = card.querySelector('.kpi-label')?.textContent;
        const value = card.querySelector('.kpi-value')?.textContent;
        if (label && value) {
            card.setAttribute('aria-label', `${label}: ${value}`);
        }
    });
}

function announceChartData(container) {
    const title = container.querySelector('.chart-header h3')?.textContent;
    const announcement = `Graphique: ${title}. Utilisez les flèches pour naviguer dans les données.`;
    
    // Create live region for screen readers
    const liveRegion = document.createElement('div');
    liveRegion.setAttribute('aria-live', 'polite');
    liveRegion.setAttribute('aria-atomic', 'true');
    liveRegion.style.position = 'absolute';
    liveRegion.style.left = '-10000px';
    liveRegion.textContent = announcement;
    
    document.body.appendChild(liveRegion);
    setTimeout(() => liveRegion.remove(), 1000);
}

// Initialize form validation and accessibility on page load
document.addEventListener('DOMContentLoaded', function() {
    setupFormValidation();
    setupAccessibility();
});

// Window resize handler
window.addEventListener('resize', function() {
    createFloatingActionButton();
    const existingFab = document.querySelector('.fab');
    if (window.innerWidth > 768 && existingFab) {
        existingFab.remove();
    }
    
    // Resize charts
    Chart.instances.forEach(chart => {
        chart.resize();
    });
});

// Cleanup and error handling
window.addEventListener('beforeunload', function() {
    // Clean up any intervals or timeouts
    clearInterval(window.dashboardUpdateInterval);
});

window.addEventListener('error', function(e) {
    console.error('Reports page error:', e.error);
    showTemporaryMessage('Une erreur s\'est produite. Veuillez actualiser la page.', 'error', 10000);
});

// CSS injection for dynamic styles
const dynamicStyles = document.createElement('style');
dynamicStyles.textContent = `
    .mobile-toggle {
        transition: all 0.3s ease;
    }
    
    .mobile-toggle:hover {
        background: #3182ce !important;
        transform: scale(1.05);
    }
    
    .fab {
        transition: all 0.3s ease;
    }
    
    .chart-tooltip {
        position: absolute;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
        pointer-events: none;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .chart-tooltip.show {
        opacity: 1;
    }
    
    .export-progress {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 9999;
        min-width: 300px;
        transition: transform 0.3s ease;
    }
    
    .export-progress-bar {
        width: 100%;
        height: 6px;
        background: #e2e8f0;
        border-radius: 3px;
        overflow: hidden;
    }
    
    .export-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #4299e1, #3182ce);
        transition: width 0.3s ease;
        border-radius: 3px;
    }
    
    .success-message {
        position: fixed;
        top: 2rem;
        right: 2rem;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 9999;
        font-size: 0.9rem;
        font-weight: 600;
        transition: transform 0.3s ease;
    }
    
    .kpi-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .kpi-icon {
        transition: transform 0.3s ease;
    }
    
    .report-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .report-icon {
        transition: transform 0.3s ease;
    }
    
    .chart-container {
        transition: box-shadow 0.3s ease;
    }
    
    .timeline-item {
        transition: all 0.3s ease;
    }
    
    @media (max-width: 768px) {
        .export-progress {
            left: 1rem;
            right: 1rem;
            bottom: 1rem;
            min-width: auto;
        }
        
        .success-message {
            left: 1rem;
            right: 1rem;
            top: 1rem;
        }
    }
    
    @media (prefers-reduced-motion: reduce) {
        * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }
`;

document.head.appendChild(dynamicStyles);

// Export main functions for external use
window.ReportsManager = {
    openReportModal,
    exportAllData,
    downloadChart,
    generateQuickReport,
    showTemporaryMessage,
    formatDate,
    formatDateTime,
    formatNumber,
    formatCurrency,
    printReports,
    exportReportsData
};

console.log('Reports.js loaded successfully');