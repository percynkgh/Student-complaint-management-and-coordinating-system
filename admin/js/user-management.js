// MODAL FUNCTIONS (Create User)

/* Open the Create User modal*/
function openCreateUserModal() {
  const modal = document.getElementById("createUserModal");
  if (modal) {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }
}

/* Close the Create User modal*/
function closeCreateUserModal() {
  const modal = document.getElementById("createUserModal");
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }

  // Reset form fields
  const form = document.getElementById("createUserForm");
  if (form) {
    form.reset();
  }

  // Hide all role fields
  document.querySelectorAll(".role-fields").forEach((field) => {
    field.style.display = "none";
  });
}

// ROLE-BASED FIELD TOGGLING (Create User Form)

/*Show/hide fields based on selected role*/
function toggleRoleFields() {
  const roleSelect = document.getElementById("roleSelect");
  if (!roleSelect) return;

  const role = roleSelect.value;

  // Hide all role-specific fields
  document.querySelectorAll(".role-fields").forEach((field) => {
    field.style.display = "none";
  });

  // Get all department and ID fields
  const studentIdField = document.getElementById("student_id_field");
  const staffIdField = document.getElementById("staff_id_field");
  const adminIdField = document.getElementById("admin_id_field");
  const studentDeptField = document.getElementById("student_dept_field");
  const staffDeptField = document.getElementById("staff_dept_field");
  const adminDeptField = document.getElementById("admin_dept_field");

  // REMOVE required attribute from ALL role-specific fields first
  if (studentIdField) studentIdField.required = false;
  if (staffIdField) staffIdField.required = false;
  if (adminIdField) adminIdField.required = false;
  if (studentDeptField) studentDeptField.required = false;
  if (staffDeptField) staffDeptField.required = false;
  if (adminDeptField) adminDeptField.required = false;

  // Show and set required for relevant fields based on role
  if (role === "student" || role === "class_rep") {
    const studentFields = document.getElementById("student-fields");
    if (studentFields) {
      studentFields.style.display = "block";
      if (studentIdField) studentIdField.required = true;
      if (studentDeptField) studentDeptField.required = true;
    }
  } else if (role === "staff") {
    const staffFields = document.getElementById("staff-fields");
    if (staffFields) {
      staffFields.style.display = "block";
      if (staffIdField) staffIdField.required = true;
      if (staffDeptField) staffDeptField.required = true;
    }
  } else if (role === "admin" || role === "dean" || role === "principal") {
    const adminFields = document.getElementById("admin-fields");
    if (adminFields) {
      adminFields.style.display = "block";
      if (adminIdField) adminIdField.required = true;
      // Admin department is optional
    }
  }
}

// FORM VALIDATION (Create User)

/* Validate the create user form before submission*/
function validateUserForm() {
  const roleSelect = document.getElementById("roleSelect");
  if (!roleSelect) return true;

  const role = roleSelect.value;

  if (!role) {
    showNotification("Please select a role", "error");
    return false;
  }

  // Validate common required fields
  const fullName = document.querySelector('input[name="full_name"]');
  const email = document.querySelector('input[name="email"]');

  if (!fullName || !fullName.value.trim()) {
    showNotification("Full name is required", "error");
    return false;
  }

  if (!email || !email.value.trim()) {
    showNotification("Email address is required", "error");
    return false;
  }

  // Validate email format
  if (email && email.value.trim()) {
    if (!isValidEmail(email.value.trim())) {
      showNotification("Please enter a valid email address", "error");
      return false;
    }
  }

  // Validate based on role
  if (role === "student" || role === "class_rep") {
    const studentId = document.getElementById("student_id_field");
    if (!studentId || !studentId.value.trim()) {
      showNotification("Student ID is required", "error");
      return false;
    }

    const studentIdRegex = /^[A-Z0-9]{6,15}$/i;
    if (!studentIdRegex.test(studentId.value.trim())) {
      showNotification(
        "Student ID must be 6-15 characters with only letters and numbers",
        "error"
      );
      return false;
    }

    // Validate Academic Department
    const academicDept = document.getElementById("student_dept_field");
    if (!academicDept || !academicDept.value) {
      showNotification("Please select an academic department", "error");
      return false;
    }
  }

  if (role === "staff") {
    const staffId = document.getElementById("staff_id_field");
    const department = document.getElementById("staff_dept_field");

    if (!staffId || !staffId.value.trim()) {
      showNotification("Staff ID is required", "error");
      return false;
    }

    if (!department || !department.value) {
      showNotification("Department is required for staff", "error");
      return false;
    }
  }

  if (role === "admin" || role === "dean" || role === "principal") {
    const adminId = document.getElementById("admin_id_field");
    if (!adminId || !adminId.value.trim()) {
      showNotification("Admin ID is required", "error");
      return false;
    }
  }

  return true;
}

