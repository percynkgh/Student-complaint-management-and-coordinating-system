
let timeoutId, suggestionTimeout, lastCheck = '';
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

function getDepartmentSuggestion() {
    const title = titleInput.value.trim();
    const description = descriptionInput.value.trim();
    if (title.length < 5 || description.length < 10) { suggestionBox.style.display = 'none'; return; }
    suggestionBox.style.display = 'block';
    suggestedDept.innerHTML = '<span class="loading-dots">Analyzing</span>';
    suggestedDept.style.color = '#666';
    const formData = new FormData();
    formData.append('get_suggestion', '1');
    formData.append('title', title);
    formData.append('description', description);
    fetch('get_department_suggestion.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                suggestedDept.innerHTML = data.department;
                suggestedDept.style.color = '#28a745';
                for (let option of departmentSelect.options) {
                    if (option.value === data.department) { option.selected = true; break; }
                }
            } else {
                suggestedDept.innerHTML = 'Could not analyze';
                suggestedDept.style.color = '#dc3545';
            }
        })
        .catch(() => { suggestedDept.innerHTML = 'Error analyzing'; suggestedDept.style.color = '#dc3545'; });
}

function checkDuplicates() {
    const title = titleInput.value.trim();
    const description = descriptionInput.value.trim();
    if (title.length < 5 || description.length < 5) { duplicateAlert.style.display = 'none'; return; }
    const checkString = title + '|' + description;
    if (checkString === lastCheck) return;
    lastCheck = checkString;
    if (checkBtn) { checkBtn.textContent = 'Checking...'; checkBtn.disabled = true; }
    const formData = new FormData();
    formData.append('check_duplicate', '1');
    formData.append('title', title);
    formData.append('description', description);
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.length > 0) {
                duplicateList.innerHTML = '';
                data.data.forEach(complaint => {
                    const div = document.createElement('div');
                    div.className = 'duplicate-item';
                    div.innerHTML = `<div style="font-weight:600;color:#667eea;"><a href="../student/view_complaint.php?id=${complaint.complaint_id}" target="_blank">${escapeHtml(complaint.title)}</a></div><div style="font-size:12px;color:#666;">${escapeHtml(complaint.full_name)} • ${new Date(complaint.created_at).toLocaleDateString()}</div>`;
                    duplicateList.appendChild(div);
                });
                duplicateAlert.style.display = 'block';
            } else { duplicateAlert.style.display = 'none'; }
        })
        .catch(() => duplicateAlert.style.display = 'none')
        .finally(() => { if (checkBtn) { checkBtn.textContent = 'Check for Duplicates'; checkBtn.disabled = false; } });
}

function updateAttachmentPreview() {
    const preview = document.getElementById('attachmentPreview');
    if (!preview) return;
    preview.innerHTML = '';
    for (let file of attachments.files) {
        const div = document.createElement('div');
        div.className = 'attachment-item';
        div.textContent = `${file.name} (${(file.size/1024).toFixed(1)} KB)`;
        preview.appendChild(div);
    }
}

function updatePriorityPreview() {
    if (!titleInput || !descriptionInput || !categorySelect || !priorityDisplay) return;
    const text = (titleInput.value + ' ' + descriptionInput.value).toLowerCase();
    const urgentKeywords = ['urgent', 'emergency', 'critical', 'danger', 'safety', 'fire', 'accident', 'injured'];
    let priority = 'medium';
    if (urgentKeywords.some(kw => text.includes(kw))) priority = 'urgent';
    priorityDisplay.className = `priority-indicator priority-${priority}`;
    priorityDisplay.textContent = `Estimated Priority: ${priority.toUpperCase()}`;
    priorityDisplay.style.display = 'block';
}

document.addEventListener('DOMContentLoaded', function() {
    if (titleInput && descriptionInput) {
        titleInput.addEventListener('keyup', function() {
            clearTimeout(timeoutId); timeoutId = setTimeout(checkDuplicates, 800);
            clearTimeout(suggestionTimeout); suggestionTimeout = setTimeout(getDepartmentSuggestion, 800);
        });
        descriptionInput.addEventListener('keyup', function() {
            clearTimeout(timeoutId); timeoutId = setTimeout(checkDuplicates, 800);
            clearTimeout(suggestionTimeout); suggestionTimeout = setTimeout(getDepartmentSuggestion, 800);
        });
        titleInput.addEventListener('input', updatePriorityPreview);
        descriptionInput.addEventListener('input', updatePriorityPreview);
        categorySelect.addEventListener('change', updatePriorityPreview);
    }
    if (checkBtn) checkBtn.addEventListener('click', function(e) { e.preventDefault(); checkDuplicates(); });
    if (attachments) attachments.addEventListener('change', updateAttachmentPreview);
});