
// DASHBOARD INITIALIZATION

document.addEventListener('DOMContentLoaded', function() {
    // Animate statistics counters (numbers counting up)
    animateStatsCounters();
    
    // Initialize any charts if Chart is loaded
    initDashboardCharts();
    
    // Start auto-refresh for real-time data (every 30 seconds)
    startAutoRefresh();
    
    console.log('Dashboard JS loaded');
});


// STATS COUNTER ANIMATION

/* Animate all stat numbers to count up from 0 to target value*/
function animateStatsCounters() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach(stat => {
        const targetText = stat.innerText;
        const target = parseInt(targetText);
        
        // Skip if not a number
        if (isNaN(target)) return;
        
        let current = 0;
        const increment = Math.ceil(target / 50); // Complete in ~50 steps
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                stat.innerText = target;
                clearInterval(timer);
            } else {
                stat.innerText = Math.floor(current);
            }
        }, 20);
    });
}


// CHARTS 


 //Initialize charts on dashboard
 //This checks if Chart is loaded and if chart elements exist
 
function initDashboardCharts() {
    // Check if Chart is available
    if (typeof Chart === 'undefined') {
        console.log('Chart.js not loaded, skipping charts');
        return;
    }
    
    // Department Performance Chart
    const deptChartCanvas = document.getElementById('deptPerformanceChart');
    if (deptChartCanvas && deptChartCanvas.dataset.labels) {
        try {
            new Chart(deptChartCanvas, {
                type: 'bar',
                data: {
                    labels: JSON.parse(deptChartCanvas.dataset.labels),
                    datasets: [{
                        label: 'Complaints by Department',
                        data: JSON.parse(deptChartCanvas.dataset.data),
                        backgroundColor: 'rgba(102, 126, 234, 0.6)',
                        borderColor: '#667eea',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Complaints'
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Error creating department chart:', e);
        }
    }
    
    // Resolution Trend Chart
    const trendChartCanvas = document.getElementById('resolutionTrendChart');
    if (trendChartCanvas && trendChartCanvas.dataset.labels) {
        try {
            new Chart(trendChartCanvas, {
                type: 'line',
                data: {
                    labels: JSON.parse(trendChartCanvas.dataset.labels),
                    datasets: [
                        {
                            label: 'Total Complaints',
                            data: JSON.parse(trendChartCanvas.dataset.total),
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Resolved',
                            data: JSON.parse(trendChartCanvas.dataset.resolved),
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Complaints'
                            }
                        }
                    }
                }
            });
        } catch (e) {
            console.error('Error creating trend chart:', e);
        }
    }
}


// AUTO-REFRESH FUNCTIONALITY

let refreshInterval = null;


 //Start auto-refresh for real-time data
 // Updates stats every 30 seconds without page reload

function startAutoRefresh() {
    // Clear existing interval if any
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    
    // Set new interval (30 seconds)
    refreshInterval = setInterval(() => {
        refreshDashboardData();
    }, 30000);
}

/*Stop auto-refresh (call when leaving page)*/
function stopAutoRefresh() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

/* Fetch fresh dashboard data via AJAX and update page*/
async function refreshDashboardData() {
    try {
        const response = await fetch('dashboard_data.php');
        const data = await response.json();
        
        if (data.success && data.stats) {
            // Update stat numbers
            updateStatNumber('totalUsers', data.stats.total_users);
            updateStatNumber('totalComplaints', data.stats.total_complaints);
            updateStatNumber('urgentComplaints', data.stats.urgent_complaints);
            updateStatNumber('totalDepartments', data.stats.total_departments);
            updateStatNumber('activeComplaints', data.stats.active_complaints);
            updateStatNumber('resolvedWeek', data.stats.resolved_week);
            
            // Update detail text
            const statDetail = document.querySelector('.stat-card.warning .stat-detail');
            if (statDetail && data.stats.active_complaints && data.stats.resolved_week) {
                statDetail.innerHTML = `${data.stats.active_complaints} active | ${data.stats.resolved_week} resolved this week`;
            }
            
            // Show subtle notification (optional)
            // showNotification('Dashboard updated', 'info');
        }
    } catch (error) {
        console.error('Error refreshing dashboard:', error);
        // Don't show error notification to avoid annoyance
    }
}

/*Update a single stat number with animation*/
function updateStatNumber(elementId, newValue) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const currentValue = parseInt(element.innerText);
    const targetValue = parseInt(newValue);
    
    // If same, no need to animate
    if (currentValue === targetValue) return;
    
    // Animate the change
    let current = currentValue;
    const difference = targetValue - currentValue;
    const steps = 20;
    const increment = difference / steps;
    let step = 0;
    
    const timer = setInterval(() => {
        step++;
        current += increment;
        if (step >= steps) {
            element.innerText = targetValue;
            clearInterval(timer);
        } else {
            element.innerText = Math.round(current);
        }
    }, 30);
}


// NOTIFICATION BADGE UPDATE


/* Check for new notifications and update badge*/
async function checkNotifications() {
    try {
        const response = await fetch('check_notifications.php');
        const data = await response.json();
        
        if (data.unread_count > 0) {
            updateNotificationBadge(data.unread_count);
        }
    } catch (error) {
        console.error('Error checking notifications:', error);
    }
}

/* Update the notification badge in header*/
function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
}


// CHART REFRESH 

/* Refresh chart data without reloading page*/
async function refreshChartData() {
    if (typeof Chart === 'undefined') return;
    
    try {
        const response = await fetch('dashboard_chart_data.php');
        const data = await response.json();
        
        // Update existing charts if they exist
        const trendChart = Chart.getChart('resolutionTrendChart');
        if (trendChart && data.trends) {
            trendChart.data.datasets[0].data = data.trends.total;
            trendChart.data.datasets[1].data = data.trends.resolved;
            trendChart.update();
        }
        
        const deptChart = Chart.getChart('deptPerformanceChart');
        if (deptChart && data.departments) {
            deptChart.data.labels = data.departments.labels;
            deptChart.data.datasets[0].data = data.departments.data;
            deptChart.update();
        }
    } catch (error) {
        console.error('Error refreshing charts:', error);
    }
}


// CLEANUP ON PAGE UNLOAD

// Stop auto-refresh when page is unloaded
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});