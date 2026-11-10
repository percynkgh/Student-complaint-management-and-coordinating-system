document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('title');
    const descriptionInput = document.getElementById('description');
    const duplicateAlert = document.getElementById('duplicateAlert');
    const duplicateList = document.getElementById('duplicateList');
    const checkBtn = document.getElementById('checkDuplicateBtn');
    const attachments = document.getElementById('attachments');
    const categorySelect = document.getElementById('category');
    const priorityDisplay = document.getElementById('priorityDisplay');
    const suggestionBox = document.getElementById('suggestionBox');
    const suggestedDept = document.getElementById('suggestedDept');
    const departmentSelect = document.getElementById('departmentSelect');
    
    let timeoutId;
    let lastCheck = '';
    let suggestionTimeout;
    
    function getDepartmentSuggestion() {
        const title = titleInput.value.trim();
        const description = descriptionInput.value.trim();
        
        if (title.length < 5 || description.length < 10) {
            suggestionBox.style.display = 'none';
            return;
        }
        
        suggestionBox.style.display = 'block';
        suggestedDept.innerHTML = '<span class="loading-dots">Analyzing</span>';
        suggestedDept.style.color = '#666';
        
        const formData = new FormData();
        formData.append('get_suggestion', '1');
        formData.append('title', title);
        formData.append('description', description);
        
        fetch('get_department_suggestion.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                suggestedDept.innerHTML = data.department;
                suggestedDept.style.color = '#28a745';
                for (let option of departmentSelect.options) {
                    if (option.value === data.department) {
                        option.selected = true;
                        break;
                    }
                }
            } else {
                suggestedDept.innerHTML = 'Could not analyze';
                suggestedDept.style.color = '#dc3545';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            suggestedDept.innerHTML = 'Error analyzing';
            suggestedDept.style.color = '#dc3545';
        });
    }
    
    titleInput.addEventListener('keyup', function() {
        clearTimeout(suggestionTimeout);
        suggestionTimeout = setTimeout(getDepartmentSuggestion, 800);
    });
    
    descriptionInput.addEventListener('keyup', function() {
        clearTimeout(suggestionTimeout);
        suggestionTimeout = setTimeout(getDepartmentSuggestion, 800);
    });
    
    function checkDuplicates() {
        const title = titleInput.value.trim();
        const description = descriptionInput.value.trim();
        
        if (title.length < 5 || description.length < 5) {
            if (duplicateAlert) duplicateAlert.style.display = 'none';
            return;
        }
        
        const checkString = title + '|' + description;
        if (checkString === lastCheck) return;
        lastCheck = checkString;
        
        if (checkBtn) {
            checkBtn.textContent = 'Checking...';
            checkBtn.disabled = true;
        }
        
        const formData = new FormData();
        formData.append('check_duplicate', '1');
        formData.append('title', title);
        formData.append('description', description);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success === false) {
                console.error('Server error:', data.error);
                alert('Error checking duplicates: ' + data.error);
                return;
            }
            
            const duplicates = data.data || data || [];
            
            if (duplicates.length > 0) {
                duplicateList.innerHTML = '';
                duplicates.forEach(complaint => {
                    const div = document.createElement('div');
                    div.className = 'duplicate-item';
                    div.innerHTML = `
                        <div style="font-weight: 600; color: #667eea; margin-bottom: 8px;">
                            <a href="view_complaint.php?id=${complaint.complaint_id}" target="_blank" style="color: #667eea; text-decoration: none;">
                                ${escapeHtml(complaint.title || 'Untitled')}
                            </a>
                        </div>
                        <div style="font-size: 13px; color: #666; margin-bottom: 8px;">
                            👤 ${escapeHtml(complaint.full_name || 'Unknown')} &nbsp; 📅 ${new Date(complaint.created_at).toLocaleDateString()} &nbsp; 📊 ${escapeHtml(complaint.status || 'Unknown')}
                        </div>
                        <button onclick="upvoteComplaint(${complaint.complaint_id})" style="background: #28a745; color: white; border: none; padding: 5px 15px; border-radius: 3px; cursor: pointer;">
                            👍 Upvote (${complaint.upvote_count || 0})
                        </button>
                    `;
                    duplicateList.appendChild(div);
                });
                duplicateAlert.style.display = 'block';
            } else {
                duplicateAlert.style.display = 'none';
                const msg = document.createElement('div');
                msg.style.cssText = 'background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin: 10px 0;';
                msg.textContent = `✓ No similar complaints found. Checked ${data.debug?.total_complaints || 'all'} complaints.`;
                document.querySelector('.form-container').insertBefore(msg, document.getElementById('complaintForm'));
                setTimeout(() => { if (msg.parentNode) msg.remove(); }, 3000);
            }
        })
        .catch(error => {
            console.error('Error checking duplicates:', error);
            if (duplicateAlert) duplicateAlert.style.display = 'none';
            alert('Error checking duplicates: ' + error.message);
        })
        .finally(() => {
            if (checkBtn) {
                checkBtn.textContent = 'Check for Duplicates';
                checkBtn.disabled = false;
            }
        });
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    if (titleInput && descriptionInput) {
        titleInput.addEventListener('keyup', function() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(checkDuplicates, 800);
        });
        descriptionInput.addEventListener('keyup', function() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(checkDuplicates, 800);
        });
    }
    
    if (checkBtn) {
        checkBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const title = titleInput ? titleInput.value.trim() : '';
            const description = descriptionInput ? descriptionInput.value.trim() : '';
            if (title.length < 5) {
                alert('Please enter a title with at least 5 characters');
                titleInput.focus();
                return;
            }
            if (description.length < 5) {
                alert('Please enter a description with at least 5 characters');
                descriptionInput.focus();
                return;
            }
            checkDuplicates();
        });
    }
    
    if (attachments) {
        attachments.addEventListener('change', function(e) {
            const preview = document.getElementById('attachmentPreview');
            if (!preview) return;
            preview.innerHTML = '';
            for (let file of this.files) {
                const div = document.createElement('div');
                div.className = 'attachment-item';
                div.textContent = `${file.name} (${(file.size/1024).toFixed(1)} KB)`;
                preview.appendChild(div);
            }
        });
    }
    
    function updatePriorityPreview() {
        if (!titleInput || !descriptionInput || !categorySelect || !priorityDisplay) return;
        const title = titleInput.value;
        const description = descriptionInput.value;
        const category = categorySelect.selectedOptions[0]?.text || '';
        let priority = 'medium';
        const urgentKeywords = ['urgent', 'emergency', 'critical', 'danger', 'safety', 'fire', 'accident', 'injured'];
        const text = (title + ' ' + description).toLowerCase();
        for (let keyword of urgentKeywords) {
            if (text.includes(keyword)) {
                priority = 'urgent';
                break;
            }
        }
        priorityDisplay.className = `priority-indicator priority-${priority}`;
        priorityDisplay.textContent = `Estimated Priority: ${priority.toUpperCase()}`;
        priorityDisplay.style.display = 'block';
    }
    
    if (titleInput && descriptionInput && categorySelect && priorityDisplay) {
        titleInput.addEventListener('input', updatePriorityPreview);
        descriptionInput.addEventListener('input', updatePriorityPreview);
        categorySelect.addEventListener('change', updatePriorityPreview);
    }
    
    if (titleInput.value.length > 0 || descriptionInput.value.length > 0) {
        setTimeout(getDepartmentSuggestion, 1000);
    }
});

function upvoteComplaint(complaintId) {
    const formData = new FormData();
    formData.append('upvote', '1');
    formData.append('complaint_id', complaintId);
    fetch('upvote_complaint.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Complaint upvoted! Redirecting...');
            window.location.href = 'view_complaint.php?id=' + complaintId;
        } else {
            alert(data.message || 'Could not upvote complaint');
        }
    })
    .catch(error => {
        console.error('Error upvoting:', error);
        alert('Error upvoting complaint');
    });
}