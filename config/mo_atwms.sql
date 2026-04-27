-- MO-ATWMS Database Schema
-- Import this file in phpMyAdmin or use: mysql -u root < mo_atwms.sql

CREATE DATABASE IF NOT EXISTS mo_atwms DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mo_atwms;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Super Admin', 'Department Staff', 'Administrative Assistant', 'Mayor', 'Record Officer') NOT NULL DEFAULT 'Department Staff',
    position VARCHAR(100),
    office_department VARCHAR(150),
    civil_status VARCHAR(50),
    date_of_birth DATE,
    contact_number VARCHAR(20),
    house_no VARCHAR(100),
    street VARCHAR(150),
    barangay VARCHAR(100),
    municipality VARCHAR(100),
    province VARCHAR(100),
    status ENUM('Pending', 'Active', 'Inactive') DEFAULT 'Pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Trail Table
CREATE TABLE IF NOT EXISTS audit_trail (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login Attempts Table
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100),
    username VARCHAR(100),
    success BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(50),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Sessions Table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents Table
CREATE TABLE IF NOT EXISTS documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document Assignments Table
CREATE TABLE IF NOT EXISTS document_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_to INT NOT NULL,
    office_department VARCHAR(150),
    notes TEXT,
    status ENUM('Pending', 'Received', 'Checking Documents', 'Waiting For Approval by Mayor', 'In Progress', 'Completed', 'Returned') DEFAULT 'Pending',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_document_id (document_id),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_status (status),
    INDEX idx_assigned_at (assigned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Default Super Admin (Password: admin123)
INSERT INTO users (first_name, last_name, email, username, password, role, office_department, status, approved_by, approved_at) 
VALUES ('System', 'Administrator', 'admin@lgumeceedes.gov.ph', 'admin', '$2y$10$YIjlrNM.0.1L5lVVKvLU.eKBZdQwFAjlhVQ1xKVjLmYqw1fJZvvtm', 'Super Admin', 'Mayor\'s Office', 'Active', 1, NOW());

-- Insert Sample Department Staff User (Password: staff123)
INSERT INTO users (first_name, last_name, email, username, password, role, office_department, status, approved_by, approved_at) 
VALUES ('Juan', 'Dela Cruz', 'juan@lgumeceedes.gov.ph', 'juan_staff', '$2y$10$GJEYyFp7JMpfRzpKq5t2wu7nrYfHKN7Z9p8zRfKq0U6mXxL8z9J1C', 'Department Staff', 'Administrative Office', 'Active', 1, NOW());

-- Insert Sample Mayor User (Password: mayor123)
INSERT INTO users (first_name, last_name, email, username, password, role, office_department, status, approved_by, approved_at) 
VALUES ('Maria', 'Garcia', 'maria.garcia@lgumeceedes.gov.ph', 'mayor_maria', '$2y$10$8k9L2pQrTvWxYzAbCdEfGe.7sH6iJkLmNoPqRsTuVwXyZaBcDeFgH', 'Mayor', 'Mayor\'s Office', 'Active', 1, NOW());