// BULK ACTIONS (Select All, Update Count)

/* Select or deselect all user checkboxes*/
function selectAllUsers() {
  const selectAllCheckbox = document.getElementById("selectAll");
  if (!selectAllCheckbox) return;

  const checkboxes = document.querySelectorAll(".user-checkbox");
  const isChecked = selectAllCheckbox.checked;

  checkboxes.forEach((cb) => {
    cb.checked = isChecked;
  });

  updateSelectedCount();
}

/* Update the selected count display*/
function updateSelectedCount() {
  const checkboxes = document.querySelectorAll(".user-checkbox:checked");
  const count = checkboxes.length;
  const counter = document.getElementById("selectedCount");

  if (counter) {
    counter.textContent = count + " selected";
    counter.style.display = count > 0 ? "inline-block" : "none";
  }

  // Enable/disable bulk action button
  const bulkActionBtn = document.getElementById("applyBulkAction");
  if (bulkActionBtn) {
    bulkActionBtn.disabled = count === 0;
  }
}

/* Perform bulk action on selected users*/
function performBulkAction() {
  const actionSelect = document.getElementById("bulkAction");
  const selected = document.querySelectorAll(".user-checkbox:checked");

  if (selected.length === 0) {
    showNotification("Please select at least one user", "error");
    return;
  }

  if (!actionSelect || !actionSelect.value) {
    showNotification("Please select an action", "error");
    return;
  }

  const action = actionSelect.value;

  if (action === "delete") {
    confirmAction(
      `Are you sure you want to delete ${selected.length} user(s)? This cannot be undone.`,
      () => {
        document.getElementById("bulkForm").submit();
      }
    );
  } else {
    document.getElementById("bulkForm").submit();
  }
}

// SEARCH AND FILTER

/*Filter users table based on search term and filters*/
function filterUsers() {
  const searchInput = document.getElementById("userSearch");
  const roleFilter = document.getElementById("roleFilter");
  const statusFilter = document.getElementById("statusFilter");

  const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
  const roleValue = roleFilter ? roleFilter.value : "all";
  const statusValue = statusFilter ? statusFilter.value : "all";

  const rows = document.querySelectorAll("#usersTable tbody tr");

  rows.forEach((row) => {
    let show = true;

    // Search by name or email
    if (searchTerm) {
      const nameCell = row.cells[0];
      const emailCell = row.cells[2];
      const name = nameCell ? nameCell.textContent.toLowerCase() : "";
      const email = emailCell ? emailCell.textContent.toLowerCase() : "";

      if (!name.includes(searchTerm) && !email.includes(searchTerm)) {
        show = false;
      }
    }

    // Filter by role
    if (show && roleValue !== "all") {
      const roleCell = row.cells[1];
      const role = roleCell ? roleCell.textContent.toLowerCase() : "";
      if (!role.includes(roleValue.toLowerCase())) {
        show = false;
      }
    }

    // Filter by status
    if (show && statusValue !== "all") {
      const statusCell = row.cells[5];
      const status = statusCell ? statusCell.textContent.toLowerCase() : "";
      const isActive = status === "active";

      if (statusValue === "active" && !isActive) {
        show = false;
      } else if (statusValue === "inactive" && isActive) {
        show = false;
      }
    }

    row.style.display = show ? "" : "none";
  });

  // Update visible count
  const visibleRows = document.querySelectorAll(
    '#usersTable tbody tr[style=""]'
  );
  const countDisplay = document.getElementById("filteredCount");
  if (countDisplay) {
    countDisplay.textContent = visibleRows.length;
  }
}

// USER DETAILS PAGE FUNCTIONS

/*Toggle user status from details page*/
function toggleUserStatus(userId, currentStatus) {
  const newStatus = currentStatus === "active" ? "inactive" : "active";
  const action = newStatus === "active" ? "activate" : "deactivate";

  confirmAction(`Are you sure you want to ${action} this user?`, () => {
    window.location.href = `?toggle_status=${userId}`;
  });
}

