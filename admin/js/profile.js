
// PROFILE UPDATE FUNCTIONS

/**
 * Validate profile update form
 * @returns {boolean} - True if valid
 */
function validateProfileForm() {
    const fullName = document.querySelector('input[name="full_name"]');
    const email = document.querySelector('input[name="email"]');
    const phone = document.querySelector('input[name="phone"]');
    
    // Check full name
    if (!fullName || !fullName.value.trim()) {
        showNotification('Full name is required', 'error');
        if (fullName) fullName.focus();
        return false;
    }
    
    // Check email
    if (!email || !email.value.trim()) {
        showNotification('Email address is required', 'error');
        if (email) email.focus();
        return false;
    }
    
    // Validate email format
    if (!isValidEmail(email.value.trim())) {
        showNotification('Please enter a valid email address', 'error');
        if (email) email.focus();
        return false;
    }
    
    // Validate phone (optional)
    if (phone && phone.value.trim()) {
        if (!isValidPhone(phone.value.trim())) {
            showNotification('Phone number must be 10-15 digits', 'error');
            if (phone) phone.focus();
            return false;
        }
    }
    
    return true;
}

/*Submit profile update form*/
function updateProfile() {
    if (validateProfileForm()) {
        document.getElementById('profileForm').submit();
    }
}


// PASSWORD CHANGE FUNCTIONS

/**
 * Validate password change form
 * @returns {boolean} - True if valid
 */
function validatePasswordForm() {
    const currentPassword = document.querySelector('input[name="current_password"]');
    const newPassword = document.querySelector('input[name="new_password"]');
    const confirmPassword = document.querySelector('input[name="confirm_password"]');
    
    // Check all fields are filled
    if (!currentPassword || !currentPassword.value) {
        showNotification('Current password is required', 'error');
        if (currentPassword) currentPassword.focus();
        return false;
    }
    
    if (!newPassword || !newPassword.value) {
        showNotification('New password is required', 'error');
        if (newPassword) newPassword.focus();
        return false;
    }
    
    if (!confirmPassword || !confirmPassword.value) {
        showNotification('Please confirm your new password', 'error');
        if (confirmPassword) confirmPassword.focus();
        return false;
    }
    
    // Check password length
    if (newPassword.value.length < 8) {
        showNotification('New password must be at least 8 characters', 'error');
        if (newPassword) newPassword.focus();
        return false;
    }
    
    // Check passwords match
    if (newPassword.value !== confirmPassword.value) {
        showNotification('New passwords do not match', 'error');
        if (confirmPassword) confirmPassword.focus();
        return false;
    }
    
    return true;
}

/* Submit password change form*/
function changePassword() {
    if (validatePasswordForm()) {
        document.getElementById('passwordForm').submit();
    }
}

/* Generate and fill a strong password*/
function fillStrongPassword() {
    const strongPassword = generateStrongPassword();
    const newPasswordInput = document.querySelector('input[name="new_password"]');
    const confirmInput = document.querySelector('input[name="confirm_password"]');
    
    if (newPasswordInput) {
        newPasswordInput.value = strongPassword;
        // Trigger password strength check
        const event = new Event('input');
        newPasswordInput.dispatchEvent(event);
    }
    if (confirmInput) {
        confirmInput.value = strongPassword;
    }
    
    showNotification('Strong password generated! Please save it securely.', 'success');
}


// PASSWORD STRENGTH INDICATOR

