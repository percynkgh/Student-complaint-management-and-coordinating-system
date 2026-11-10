

 //AJAX functions for Department Details page

// Mirrors the PHP formatDuration() helper, for values rendered client-side.
function formatDuration(hours) {
    if (hours === null || hours === undefined || hours === '') return 'N/A';
    const totalMinutes = Math.round(parseFloat(hours) * 60);
    if (isNaN(totalMinutes)) return 'N/A';
    if (totalMinutes < 1) return '<1 min';
    if (totalMinutes < 60) return totalMinutes + ' min';
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
}

// Show/hide loading indicator
function showLoading(show) {
    let loader = document.getElementById('loadingOverlay');
    if (show) {
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'loadingOverlay';
            loader.className = 'loading-overlay';
            loader.innerHTML = '<div class="loading-spinner">Loading department data...</div>';
            document.body.appendChild(loader);
        } else {
            loader.style.display = 'flex';
        }
    } else {
        if (loader) loader.style.display = 'none';
    }
}

// Main function to fetch department details via AJAX
function getDepartmentDetails(date_from, date_to, department) {
    showLoading(true);
    
    // Build URL with query parameters
    const params = new URLSearchParams({
        ajax: 1,
        date_from: date_from,
        date_to: date_to,
        department: department
    });
    
    fetch('get_department_data.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            if (data.success) {
                updateStatisticsCards(data.stats);
                updateComplaintsTable(data.complaints);
            } else {
                alert('Error: ' + (data.message || 'Failed to load data'));
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Error:', error);
            alert('An error occurred while fetching department details.');
        });
}

// Update the statistics cards dynamically
function updateStatisticsCards(stats) {
    const statsGrid = document.getElementById('statsGrid');
    if (!statsGrid) return;
    
    if (!stats || stats.length === 0) {
        statsGrid.innerHTML = '<div class="dept-stat-card">No department statistics available</div>';
        return;
    }
    
    let html = '';
    stats.forEach(stat => {
        const stars = Math.round(stat.satisfaction || 0);
        let starHtml = '';
        for (let i = 1; i <= 5; i++) {
            starHtml += i <= stars ? '★' : '☆';
        }
        
        html += `
            <div class="dept-stat-card">
                <div class="dept-stat-header">
                    <span class="dept-stat-name">${escapeHtml(stat.department)}</span>
                    ${stat.satisfaction ? `<span class="satisfaction-stars">${starHtml}</span>` : ''}
                </div>
                <div class="dept-stat-numbers">
                    <div class="stat-item"><div class="number">${stat.total}</div><div class="label">Total</div></div>
                    <div class="stat-item"><div class="number">${stat.resolved}</div><div class="label">Resolved</div></div>
                    <div class="stat-item"><div class="number">${formatDuration(stat.avg_time)}</div><div class="label">Avg Resolution Time</div></div>
                    <div class="stat-item"><div class="number">${stat.urgent}</div><div class="label">Urgent</div></div>
                    <div class="stat-item"><div class="number">${stat.total_ratings || 0}</div><div class="label">Ratings</div></div>
                </div>
            </div>
        `;
    });
    statsGrid.innerHTML = html;
}

// Update the complaints table dynamically
function updateComplaintsTable(complaints) {
    const tableBody = document.querySelector('#complaintsTable tbody');
    if (!tableBody) return;
    
    if (!complaints || complaints.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="11" style="text-align:center; padding:40px;">No complaints found for the selected filters</td></tr>';
        return;
    }
    
    let html = '';
    complaints.forEach(c => {
        html += `
            <tr>
                <td>${c.complaint_id}</td>
                <td><strong>#${escapeHtml(c.complaint_number)}</strong></td>
                <td>${escapeHtml(c.title)}</td>
                <td>${escapeHtml(c.student_name)}</td>
                <td>${escapeHtml(c.department_assigned)}</td>
                <td>${escapeHtml(c.category_name || 'N/A')}</td>
                <td><span class="priority-badge priority-${c.priority}">${c.priority.toUpperCase()}</span></td>
                <td><span class="status-badge status-${c.status}">${c.status.charAt(0).toUpperCase() + c.status.slice(1)}</span></td>
                <td>${c.hours_old}</td>
                <td>${c.resolution_hours || '—'}</td>
                <td>${c.created_date}</td>
            </tr>
        `;
    });
    tableBody.innerHTML = html;
}

// Helper: escape HTML to prevent XSS
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    }).replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, function(c) {
        return c;
    });
}

// Export table to CSV (existing function, kept for compatibility)
function exportTableToCSV(customFilename) {
    const table = document.getElementById('complaintsTable');
    if (!table) {
        alert('Unable to export: complaints table not found.');
        return;
    }
    const rows = table.querySelectorAll('tr');
    const csv = [];
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    rows.forEach(row => {
        const rowData = [];
        row.querySelectorAll('td').forEach(td => {
            let text = td.textContent.trim().replace(/,/g, ';').replace(/\n/g, ' ');
            rowData.push(text);
        });
        if (rowData.length > 0) csv.push(rowData.join(','));
    });
    const csvContent = "\uFEFF" + csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = customFilename || 'department_details_export.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Attach event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const date_from = document.querySelector('input[name="date_from"]').value;
            const date_to = document.querySelector('input[name="date_to"]').value;
            const department = document.querySelector('select[name="department"]').value;
            getDepartmentDetails(date_from, date_to, department);
        });
    }
});