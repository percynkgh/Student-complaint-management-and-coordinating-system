-- Migration: Add Supervisor role
-- Run this once against your existing SCCSv2 database.

ALTER TABLE users MODIFY COLUMN role enum('student','class_rep','staff','supervisor','admin','dean','principal') DEFAULT 'student';