/* Check password strength and update indicator*/
function checkPasswordStrength() {
    const passwordInput = document.querySelector('input[name="new_password"]');
    if (!passwordInput) return;
    
    // Create strength bar if it doesn't exist
    let strengthBar = document.getElementById('strengthBar');
    let strengthText = document.getElementById('strengthText');
    
    if (!strengthBar) {
        const container = passwordInput.parentElement;
        const barContainer = document.createElement('div');
        barContainer.className = 'strength-container';
        barContainer.style.marginTop = '8px';
        
        strengthBar = document.createElement('div');
        strengthBar.id = 'strengthBar';
        strengthBar.style.cssText = `
            height: 4px;
            border-radius: 2px;
            transition: width 0.3s, background 0.3s;
            width: 0%;
        `;
        
        strengthText = document.createElement('span');
        strengthText.id = 'strengthText';
        strengthText.style.cssText = `
            font-size: 11px;
            margin-top: 4px;
            display: inline-block;
        `;
        
        barContainer.appendChild(strengthBar);
        barContainer.appendChild(strengthText);
        container.appendChild(barContainer);
    }
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Length check
        if (password.length >= 8) strength++;
        // Lowercase check
        if (password.match(/[a-z]+/)) strength++;
        // Uppercase check
        if (password.match(/[A-Z]+/)) strength++;
        // Number check
        if (password.match(/[0-9]+/)) strength++;
        // Special character check
        if (password.match(/[$@#&!%*?]+/)) strength++;
        
        const colors = ['#dc3545', '#ffc107', '#ffc107', '#28a745', '#28a745'];
        const messages = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const width = (strength / 5) * 100;
        
        strengthBar.style.width = width + '%';
        strengthBar.style.backgroundColor = colors[strength - 1] || '#dc3545';
        strengthText.textContent = messages[strength - 1] || 'Very Weak';
        strengthText.style.color = colors[strength - 1] || '#dc3545';
    });
}


// AVATAR UPLOAD FUNCTIONS

/**
 * Handle avatar file selection
 */
function handleAvatarSelect() {
    const fileInput = document.getElementById('avatar');
    if (!fileInput) return;
    
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        
        // Check file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
            showNotification('Only JPG, PNG, and GIF files are allowed', 'error');
            this.value = '';
            return;
        }
        
        // Check file size (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            showNotification('File size must be less than 2MB', 'error');
            this.value = '';
            return;
        }
        
        // Preview image
        const reader = new FileReader();
        reader.onload = function(e) {
            const avatarImg = document.querySelector('.avatar');
            const avatarPlaceholder = document.querySelector('.avatar-placeholder');
            
            if (avatarImg) {
                avatarImg.src = e.target.result;
            }
            if (avatarPlaceholder) {
                // Replace placeholder with image
                const newImg = document.createElement('img');
                newImg.src = e.target.result;
                newImg.className = 'avatar';
                newImg.alt = 'Profile Picture';
                avatarPlaceholder.parentNode.replaceChild(newImg, avatarPlaceholder);
            }
        };
        reader.readAsDataURL(file);
        
        // Auto-submit the form
        document.getElementById('avatarForm').submit();
    });
}

// Remove profile picture
 
