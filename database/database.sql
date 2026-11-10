-- Create database
CREATE DATABASE IF NOT EXISTS student_complaint_system;
USE student_complaint_system;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'class_rep', 'staff', 'admin', 'dean', 'principal') DEFAULT 'student',
    department VARCHAR(100),
    course VARCHAR(100),
    year_of_study INT,
    phone VARCHAR(20),
    profile_pic VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Password reset tokens
CREATE TABLE password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Session table for security
CREATE TABLE user_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Departments table
CREATE TABLE departments (
    dept_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(100) UNIQUE NOT NULL,
    dept_code VARCHAR(20) UNIQUE NOT NULL,
    description TEXT,
    staff_count INT DEFAULT 0,
    avg_response_time DECIMAL(5,2) DEFAULT 0,
    satisfaction_score DECIMAL(3,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default departments
INSERT INTO departments (dept_name, dept_code, description) VALUES
('Information Technology', 'IT', 'Handles IT-related issues'),
('Maintenance', 'MNT', 'Handles physical infrastructure issues'),
('Finance', 'FIN', 'Handles financial and fee-related issues'),
('Registry', 'REG', 'Handles academic and administrative issues'),
('Student Affairs', 'SA', 'Handles student welfare issues');

-- Login attempts for security
CREATE TABLE login_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50),
    ip_address VARCHAR(45),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE
);

-- Add to database.sql or run separately
USE student_complaint_system;

-- Complaint categories/types
CREATE TABLE complaint_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    default_department VARCHAR(100), -- Which department handles this by default
    avg_resolution_time INT DEFAULT 0, -- In hours
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO complaint_categories (category_name, description, default_department) VALUES
('IT Issues', 'Computer, internet, software, printer problems', 'Information Technology'),
('Maintenance', 'Building, furniture, electricity, plumbing issues', 'Maintenance'),
('Financial', 'Fee payments, refunds, financial aid', 'Finance'),
('Academic', 'Registration, transcripts, course issues', 'Registry'),
('Facilities', 'Classrooms, labs, library, sports facilities', 'Maintenance'),
('Security', 'Campus security, safety concerns', 'Student Affairs'),
('Harassment', 'Bullying, discrimination, harassment reports', 'Student Affairs'),
('Transport', 'Shuttle, parking, transport services', 'Maintenance');

-- Main complaints table
CREATE TABLE complaints (
    complaint_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_number VARCHAR(50) UNIQUE NOT NULL, -- Format: CMP-YYYY-XXXX
    user_id INT NOT NULL,
    category_id INT,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('submitted', 'under_review', 'in_progress', 'resolved', 'closed', 'rejected') DEFAULT 'submitted',
    department_assigned VARCHAR(100),
    assigned_to INT, -- staff user_id
    location_building VARCHAR(100),
    location_room VARCHAR(50),
    location_details TEXT,
    is_duplicate BOOLEAN DEFAULT FALSE,
    duplicate_of INT, -- complaint_id of original
    upvote_count INT DEFAULT 0,
    affected_count INT DEFAULT 1, -- Number of students affected
    is_batch_complaint BOOLEAN DEFAULT FALSE,
    batch_token VARCHAR(100), -- For group complaints
    attachment_path TEXT,
    estimated_resolution_date DATETIME,
    resolved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES complaint_categories(category_id),
    FOREIGN KEY (assigned_to) REFERENCES users(user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_department (department_assigned)
);

-- Complaint attachments
CREATE TABLE complaint_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    file_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE
);

-- Complaint updates/timeline
CREATE TABLE complaint_timeline (
    timeline_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    status VARCHAR(50),
    description TEXT,
    updated_by INT, -- user_id who made the update
    is_auto_update BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(user_id)
);

-- Complaint upvotes (for students to support complaints)
CREATE TABLE complaint_upvotes (
    upvote_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_upvote (complaint_id, user_id),
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Batch complaint members (students affected in group complaints)
CREATE TABLE batch_members (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    user_id INT NOT NULL,
    endorsement_status ENUM('pending', 'confirmed', 'denied') DEFAULT 'pending',
    endorsed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_member (complaint_id, user_id),
    FOREIGN KEY (complaint_id) REFERENCES complaints(complaint_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Smart duplicate detection log
CREATE TABLE duplicate_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    new_complaint_id INT,
    existing_complaint_id INT,
    similarity_score DECIMAL(5,2),
    matched_keywords TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Update users table to include class info for batch complaints
ALTER TABLE users ADD COLUMN class_code VARCHAR(50) AFTER course;
ALTER TABLE users ADD COLUMN batch_year INT AFTER year_of_study;