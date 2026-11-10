

let trendsChart = null;
let departmentChart = null;
let priorityChart = null;
let satisfactionChart = null;
let resolutionChart = null;


// EXPORT FUNCTION - Called by PDF/Excel/CSV buttons
function exportReport(format) {
    console.log('Exporting to:', format);
    
    // Get current form values
    const reportType = document.querySelector('select[name="type"]')?.value || 'summary';
    const dateFrom = document.querySelector('input[name="date_from"]')?.value || '';
    const dateTo = document.querySelector('input[name="date_to"]')?.value || '';
    const department = document.querySelector('select[name="department"]')?.value || 'all';
    
    // Validate date range
    if (!dateFrom || !dateTo) {
        showNotification('Please select a date range', 'warning');
        return;
    }
    
    // Build export URL
    const url = window.location.pathname + 
        '?export=1' +
        '&format=' + encodeURIComponent(format) +
        '&type=' + encodeURIComponent(reportType) +
        '&date_from=' + encodeURIComponent(dateFrom) +
        '&date_to=' + encodeURIComponent(dateTo) +
        '&department=' + encodeURIComponent(department);
    
    console.log('Export URL:', url);
    
    // Show loading notification
    showNotification('Generating ' + format.toUpperCase() + ' file...', 'info');
    
    // Redirect to download
    window.location.href = url;
}


// GENERATE REPORT FUNCTION 
function generateReport() {
    console.log('Generating report...');
    
    // Get form values
    const reportType = document.querySelector('select[name="type"]')?.value || 'summary';
    const dateFrom = document.querySelector('input[name="date_from"]')?.value;
    const dateTo = document.querySelector('input[name="date_to"]')?.value;
    const department = document.querySelector('select[name="department"]')?.value || 'all';
    
    // Validate
    if (!dateFrom || !dateTo) {
        showNotification('Please select both start and end dates', 'warning');
        return;
    }
    
    // Build URL with parameters
    const url = window.location.pathname +
        '?type=' + encodeURIComponent(reportType) +
        '&date_from=' + encodeURIComponent(dateFrom) +
        '&date_to=' + encodeURIComponent(dateTo) +
        '&department=' + encodeURIComponent(department);
    
    console.log('Generate URL:', url);
    
    // Show loading
    showNotification('Loading report...', 'info');
    
    // Navigate to new URL
    window.location.href = url;
}


// NOTIFICATION FUNCTION

function showNotification(message, type = 'info') {
    // Create container if it doesn't exist
    let container = document.getElementById('notificationContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notificationContainer';
        container.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        `;
        document.body.appendChild(container);
    }
    
    // Create notification element
    const notification = document.createElement('div');
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#667eea'
    };
    
    notification.style.cssText = `
        padding: 12px 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-left: 4px solid ${colors[type] || colors.info};
        min-width: 250px;
        max-width: 350px;
        animation: slideIn 0.3s ease;
        font-family: Arial, sans-serif;
        font-size: 14px;
    `;
    
    notification.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span>${message}</span>
            <button style="background: none; border: none; font-size: 18px; cursor: pointer; margin-left: 10px; color: #999;">&times;</button>
        </div>
    `;
    
    container.appendChild(notification);
    
    // Auto remove after 3 seconds
    const timeout = setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
    
    // Close button handler
    const closeBtn = notification.querySelector('button');
    closeBtn.onclick = () => {
        clearTimeout(timeout);
        notification.remove();
    };
}


// VALIDATION HELPER
function validateChartData(data, defaultValue = 0) {
    if (!data || !Array.isArray(data) || data.length === 0) {
        return [defaultValue, defaultValue, defaultValue, defaultValue, defaultValue];
    }
    return data.map(item => {
        const num = Number(item);
        return isNaN(num) ? defaultValue : num;
    });
}


// TRENDS CHART

function initTrendsChart(labels, data, resolvedData) {
    const canvas = document.getElementById('trendsChart');
    if (!canvas || typeof Chart === 'undefined') return;
    
    if (trendsChart) trendsChart.destroy();
    
    const validatedData = validateChartData(data);
    const validatedResolved = validateChartData(resolvedData);
    const validatedLabels = labels && labels.length ? labels : Array(validatedData.length).fill('');
    
    trendsChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: validatedLabels,
            datasets: [
                {
                    label: 'Complaints Received',
                    data: validatedData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Resolved',
                    data: validatedResolved,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#28a745',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 10 } },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Number of Complaints' } },
                x: { title: { display: true, text: 'Month' } }
            }
        }
    });
}


// DEPARTMENT CHART

function initDepartmentChart(departments, totals, resolved) {
    const canvas = document.getElementById('departmentChart');
    if (!canvas || typeof Chart === 'undefined') return;
    
    if (departmentChart) departmentChart.destroy();
    
    const validatedTotals = validateChartData(totals);
    const validatedResolved = validateChartData(resolved);
    const validatedDepartments = departments && departments.length ? departments : Array(validatedTotals.length).fill('');
    
    departmentChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: validatedDepartments,
            datasets: [
                { label: 'Total Complaints', data: validatedTotals, backgroundColor: 'rgba(102, 126, 234, 0.7)', borderColor: '#667eea', borderWidth: 1 },
                { label: 'Resolved', data: validatedResolved, backgroundColor: 'rgba(40, 167, 69, 0.7)', borderColor: '#28a745', borderWidth: 1 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Complaints' } } }
        }
    });
}


