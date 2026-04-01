-- Create Yearly Closings Table
-- This table stores annual financial closing records
CREATE TABLE IF NOT EXISTS yearly_closings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    closing_year YEAR NOT NULL,
    closing_date DATE NOT NULL,
    total_revenue DECIMAL(15, 2) DEFAULT 0.00,
    total_expenses DECIMAL(15, 2) DEFAULT 0.00,
    total_profit DECIMAL(15, 2) DEFAULT 0.00,
    total_assets DECIMAL(15, 2) DEFAULT 0.00,
    total_liabilities DECIMAL(15, 2) DEFAULT 0.00,
    net_worth DECIMAL(15, 2) DEFAULT 0.00,
    bank_balance DECIMAL(15, 2) DEFAULT 0.00,
    cash_balance DECIMAL(15, 2) DEFAULT 0.00,
    notes TEXT,
    status ENUM('draft', 'finalized', 'audited') DEFAULT 'draft',
    closed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_year (closing_year),
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE
    SET NULL,
        INDEX idx_status (status),
        INDEX idx_year (closing_year)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;