async function removeAvatar() {
    confirmAction('Are you sure you want to remove your profile picture?', async () => {
        try {
            const response = await fetch('remove_avatar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await response.json();
            
            if (data.success) {
                showNotification('Profile picture removed', 'success');
                location.reload();
            } else {
                showNotification(data.message || 'Failed to remove picture', 'error');
            }
        } catch (error) {
            console.error('Error removing avatar:', error);
            showNotification('An error occurred', 'error');
        }
    });
}


// ACTIVITY LOG FUNCTIONS

// Load more activity logs
 
async function loadMoreActivity() {
    const lastId = document.querySelector('.activity-item:last-child')?.dataset.logId;
    if (!lastId) return;
    
    const loadBtn = document.getElementById('loadMoreBtn');
    if (loadBtn) {
        loadBtn.disabled = true;
        loadBtn.textContent = 'Loading...';
    }
    
    try {
        const response = await fetch(`get_activity_logs.php?last_id=${lastId}&limit=10`);
        const data = await response.json();
        
        if (data.success && data.logs.length > 0) {
            const container = document.getElementById('activityLogs');
            data.logs.forEach(log => {
                appendActivityLog(log);
            });
        } else {
            const loadBtn = document.getElementById('loadMoreBtn');
            if (loadBtn) {
                loadBtn.textContent = 'No more logs';
                loadBtn.disabled = true;
            }
        }
    } catch (error) {
        console.error('Error loading more logs:', error);
        showNotification('Failed to load more logs', 'error');
    } finally {
        const loadBtn = document.getElementById('loadMoreBtn');
        if (loadBtn) {
            loadBtn.disabled = false;
            loadBtn.textContent = 'Load More';
        }
    }
}

/**
 * Append activity log to list
 * @param {object} log - Activity log object
 */
function appendActivityLog(log) {
    const container = document.getElementById('activityLogs');
    if (!container) return;
    
    const logDiv = document.createElement('div');
    logDiv.className = 'activity-item';
    logDiv.dataset.logId = log.log_id;
    logDiv.innerHTML = `
        <div class="activity-time">${formatDateTime(log.created_at)}</div>
        <div class="activity-action">${escapeHtml(log.action)}</div>
        <div class="activity-details">${escapeHtml(log.details || '')}</div>
        <div class="activity-ip">IP: ${escapeHtml(log.ip_address)}</div>
    `;
    container.appendChild(logDiv);
}


// SESSION MANAGEMENT

/* Show active sessions*/
async function showActiveSessions() {
    try {
        const response = await fetch('get_active_sessions.php');
        const data = await response.json();
        
        if (data.success && data.sessions.length > 0) {
            let message = 'Active Sessions:\n\n';
            data.sessions.forEach(session => {
                message += `- ${session.ip_address} (${session.last_activity})\n`;
            });
            message += '\nLog out from other devices?';
            
            if (confirm(message)) {
                await logoutOtherDevices();
            }
        } else {
            showNotification('No other active sessions found', 'info');
        }
    } catch (error) {
        console.error('Error fetching sessions:', error);
        showNotification('Failed to fetch sessions', 'error');
    }
}

/*Log out from all other devices*/
async function logoutOtherDevices() {
    try {
        const response = await fetch('logout_others.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();
        
        if (data.success) {
            showNotification('Logged out from other devices', 'success');
        } else {
            showNotification(data.message || 'Failed to logout others', 'error');
        }
    } catch (error) {
        console.error('Error logging out others:', error);
        showNotification('An error occurred', 'error');
    }
}


// TWO-FACTOR AUTHENTICATION

/* Enable 2FA for account*/
async function enable2FA() {
    try {
        const response = await fetch('enable_2fa.php');
        const data = await response.json();
        
        if (data.success) {
            // Show QR code modal
            show2FAModal(data.qr_code, data.secret);
        } else {
            showNotification(data.message || 'Failed to enable 2FA', 'error');
        }
    } catch (error) {
        console.error('Error enabling 2FA:', error);
        showNotification('An error occurred', 'error');
    }
}

/**
 * Show 2FA setup modal
 * @param {string} qrCode - QR code image data
 * @param {string} secret - Secret key
 */
function show2FAModal(qrCode, secret) {
    const modal = document.getElementById('twoFAModal');
    if (!modal) return;
    
    const qrContainer = document.getElementById('qrCode');
    const secretSpan = document.getElementById('secretKey');
    
    if (qrContainer) qrContainer.innerHTML = `<img src="${qrCode}" alt="QR Code">`;
    if (secretSpan) secretSpan.textContent = secret;
    
    modal.style.display = 'flex';
}

/* Verify 2FA code*/
async function verify2FA() {
    const codeInput = document.getElementById('verificationCode');
    const code = codeInput ? codeInput.value : '';
    
    if (!code) {
        showNotification('Please enter verification code', 'error');
        return;
    }
    
    try {
        const response = await fetch('verify_2fa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code: code })
        });
        const data = await response.json();
        
        if (data.success) {
            showNotification('2FA enabled successfully', 'success');
            closeModal();
            location.reload();
        } else {
            showNotification(data.message || 'Invalid verification code', 'error');
        }
    } catch (error) {
        console.error('Error verifying 2FA:', error);
        showNotification('An error occurred', 'error');
    }
}


// INITIALIZATION

document.addEventListener('DOMContentLoaded', function() {
    console.log('Profile JS loaded');
    
    // Setup profile form
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            if (!validateProfileForm()) {
                e.preventDefault();
            }
        });
    }
    
    // Setup password form
    const passwordForm = document.getElementById('passwordForm');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            if (!validatePasswordForm()) {
                e.preventDefault();
            }
        });
    }
    
    // Setup password strength checker
    checkPasswordStrength();
    
    // Setup avatar upload
    handleAvatarSelect();
    
    // Setup load more button
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', loadMoreActivity);
    }
    
    // Setup 2FA button
    const enable2FABtn = document.getElementById('enable2FA');
    if (enable2FABtn) {
        enable2FABtn.addEventListener('click', enable2FA);
    }
    
    // Setup session management button
    const sessionsBtn = document.getElementById('viewSessions');
    if (sessionsBtn) {
        sessionsBtn.addEventListener('click', showActiveSessions);
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('twoFAModal');
        if (event.target === modal) {
            closeModal();
        }
    };
});