// PRIORITY CHART

function initPriorityChart(priorities) {
    const canvas = document.getElementById('priorityChart');
    if (!canvas || typeof Chart === 'undefined') return;
    
    if (priorityChart) priorityChart.destroy();
    
    const labels = ['Urgent', 'High', 'Medium', 'Low'];
    const data = [priorities.urgent || 0, priorities.high || 0, priorities.medium || 0, priorities.low || 0];
    const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745'];
    
    priorityChart = new Chart(canvas, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: data, backgroundColor: colors, borderWidth: 0, hoverOffset: 10 }] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 10 } },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}


// SATISFACTION CHART

function initSatisfactionChart(scores) {
    const canvas = document.getElementById('satisfactionChart');
    if (!canvas || typeof Chart === 'undefined') return;
    
    if (satisfactionChart) satisfactionChart.destroy();
    
    satisfactionChart = new Chart(canvas, {
        type: 'radar',
        data: {
            labels: ['Speed', 'Communication', 'Effectiveness', 'Overall'],
            datasets: [{
                label: 'Satisfaction Score (1-5)',
                data: [scores.speed || 0, scores.communication || 0, scores.effectiveness || 0, scores.overall || 0],
                backgroundColor: 'rgba(102, 126, 234, 0.2)',
                borderColor: '#667eea',
                borderWidth: 2,
                pointBackgroundColor: '#667eea',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { r: { beginAtZero: true, max: 5, ticks: { stepSize: 1 } } }
        }
    });
}


// RESOLUTION CHART

function initResolutionChart(departments, avgHours) {
    const canvas = document.getElementById('resolutionChart');
    if (!canvas || typeof Chart === 'undefined') return;
    
    if (resolutionChart) resolutionChart.destroy();
    
    const validatedAvgHours = validateChartData(avgHours);
    const validatedDepartments = departments && departments.length ? departments : Array(validatedAvgHours.length).fill('');
    
    resolutionChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: validatedDepartments,
            datasets: [{ label: 'Average Resolution Time (Hours)', data: validatedAvgHours, backgroundColor: 'rgba(255, 193, 7, 0.7)', borderColor: '#ffc107', borderWidth: 1 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Hours' } } }
        }
    });
}


// SET DATE RANGE PRESET

function setDateRange(range) {
    const today = new Date();
    const from = new Date();
    
    switch(range) {
        case '7d': from.setDate(today.getDate() - 7); break;
        case '30d': from.setDate(today.getDate() - 30); break;
        case '90d': from.setDate(today.getDate() - 90); break;
        case '12m': from.setMonth(today.getMonth() - 12); break;
        default: return;
    }
    
    const dateFromInput = document.querySelector('input[name="date_from"]');
    const dateToInput = document.querySelector('input[name="date_to"]');
    
    if (dateFromInput) dateFromInput.value = from.toISOString().split('T')[0];
    if (dateToInput) dateToInput.value = today.toISOString().split('T')[0];
    
    // Auto-submit the form
    const reportForm = document.getElementById('reportForm');
    if (reportForm) reportForm.submit();
}


// INITIALIZATION
document.addEventListener('DOMContentLoaded', function() {
    console.log('Analytics JS loaded - Ready!');
    
    // Initialize charts with data from data attributes
    const trendsCanvas = document.getElementById('trendsChart');
    if (trendsCanvas && trendsCanvas.dataset.labels) {
        try {
            initTrendsChart(
                JSON.parse(trendsCanvas.dataset.labels),
                JSON.parse(trendsCanvas.dataset.data),
                JSON.parse(trendsCanvas.dataset.resolved)
            );
        } catch (e) { console.error('Trends chart error:', e); }
    }
    
    const deptCanvas = document.getElementById('departmentChart');
    if (deptCanvas && deptCanvas.dataset.labels) {
        try {
            initDepartmentChart(
                JSON.parse(deptCanvas.dataset.labels),
                JSON.parse(deptCanvas.dataset.totals),
                JSON.parse(deptCanvas.dataset.resolved)
            );
        } catch (e) { console.error('Department chart error:', e); }
    }
    
    const priorityCanvas = document.getElementById('priorityChart');
    if (priorityCanvas && priorityCanvas.dataset.priorities) {
        try {
            initPriorityChart(JSON.parse(priorityCanvas.dataset.priorities));
        } catch (e) { console.error('Priority chart error:', e); }
    }
    
    const satisfactionCanvas = document.getElementById('satisfactionChart');
    if (satisfactionCanvas && satisfactionCanvas.dataset.scores) {
        try {
            initSatisfactionChart(JSON.parse(satisfactionCanvas.dataset.scores));
        } catch (e) { console.error('Satisfaction chart error:', e); }
    }
    
    const resolutionCanvas = document.getElementById('resolutionChart');
    if (resolutionCanvas && resolutionCanvas.dataset.departments) {
        try {
            initResolutionChart(
                JSON.parse(resolutionCanvas.dataset.departments),
                JSON.parse(resolutionCanvas.dataset.avgHours)
            );
        } catch (e) { console.error('Resolution chart error:', e); }
    }
    
    // Add animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
    
    console.log('All systems ready!');
});