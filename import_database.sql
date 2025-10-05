-- Create database
CREATE DATABASE financial_dashboard;
USE financial_dashboard;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Chart of Accounts
CREATE TABLE chart_of_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(20) UNIQUE NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_type ENUM('Asset', 'Liability', 'Equity', 'Revenue', 'Expense') NOT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Disbursement Requests
CREATE TABLE disbursement_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(20) UNIQUE NOT NULL,
    requested_by VARCHAR(100) NOT NULL,
    department VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    status ENUM('Pending', 'Approved', 'Rejected', 'Completed') DEFAULT 'Pending',
    date_requested DATE NOT NULL,
    date_approved DATE,
    approved_by VARCHAR(100),
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Journal Entries
CREATE TABLE journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_id VARCHAR(20) UNIQUE NOT NULL,
    entry_date DATE NOT NULL,
    description TEXT NOT NULL,
    status ENUM('Draft', 'Posted') DEFAULT 'Draft',
    created_by VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Journal Entry Lines
CREATE TABLE journal_entry_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT,
    account_id INT,
    debit DECIMAL(15,2) DEFAULT 0.00,
    credit DECIMAL(15,2) DEFAULT 0.00,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
);

-- Vendors/Customers
CREATE TABLE business_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    type ENUM('Vendor', 'Customer') NOT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Invoices
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20) UNIQUE NOT NULL,
    contact_id INT,
    type ENUM('Receivable', 'Payable') NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    status ENUM('Pending', 'Paid', 'Overdue', 'Cancelled') DEFAULT 'Pending',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES business_contacts(id)
);

-- Payments
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(20) UNIQUE NOT NULL,
    contact_id INT,
    invoice_id INT,
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    payment_method ENUM('Cash', 'Check', 'Bank Transfer', 'Credit Card') NOT NULL,
    type ENUM('Receive', 'Make') NOT NULL,
    status ENUM('Completed', 'Processing', 'Scheduled', 'Cancelled') DEFAULT 'Processing',
    reference_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES business_contacts(id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
);

-- Budget Proposals
CREATE TABLE budget_proposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id VARCHAR(20) UNIQUE NOT NULL,
    department VARCHAR(50) NOT NULL,
    fiscal_year YEAR NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    submitted_by VARCHAR(100) NOT NULL,
    submission_date DATE NOT NULL,
    status ENUM('Draft', 'Under Review', 'Approved', 'Rejected') DEFAULT 'Draft',
    current_stage VARCHAR(50),
    next_approver VARCHAR(100),
    deadline DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Budget Categories
CREATE TABLE budget_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proposal_id INT,
    category_name VARCHAR(100) NOT NULL,
    budget_amount DECIMAL(15,2) NOT NULL,
    actual_amount DECIMAL(15,2) DEFAULT 0.00,
    FOREIGN KEY (proposal_id) REFERENCES budget_proposals(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert sample data
INSERT INTO users (username, password, name, role, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ralf', 'admin', 'ralf@company.com'),
('user1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Smith', 'user', 'john@company.com');

INSERT INTO chart_of_accounts (account_code, account_name, account_type, balance) VALUES
('1001', 'Cash on Hand', 'Asset', 125430.00),
('1002', 'Bank Account', 'Asset', 458720.00),
('2001', 'Accounts Payable', 'Liability', 85300.00),
('3001', 'Owner''s Equity', 'Equity', 500000.00),
('4001', 'Service Revenue', 'Revenue', 285600.00),
('5001', 'Salaries Expense', 'Expense', 125800.00);

INSERT INTO business_contacts (contact_id, name, contact_person, phone, email, type, balance) VALUES
('V-001', 'XYZ Supplies Inc.', 'Michael Tan', '+63 912 345 6789', 'michael@xyzsupplies.com', 'Vendor', 12500.00),
('V-002', 'ABC Office Solutions', 'Sarah Lim', '+63 917 654 3210', 'sarah@abcoffice.com', 'Vendor', 8750.00),
('C-001', 'Global Tech Solutions', 'James Wilson', '+63 918 765 4321', 'james@globaltech.com', 'Customer', 25000.00),
('C-002', 'Innovate Marketing Agency', 'Lisa Garcia', '+63 919 876 5432', 'lisa@innovatemktg.com', 'Customer', 15750.00);