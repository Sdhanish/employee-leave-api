-- ============================================================
-- database.sql
-- Employee Leave Request & Balance Tracker
-- MySQL schema + sample data
-- ============================================================

-- Create and select the database
CREATE DATABASE IF NOT EXISTS employee_leave_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE employee_leave_db;

-- ============================================================
-- Table: employees
-- ============================================================
CREATE TABLE IF NOT EXISTS employees (
    id                  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name                VARCHAR(150)    NOT NULL,
    annual_leave_balance INT            NOT NULL DEFAULT 0,  -- days remaining
    sick_leave_balance   INT            NOT NULL DEFAULT 0,
    casual_leave_balance INT            NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: leave_requests
-- ============================================================
CREATE TABLE IF NOT EXISTS leave_requests (
    id          INT UNSIGNED        NOT NULL AUTO_INCREMENT,
    employee_id INT UNSIGNED        NOT NULL,
    leave_type  ENUM('annual','sick','casual') NOT NULL,
    start_date  DATE                NOT NULL,
    end_date    DATE                NOT NULL,
    status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    -- Foreign key keeps referential integrity
    CONSTRAINT fk_leave_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index speeds up the overlap-check query
CREATE INDEX idx_leave_employee_dates
    ON leave_requests (employee_id, start_date, end_date, status);

-- ============================================================
-- Sample employees
-- ============================================================
INSERT INTO employees (name, annual_leave_balance, sick_leave_balance, casual_leave_balance) VALUES
    ('Alice Johnson',  15, 10, 7),
    ('Bob Smith',      10,  8, 5),
    ('Carol Williams',  5,  6, 3);