/* Delete user from details page*/
function deleteUser(userId, userName) {
  confirmAction(
    `Are you sure you want to permanently delete ${userName}? This cannot be undone.`,
    () => {
      window.location.href = `?delete=${userId}`;
    }
  );
}

/* Reset user password from details page*/
function resetUserPassword(userId) {
  confirmAction(`Reset password to "Welcome123" for this user?`, () => {
    window.location.href = `?reset_password=${userId}`;
  });
}

// EDIT USER PAGE FUNCTIONS

/*Validate edit user form*/
function validateEditUserForm() {
  const email = document.querySelector('input[name="email"]');
  const fullName = document.querySelector('input[name="full_name"]');

  if (!fullName || !fullName.value.trim()) {
    showNotification("Full name is required", "error");
    return false;
  }

  if (email && email.value.trim()) {
    if (!isValidEmail(email.value.trim())) {
      showNotification("Please enter a valid email address", "error");
      return false;
    }
  }

  return true;
}

// RESET PASSWORD PAGE FUNCTIONS

/* Validate reset password form*/
function validateResetPasswordForm() {
  const newPassword = document.querySelector('input[name="new_password"]');
  const confirmPassword = document.querySelector(
    'input[name="confirm_password"]'
  );

  if (!newPassword || !newPassword.value) {
    showNotification("Please enter a new password", "error");
    return false;
  }

  if (newPassword.value.length < 6) {
    showNotification("Password must be at least 6 characters", "error");
    return false;
  }

  if (newPassword.value !== confirmPassword.value) {
    showNotification("Passwords do not match", "error");
    return false;
  }

  return true;
}

/* Generate and fill a strong password*/
function fillStrongPassword() {
  const strongPassword = generateStrongPassword();
  const newPasswordInput = document.querySelector('input[name="new_password"]');
  const confirmInput = document.querySelector('input[name="confirm_password"]');

  if (newPasswordInput) newPasswordInput.value = strongPassword;
  if (confirmInput) confirmInput.value = strongPassword;

  showNotification("Strong password generated!", "success");
}

// INITIALIZATION

document.addEventListener("DOMContentLoaded", function () {
  console.log("User Management JS loaded");

  // Setup Create User form validation
  const createForm = document.getElementById("createUserForm");
  if (createForm) {
    createForm.addEventListener("submit", function (e) {
      if (!validateUserForm()) {
        e.preventDefault();
      }
    });
  }

  // Setup Edit User form validation
  const editForm = document.getElementById("editUserForm");
  if (editForm) {
    editForm.addEventListener("submit", function (e) {
      if (!validateEditUserForm()) {
        e.preventDefault();
      }
    });
  }

  // Setup Reset Password form validation
  const resetForm = document.getElementById("resetPasswordForm");
  if (resetForm) {
    resetForm.addEventListener("submit", function (e) {
      if (!validateResetPasswordForm()) {
        e.preventDefault();
      }
    });
  }

  // Setup role select change event
  const roleSelect = document.getElementById("roleSelect");
  if (roleSelect) {
    roleSelect.addEventListener("change", toggleRoleFields);
    toggleRoleFields(); // Initial call
  }

  // Setup select all checkbox
  const selectAllCheckbox = document.getElementById("selectAll");
  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener("change", selectAllUsers);
  }

  // Setup user checkboxes to update count
  const userCheckboxes = document.querySelectorAll(".user-checkbox");
  userCheckboxes.forEach((cb) => {
    cb.addEventListener("change", updateSelectedCount);
  });

  // Setup search input
  const searchInput = document.getElementById("userSearch");
  if (searchInput) {
    searchInput.addEventListener("keyup", filterUsers);
  }

  // Setup filter dropdowns
  const roleFilter = document.getElementById("roleFilter");
  const statusFilter = document.getElementById("statusFilter");

  if (roleFilter) roleFilter.addEventListener("change", filterUsers);
  if (statusFilter) statusFilter.addEventListener("change", filterUsers);

  // Initial count update
  updateSelectedCount();

  // Close modal when clicking outside
  window.onclick = function (event) {
    const modal = document.getElementById("createUserModal");
    if (event.target === modal) {
      closeCreateUserModal();
    }
  };
});
