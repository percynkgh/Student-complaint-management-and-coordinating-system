
// NOTIFICATION FUNCTION

function showNotification(message, type = 'info') {
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
        margin-bottom: 10px;
    `;
    
    notification.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span>${message}</span>
            <button style="background: none; border: none; font-size: 18px; cursor: pointer; margin-left: 10px; color: #999;">&times;</button>
        </div>
    `;
    
    container.appendChild(notification);
    
    const timeout = setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
    
    const closeBtn = notification.querySelector('button');
    closeBtn.onclick = () => {
        clearTimeout(timeout);
        notification.remove();
    };
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


// BULK ACTION FUNCTIONS


function toggleAllComplaints(source) {
    const checkboxes = document.querySelectorAll('.complaint-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = source.checked;
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.complaint-checkbox:checked');
    const count = checkboxes.length;
    const selectedCountSpan = document.getElementById('selectedCount');
    
    if (selectedCountSpan) {
        selectedCountSpan.textContent = count + ' selected';
        selectedCountSpan.style.display = count > 0 ? 'inline-block' : 'none';
    }
    
    const applyBtn = document.getElementById('applyBulkAction');
    if (applyBtn) {
        applyBtn.disabled = count === 0;
    }
}

function toggleBulkOptions() {
    const actionSelect = document.getElementById('bulkAction');
    if (!actionSelect) return;
    
    const action = actionSelect.value;
    
    const statusDiv = document.getElementById('statusOptions');
    const priorityDiv = document.getElementById('priorityOptions');
    const deptDiv = document.getElementById('departmentOptions');
    const staffDiv = document.getElementById('staffOptions');
    
    if (statusDiv) statusDiv.style.display = 'none';
    if (priorityDiv) priorityDiv.style.display = 'none';
    if (deptDiv) deptDiv.style.display = 'none';
    if (staffDiv) staffDiv.style.display = 'none';
    
    if (action === 'update_status' && statusDiv) {
        statusDiv.style.display = 'inline-block';
    } 
    else if (action === 'update_priority' && priorityDiv) {
        priorityDiv.style.display = 'inline-block';
    } 
    else if (action === 'assign_department' && deptDiv) {
        deptDiv.style.display = 'inline-block';
    } 
    else if (action === 'assign_staff' && staffDiv) {
        staffDiv.style.display = 'inline-block';
    }
}

function validateBulkAction() {
    const actionSelect = document.getElementById('bulkAction');
    const selected = document.querySelectorAll('.complaint-checkbox:checked');
    
    if (selected.length === 0) {
        showNotification('Please select at least one complaint', 'error');
        return false;
    }
    
    if (!actionSelect || !actionSelect.value) {
        showNotification('Please select an action', 'error');
        return false;
    }
    
    const action = actionSelect.value;
    
    if (action === 'update_status') {
        const statusSelect = document.querySelector('select[name="bulk_status"]');
        if (!statusSelect || !statusSelect.value) {
            showNotification('Please select a status', 'error');
            return false;
        }
    }
    
    if (action === 'update_priority') {
        const prioritySelect = document.querySelector('select[name="bulk_priority"]');
        if (!prioritySelect || !prioritySelect.value) {
            showNotification('Please select a priority', 'error');
            return false;
        }
    }
    
    if (action === 'assign_department') {
        const deptSelect = document.querySelector('select[name="bulk_department"]');
        if (!deptSelect || !deptSelect.value) {
            showNotification('Please select a department', 'error');
            return false;
        }
    }
    
    if (action === 'assign_staff') {
        const staffSelect = document.querySelector('select[name="bulk_staff"]');
        if (!staffSelect || !staffSelect.value) {
            showNotification('Please select a staff member', 'error');
            return false;
        }
    }
    
    if (action === 'delete') {
        return confirm(`Are you sure you want to delete ${selected.length} complaint(s)? This cannot be undone.`);
    }
    
    return true;
}


// EXPORT FUNCTIONS


function exportAll() {
    const urlParams = new URLSearchParams(window.location.search);
    let url = 'export_complaints.php?all=1';
    
    const status = urlParams.get('status');
    const dept = urlParams.get('dept');
    const priority = urlParams.get('priority');
    const date_from = urlParams.get('date_from');
    const date_to = urlParams.get('date_to');
    
    if (status && status !== 'all') url += '&status=' + encodeURIComponent(status);
    if (dept && dept !== 'all') url += '&dept=' + encodeURIComponent(dept);
    if (priority && priority !== 'all') url += '&priority=' + encodeURIComponent(priority);
    if (date_from) url += '&date_from=' + encodeURIComponent(date_from);
    if (date_to) url += '&date_to=' + encodeURIComponent(date_to);
    
    showNotification('Exporting all complaints...', 'info');
    window.location.href = url;
}

function exportSelected() {
    const selected = document.querySelectorAll('.complaint-checkbox:checked');
    const selectedIds = Array.from(selected).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        showNotification('Please select at least one complaint to export', 'error');
        return;
    }
    
    showNotification(`Exporting ${selectedIds.length} complaint(s)...`, 'info');
    window.location.href = 'export_complaints.php?ids=' + selectedIds.join(',');
}


// FILTER FUNCTIONS


function applyFilters() {
    const statusFilter = document.getElementById('statusFilter');
    const deptFilter = document.getElementById('deptFilter');
    const priorityFilter = document.getElementById('priorityFilter');
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    
    let url = window.location.pathname + '?';
    const params = [];
    
    if (statusFilter && statusFilter.value !== 'all') {
        params.push('status=' + encodeURIComponent(statusFilter.value));
    }
    if (deptFilter && deptFilter.value !== 'all') {
        params.push('dept=' + encodeURIComponent(deptFilter.value));
    }
    if (priorityFilter && priorityFilter.value !== 'all') {
        params.push('priority=' + encodeURIComponent(priorityFilter.value));
    }
    if (dateFrom && dateFrom.value) {
        params.push('date_from=' + encodeURIComponent(dateFrom.value));
    }
    if (dateTo && dateTo.value) {
        params.push('date_to=' + encodeURIComponent(dateTo.value));
    }
    
    if (params.length > 0) {
        window.location.href = url + params.join('&');
    } else {
        window.location.href = window.location.pathname;
    }
}

function clearFilters() {
    window.location.href = window.location.pathname;
}


// SEARCH FUNCTIONS

function searchComplaints() {
    const searchInput = document.getElementById('complaintSearch');
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.toLowerCase();
    const table = document.getElementById('complaintsTable');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        if (row.cells.length >= 5) {
            const titleCell = row.cells[3];
            const studentCell = row.cells[4];
            const complaintNumberCell = row.cells[2];
            
            if (titleCell && studentCell && complaintNumberCell) {
                const title = titleCell.textContent.toLowerCase();
                const student = studentCell.textContent.toLowerCase();
                const number = complaintNumberCell.textContent.toLowerCase();
                
                const matches = title.includes(searchTerm) || 
                               student.includes(searchTerm) || 
                               number.includes(searchTerm);
                
                row.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            }
        }
    });
    
    const resultCount = document.getElementById('searchResultCount');
    if (resultCount) {
        resultCount.textContent = visibleCount;
    }
}


// INITIALIZATION

document.addEventListener('DOMContentLoaded', function() {
    console.log('Complaint Management JS loaded');
    
    const bulkAction = document.getElementById('bulkAction');
    if (bulkAction) {
        bulkAction.addEventListener('change', toggleBulkOptions);
        toggleBulkOptions();
    }
    
    const bulkForm = document.getElementById('bulkForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            if (!validateBulkAction()) {
                e.preventDefault();
            }
        });
    }
    
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            toggleAllComplaints(this);
        });
    }
    
    const checkboxes = document.querySelectorAll('.complaint-checkbox');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    
    const searchInput = document.getElementById('complaintSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', searchComplaints);
    }
    
    const applyFilterBtn = document.getElementById('applyFilters');
    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', applyFilters);
    }
    
    const clearFilterBtn = document.getElementById('clearFilters');
    if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', clearFilters);
    }
    
    const exportAllBtn = document.getElementById('exportAllBtn');
    if (exportAllBtn) {
        exportAllBtn.addEventListener('click', exportAll);
    }
    
    updateSelectedCount();
    
    console.log('All event listeners setup complete');
});