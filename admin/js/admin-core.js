



/*Escape HTML to prevent XSS attacks */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/* Format date for display */
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
}

/* Format date with time */
function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/* Show notification toast */
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Set colors based on type
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#667eea'
    };
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        background: ${colors[type] || colors.info};
        color: white;
        z-index: 9999;
        animation: slideIn 0.3s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

/* Confirm action with dialog box*/
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/* Copy text to clipboard*/
function copyToClipboard(text, successMessage = 'Copied!') {
    navigator.clipboard.writeText(text).then(() => {
        showNotification(successMessage, 'success');
    }).catch(() => {
        showNotification('Failed to copy', 'error');
    });
}

/* Generate a random string */
function generateRandomString(length = 8) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

/* Generate a strong password*/
function generateStrongPassword() {
    const length = 12;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
    let password = "";
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return password;
}


// FORM VALIDATION FUNCTIONS


/*Validate email format*/
function isValidEmail(email) {
    const re = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
    return re.test(email);
}

/* Validate phone number */
function isValidPhone(phone) {
    const re = /^[0-9]{10,15}$/;
    return re.test(phone);
}

/*Validate student/staff ID  */
function isValidID(id) {
    const re = /^[A-Z0-9]{6,15}$/;
    return re.test(id);
}


// TABLE FUNCTIONS


/*Filter table rows by search input*/
function filterTable(inputId, tableId, columnIndex) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 1; i < rows.length; i++) {
            const cell = rows[i].getElementsByTagName('td')[columnIndex];
            if (cell) {
                const text = cell.textContent.toLowerCase();
                rows[i].style.display = text.indexOf(filter) > -1 ? '' : 'none';
            }
        }
    });
}


// MODAL FUNCTIONS


let currentModal = null;

/* Open a modal*/
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        currentModal = modal;
        document.body.style.overflow = 'hidden';
    }
}

/* Close the current modal*/
function closeModal() {
    if (currentModal) {
        currentModal.style.display = 'none';
        currentModal = null;
        document.body.style.overflow = 'auto';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (currentModal && event.target === currentModal) {
        closeModal();
    }
};


// AJAX FUNCTIONS


/*Fetch data from API*/
async function fetchAPI(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        showNotification('An error occurred', 'error');
        throw error;
    }
}

/*POST data to API*/
async function postAPI(url, data) {
    return fetchAPI(url, {
        method: 'POST',
        body: JSON.stringify(data)
    });
}


// PASSWORD FUNCTIONS

/*Toggle password visibility*/
function togglePassword(inputId, toggleBtnId) {
    const input = document.getElementById(inputId);
    const toggle = document.getElementById(toggleBtnId);
    
    if (!input || !toggle) return;
    
    toggle.addEventListener('click', () => {
        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
        input.setAttribute('type', type);
        toggle.textContent = type === 'password' ? '' : '';
    });
}

/*Check password strength and update indicator*/
function checkPasswordStrength(passwordInputId, strengthBarId) {
    const input = document.getElementById(passwordInputId);
    if (!input) return;
    
    input.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]+/)) strength++;
        if (password.match(/[A-Z]+/)) strength++;
        if (password.match(/[0-9]+/)) strength++;
        if (password.match(/[$@#&!]+/)) strength++;
        
        const strengthBar = document.getElementById(strengthBarId);
        if (!strengthBar) return;
        
        const colors = ['#dc3545', '#ffc107', '#ffc107', '#28a745', '#28a745'];
        const width = strength * 20;
        
        strengthBar.style.width = width + '%';
        strengthBar.style.backgroundColor = colors[strength-1] || '#dc3545';
        
        // Change text color based on strength
        const strengthText = document.getElementById('strengthText');
        if (strengthText) {
            const messages = ['Weak', 'Fair', 'Fair', 'Good', 'Strong'];
            strengthText.textContent = messages[strength-1] || 'Weak';
            strengthText.style.color = colors[strength-1] || '#dc3545';
        }
    });
}

// INITIALIZATION


// Add CSS animations to page when this script loads
document.addEventListener('DOMContentLoaded', function() {
    // Check if animations already added
    if (!document.getElementById('admin-core-styles')) {
        const style = document.createElement('style');
        style.id = 'admin-core-styles';
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            
            .notification {
                font-size: 14px;
                font-weight: 500;
                z-index: 9999;
            }
        `;
        document.head.appendChild(style);
    }
    
    console.log('Admin core JS loaded');
});