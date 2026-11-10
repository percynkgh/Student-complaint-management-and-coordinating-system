
// MODAL FUNCTIONS


/*Open the Add Department modal*/
function openAddModal() {
    const modal = document.getElementById('deptModal');
    if (!modal) return;
    
    // Set modal title and form action
    document.getElementById('modalTitle').innerText = 'Add Department';
    document.getElementById('formAction').value = 'add';
    
    // Clear form fields
    document.getElementById('dept_id').value = '';
    document.getElementById('dept_name').value = '';
    document.getElementById('dept_code').value = '';
    document.getElementById('description').value = '';
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

/**
 * Open the Edit Department modal with pre-filled data
 * @param {object} dept - Department object containing id, name, code, description
 */
function openEditModal(dept) {
    const modal = document.getElementById('deptModal');
    if (!modal) return;
    
    // Set modal title and form action
    document.getElementById('modalTitle').innerText = 'Edit Department';
    document.getElementById('formAction').value = 'edit';
    
    // Fill form with existing department data
    document.getElementById('dept_id').value = dept.dept_id;
    document.getElementById('dept_name').value = dept.dept_name;
    document.getElementById('dept_code').value = dept.dept_code;
    document.getElementById('description').value = dept.description || '';
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

/**
 * Open the Delete Department confirmation modal
 * @param {number} id - Department ID
 * @param {string} name - Department name
 */
function openDeleteModal(id, name) {
    const modal = document.getElementById('deleteModal');
    if (!modal) return;
    
    // Set delete form values
    document.getElementById('deleteDeptId').value = id;
    document.getElementById('deleteDeptName').innerText = name;
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

//Close the Add/Edit Department modal
function closeModal() {
    const modal = document.getElementById('deptModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

//Close the Delete Confirmation modal
 
function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}


// FORM VALIDATION


/**
 * Validate department form before submission
 * @returns {boolean} - True if valid, false otherwise
 */
function validateDepartmentForm() {
    const nameInput = document.getElementById('dept_name');
    const codeInput = document.getElementById('dept_code');
    
    // Check department name
    if (!nameInput || !nameInput.value.trim()) {
        showNotification('Department name is required', 'error');
        if (nameInput) nameInput.focus();
        return false;
    }
    
    // Check department code
    if (!codeInput || !codeInput.value.trim()) {
        showNotification('Department code is required', 'error');
        if (codeInput) codeInput.focus();
        return false;
    }
    
    // Validate code format (2-10 letters/numbers, uppercase recommended)
    const codeRegex = /^[A-Za-z0-9]{2,10}$/;
    if (!codeRegex.test(codeInput.value.trim())) {
        showNotification('Department code must be 2-10 letters or numbers only', 'error');
        if (codeInput) codeInput.focus();
        return false;
    }
    
    // Optional: Check for duplicate code (AJAX check)
    // This can be added if needed
    
    return true;
}

/**
 * Check if department can be deleted (has no complaints/users)
 * @param {number} deptId - Department ID
 * @param {number} complaintCount - Number of complaints in this department
 * @param {number} userCount - Number of users in this department
 * @returns {boolean} - True if can be deleted
 */
function canDeleteDepartment(deptId, complaintCount, userCount) {
    if (complaintCount > 0) {
        showNotification(`Cannot delete: This department has ${complaintCount} complaint(s). Reassign or resolve them first.`, 'error');
        return false;
    }
    
    if (userCount > 0) {
        showNotification(`Cannot delete: This department has ${userCount} user(s) assigned. Reassign them first.`, 'error');
        return false;
    }
    
    return true;
}


// SEARCH AND FILTER


// Filter departments table based on search input
 
function filterDepartments() {
    const searchInput = document.getElementById('deptSearch');
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.toLowerCase();
    const rows = document.querySelectorAll('#departmentsTable tbody tr');
    
    rows.forEach(row => {
        const nameCell = row.cells[1];
        const codeCell = row.cells[2];
        
        if (nameCell && codeCell) {
            const name = nameCell.textContent.toLowerCase();
            const code = codeCell.textContent.toLowerCase();
            
            const matches = name.includes(searchTerm) || code.includes(searchTerm);
            row.style.display = matches ? '' : 'none';
        }
    });
    
    // Update visible count
    const visibleRows = document.querySelectorAll('#departmentsTable tbody tr[style=""]');
    const countDisplay = document.getElementById('filteredCount');
    if (countDisplay) {
        countDisplay.textContent = visibleRows.length;
    }
}

// Clear department search filter
 
function clearDepartmentFilter() {
    const searchInput = document.getElementById('deptSearch');
    if (searchInput) {
        searchInput.value = '';
        filterDepartments();
    }
}


// EXPORT FUNCTIONS


/**
 * Export departments list
 * @param {string} format - 'csv' or 'pdf'
 */
function exportDepartments(format) {
    if (format === 'csv') {
        window.location.href = 'export_departments.php?format=csv';
    } else if (format === 'pdf') {
        window.location.href = 'export_departments.php?format=pdf';
    } else {
        showNotification('Invalid export format', 'error');
    }
}


// QUICK STATS REFRESH

// Refresh department statistics without reloading page
 
async function refreshDepartmentStats() {
    try {
        const response = await fetch('get_department_stats.php');
        const data = await response.json();
        
        if (data.success) {
            // Update total departments count
            const statsNumber = document.querySelector('.stats-number');
            if (statsNumber && data.total_departments) {
                const current = parseInt(statsNumber.innerText);
                if (current !== data.total_departments) {
                    statsNumber.innerText = data.total_departments;
                    
                    // Show warning if still more than 5
                    const warningBox = document.querySelector('.warning-box');
                    if (data.total_departments > 5 && warningBox) {
                        warningBox.style.display = 'block';
                        warningBox.innerHTML = `⚠️ You have ${data.total_departments} departments. Delete the ones that are not actual departments.`;
                    } else if (warningBox) {
                        warningBox.style.display = 'none';
                    }
                }
            }
        }
    } catch (error) {
        console.error('Error refreshing stats:', error);
    }
}


// INITIALIZATION


document.addEventListener('DOMContentLoaded', function() {
    console.log('Department Management JS loaded');
    
    // Setup department form validation
    const deptForm = document.querySelector('#deptModal form');
    if (deptForm) {
        deptForm.addEventListener('submit', function(e) {
            if (!validateDepartmentForm()) {
                e.preventDefault();
            }
        });
    }
    
    // Setup search input
    const searchInput = document.getElementById('deptSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', filterDepartments);
    }
    
    // Setup clear filter button
    const clearBtn = document.getElementById('clearFilterBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearDepartmentFilter);
    }
    
    // Setup refresh stats button
    const refreshBtn = document.getElementById('refreshStatsBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshDepartmentStats);
    }
    
    // Auto-refresh stats every 30 seconds (optional)
    // setInterval(refreshDepartmentStats, 30000);
});


// HELPER FUNCTIONS 


/**
 * Check if department code is unique (AJAX)
 * @param {string} code // Department code to check
 * @param {number} excludeId // Department ID to exclude (for editing)
 */
async function checkDepartmentCodeUnique(code, excludeId = null) {
    try {
        const response = await fetch('check_dept_code.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code, exclude_id: excludeId })
        });
        const data = await response.json();
        return data.unique;
    } catch (error) {
        console.error('Error checking code uniqueness:', error);
        return false;
    }
}

/* Highlight duplicate department warning*/
function showDuplicateWarning() {
    const warningBox = document.querySelector('.warning-box');
    if (warningBox) {
        warningBox.style.display = 'block';
        warningBox.style.backgroundColor = '#fff3cd';
        warningBox.style.borderLeftColor = '#ffc107';
    }
}