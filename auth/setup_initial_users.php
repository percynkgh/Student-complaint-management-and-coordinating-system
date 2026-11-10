<?php
//  Run this once to create initial admin/staff accounts
require_once 'config/database.php';
require_once 'config/security.php';

$database = new Database();
$db = $database->getConnection();

// Function to create user if not exists
function createUser($db, $student_id, $email, $password, $full_name, $role, $department = null) {
    // Check if user exists
    $check = "SELECT user_id FROM users WHERE student_id = ? OR email = ?";
    $stmt = $db->prepare($check);
    $stmt->execute([$student_id, $email]);
    
    if ($stmt->rowCount() == 0) {
        $hashed_password = Security::hashPassword($password);
        
        $query = "INSERT INTO users (student_id, email, password, full_name, role, department, is_active) 
                  VALUES (?, ?, ?, ?, ?, ?, 1)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$student_id, $email, $hashed_password, $full_name, $role, $department])) {
            echo "Created $role account: $full_name ($student_id)<br>";
        } else {
            echo "Failed to create $role account: $full_name<br>";
        }
    } else {
        echo "$role account already exists: $full_name ($student_id)<br>";
    }
}

// Create Admin accounts
createUser($db, 'ADMIN001', 'admin@university.edu', 'Admin@123', 'System Administrator', 'admin');
createUser($db, 'DEAN001', 'dean@university.edu', 'Dean@123', 'Professor John Smith', 'dean', 'Engineering');
createUser($db, 'PRINCIPAL001', 'principal@university.edu', 'Principal@123', 'Dr. Sarah Johnson', 'principal');

// Create Department Staff accounts
$staff_accounts = [
    ['STAFFIT01', 'it.staff@university.edu', 'IT@123', 'James Wilson', 'staff', 'Information Technology'],
    ['STAFFMNT01', 'maintenance@university.edu', 'MNT@123', 'Robert Brown', 'staff', 'Maintenance'],
    ['STAFFFIN01', 'finance@university.edu', 'FIN@123', 'Elizabeth Davis', 'staff', 'Finance'],
    ['STAFFREG01', 'registry@university.edu', 'REG@123', 'Michael Taylor', 'staff', 'Registry'],
    ['STAFFSA01', 'student.affairs@university.edu', 'SA@123', 'Patricia Martinez', 'staff', 'Student Affairs'],
];

foreach ($staff_accounts as $account) {
    createUser($db, $account[0], $account[1], $account[2], $account[3], $account[4], $account[5]);
}

// Create Class Representatives (usually students with special role)
$classrep_accounts = [
    ['CLASS001', 'rep.cs@university.edu', 'Class@123', 'David Lee', 'class_rep', 'Computer Science'],
    ['CLASS002', 'rep.eng@university.edu', 'Class@123', 'Lisa Wang', 'class_rep', 'Engineering'],
];

foreach ($classrep_accounts as $account) {
    createUser($db, $account[0], $account[1], $account[2], $account[3], $account[4], $account[5]);
}

echo "<br><strong>Setup complete!</strong><br>";
echo "You can now login with these credentials:<br>";
echo "Admin: ADMIN001 / Admin@123<br>";
echo "Dean: DEAN001 / Dean@123<br>";
echo "Staff: STAFFIT01 / IT@123<br>";
echo "Class Rep: CLASS001 / Class@123<br>";
echo "Student: Register via registration form<br>";
?>