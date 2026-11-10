
function toggleAll(source) {
    const checkboxes = document.getElementsByClassName('student-checkbox');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = source.checked;
    }
    updateSelectedCount();
}
function updateSelectedCount() {
    const checkboxes = document.getElementsByClassName('student-checkbox');
    let count = 0;
    for (let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) count++;
    }
    const selectedSpan = document.getElementById('selectedCount');
    if (selectedSpan) selectedSpan.textContent = count + ' selected';
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) submitBtn.disabled = (count === 0);
}
function updateFileList(input) {
    const fileList = document.getElementById('fileList');
    if (!fileList) return;
    fileList.innerHTML = '';
    for (let i = 0; i < input.files.length; i++) {
        const file = input.files[i];
        const fileSize = (file.size / 1024).toFixed(1);
        const div = document.createElement('div');
        div.className = 'file-item';
        div.innerHTML = `<span>${escapeHtml(file.name)}</span><small>${fileSize} KB</small>`;
        fileList.appendChild(div);
    }
}
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.addEventListener('change', function() { toggleAll(this); });
    const fileInput = document.getElementById('fileInput');
    if (fileInput) fileInput.addEventListener('change', function() { updateFileList(this); });
    const form = document.getElementById('complaintForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const checked = document.querySelectorAll('.student-checkbox:checked').length;
            if (checked === 0) {
                e.preventDefault();
                alert('Please select at least one affected student.');
            }
        });
    }
});