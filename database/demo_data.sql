-- ============================================
-- DEMO DATA INSERTION SCRIPT
-- Creates 5 sample records for each major table
-- ============================================
-- 1. CATEGORIES (Product Categories)
INSERT IGNORE INTO `categories` (`name`, `description`, `created_at`)
VALUES (
        'Electronics',
        'Electronic devices and accessories',
        NOW()
    ),
    ('Furniture', 'Office and home furniture', NOW()),
    ('Clothing', 'Apparel and fashion items', NOW()),
    (
        'Food & Beverage',
        'Food and drink products',
        NOW()
    ),
    (
        'Office Supplies',
        'Office equipment and supplies',
        NOW()
    );
-- 2. BRANDS
INSERT IGNORE INTO `brands` (`name`, `description`, `created_at`)
VALUES (
        'Samsung',
        'Korean electronics manufacturer',
        NOW()
    ),
    ('Apple', 'Technology and innovation', NOW()),
    ('Nike', 'Sports and athletic wear', NOW()),
    ('IKEA', 'Furniture and home accessories', NOW()),
    ('Coca-Cola', 'Beverage company', NOW());
-- 3. UNITS
INSERT IGNORE INTO `units` (`name`, `short_name`, `created_at`)
VALUES ('Piece', 'pc', NOW()),
    ('Kilogram', 'kg', NOW()),
    ('Liter', 'L', NOW()),
    ('Box', 'box', NOW()),
    ('Dozen', 'dz', NOW());
-- 4. CUSTOMERS
INSERT IGNORE INTO `customers` (
        `name`,
        `phone`,
        `email`,
        `address`,
        `customer_group`,
        `credit_limit`,
        `opening_balance`,
        `current_balance`,
        `status`,
        `created_at`
    )
VALUES (
        'John Doe',
        '01712345678',
        'john@example.com',
        '123 Main St, Dhaka',
        'Buyer',
        50000.00,
        0.00,
        0.00,
        'active',
        NOW()
    ),
    (
        'Jane Smith',
        '01812345679',
        'jane@example.com',
        '456 Park Ave, Chittagong',
        'Corporate',
        100000.00,
        5000.00,
        5000.00,
        'active',
        NOW()
    ),
    (
        'Bob Johnson',
        '01912345680',
        'bob@example.com',
        '789 Oak Rd, Sylhet',
        'Buyer',
        30000.00,
        0.00,
        0.00,
        'active',
        NOW()
    ),
    (
        'Alice Brown',
        '01612345681',
        'alice@example.com',
        '321 Elm St, Rajshahi',
        'Vendor',
        75000.00,
        -2000.00,
        -2000.00,
        'active',
        NOW()
    ),
    (
        'Charlie Wilson',
        '01512345682',
        'charlie@example.com',
        '654 Pine Ave, Khulna',
        'Buyer',
        40000.00,
        1000.00,
        1000.00,
        'active',
        NOW()
    );
-- 5. SUPPLIERS
INSERT IGNORE INTO `suppliers` (
        `name`,
        `phone`,
        `email`,
        `address`,
        `opening_balance`,
        `current_balance`,
        `payment_terms`,
        `status`,
        `created_at`
    )
VALUES (
        'Tech Supplies Ltd',
        '02-9876543',
        'tech@supplier.com',
        'Gulshan, Dhaka',
        0.00,
        0.00,
        'Net 30 days',
        'active',
        NOW()
    ),
    (
        'Fashion Wholesale',
        '02-9876544',
        'fashion@supplier.com',
        'Banani, Dhaka',
        10000.00,
        10000.00,
        'Net 15 days',
        'active',
        NOW()
    ),
    (
        'Food Distributors',
        '02-9876545',
        'food@supplier.com',
        'Uttara, Dhaka',
        0.00,
        0.00,
        'Cash on delivery',
        'active',
        NOW()
    ),
    (
        'Furniture Factory',
        '02-9876546',
        'furniture@supplier.com',
        'Gazipur',
        5000.00,
        5000.00,
        'Net 45 days',
        'active',
        NOW()
    ),
    (
        'Office Essentials',
        '02-9876547',
        'office@supplier.com',
        'Mirpur, Dhaka',
        0.00,
        0.00,
        'Net 30 days',
        'active',
        NOW()
    );
-- 6. PRODUCTS
INSERT IGNORE INTO `products` (
        `name`,
        `code`,
        `barcode`,
        `brand_id`,
        `category_id`,
        `unit_id`,
        `purchase_price`,
        `selling_price`,
        `tax_rate`,
        `stock_quantity`,
        `reorder_level`,
        `description`,
        `status`,
        `has_serial`,
        `warranty_duration`,
        `warranty_period`,
        `created_at`
    )
VALUES (
        'Samsung Galaxy S23',
        'PRD001',
        '8801234567890',
        1,
        1,
        1,
        65000.00,
        75000.00,
        5.00,
        50,
        10,
        'Latest Samsung flagship smartphone',
        'active',
        'Available',
        12,
        'Month',
        NOW()
    ),
    (
        'iPhone 14 Pro',
        'PRD002',
        '8801234567891',
        2,
        1,
        1,
        95000.00,
        110000.00,
        5.00,
        30,
        5,
        'Apple iPhone 14 Pro 256GB',
        'active',
        'Available',
        12,
        'Month',
        NOW()
    ),
    (
        'Office Desk',
        'PRD003',
        '8801234567892',
        4,
        2,
        1,
        8000.00,
        12000.00,
        0.00,
        25,
        5,
        'IKEA office desk with drawers',
        'active',
        'Not Available',
        0,
        'Month',
        NOW()
    ),
    (
        'Nike Air Max',
        'PRD004',
        '8801234567893',
        3,
        3,
        1,
        4500.00,
        6500.00,
        0.00,
        40,
        10,
        'Nike Air Max running shoes',
        'active',
        'Not Available',
        0,
        'Month',
        NOW()
    ),
    (
        'A4 Copy Paper',
        'PRD005',
        '8801234567894',
        NULL,
        5,
        4,
        250.00,
        350.00,
        0.00,
        100,
        20,
        'A4 size copy paper - 500 sheets/box',
        'active',
        'Not Available',
        0,
        'Month',
        NOW()
    );
-- 7. EXPENSE CATEGORIES
INSERT IGNORE INTO `expense_categories` (`name`, `description`, `created_at`)
VALUES ('Rent', 'Office and shop rent', NOW()),
    (
        'Utilities',
        'Electricity, water, internet',
        NOW()
    ),
    ('Salaries', 'Employee salaries and wages', NOW()),
    ('Marketing', 'Advertising and promotions', NOW()),
    ('Maintenance', 'Repairs and maintenance', NOW());
-- 8. CASH ACCOUNTS
INSERT IGNORE INTO `cash_accounts` (`name`, `balance`, `status`, `created_at`)
VALUES ('Main Cash Counter', 50000.00, 'active', NOW()),
    ('Petty Cash', 5000.00, 'active', NOW()),
    ('Sales Collection', 25000.00, 'active', NOW()),
    ('Reserve Fund', 100000.00, 'active', NOW()),
    ('Daily Operating', 15000.00, 'active', NOW());
-- 9. BANK ACCOUNTS
INSERT IGNORE INTO `bank_accounts` (
        `bank_name`,
        `account_name`,
        `account_number`,
        `branch`,
        `balance`,
        `status`,
        `created_at`
    )
VALUES (
        'Standard Chartered',
        'Business Account',
        '1234567890',
        'Gulshan Branch',
        500000.00,
        'active',
        NOW()
    ),
    (
        'HSBC',
        'Savings Account',
        '9876543210',
        'Motijheel Branch',
        250000.00,
        'active',
        NOW()
    ),
    (
        'City Bank',
        'Current Account',
        '1122334455',
        'Dhanmondi Branch',
        150000.00,
        'active',
        NOW()
    ),
    (
        'Dutch Bangla',
        'Business Plus',
        '5544332211',
        'Banani Branch',
        300000.00,
        'active',
        NOW()
    ),
    (
        'Brac Bank',
        'SME Account',
        '6677889900',
        'Uttara Branch',
        200000.00,
        'active',
        NOW()
    );
-- 10. STAFF ROLES
INSERT IGNORE INTO `staff_roles` (`role_name`, `description`, `created_at`)
VALUES (
        'General Manager',
        'Overall business management',
        NOW()
    ),
    (
        'Sales Manager',
        'Manages sales team and operations',
        NOW()
    ),
    ('Accountant', 'Handles financial records', NOW()),
    (
        'Sales Executive',
        'Front-line sales personnel',
        NOW()
    ),
    (
        'Store Keeper',
        'Manages inventory and warehouse',
        NOW()
    );
-- 11. STAFF
INSERT IGNORE INTO `staff` (
        `name`,
        `email`,
        `phone`,
        `address`,
        `role_id`,
        `designation`,
        `joining_date`,
        `salary`,
        `status`,
        `created_at`
    )
VALUES (
        'Ahmed Hassan',
        'ahmed@company.com',
        '01711111111',
        'Bashundhara, Dhaka',
        1,
        'General Manager',
        '2023-01-15',
        50000.00,
        'active',
        NOW()
    ),
    (
        'Fatima Khan',
        'fatima@company.com',
        '01722222222',
        'Banani, Dhaka',
        2,
        'Sales Manager',
        '2023-03-01',
        40000.00,
        'active',
        NOW()
    ),
    (
        'Karim Rahman',
        'karim@company.com',
        '01733333333',
        'Mohammadpur, Dhaka',
        3,
        'Senior Accountant',
        '2023-02-10',
        35000.00,
        'active',
        NOW()
    ),
    (
        'Nadia Islam',
        'nadia@company.com',
        '01744444444',
        'Dhanmondi, Dhaka',
        4,
        'Sales Executive',
        '2023-06-20',
        25000.00,
        'active',
        NOW()
    ),
    (
        'Rahim Uddin',
        'rahim@company.com',
        '01755555555',
        'Mirpur, Dhaka',
        5,
        'Store Keeper',
        '2023-04-12',
        20000.00,
        'active',
        NOW()
    );
-- 12. EXPENSES
INSERT IGNORE INTO `expenses` (
        `category_id`,
        `account_id`,
        `amount`,
        `description`,
        `date`,
        `created_by`,
        `created_at`
    )
VALUES (
        1,
        1,
        35000.00,
        'Monthly office rent payment',
        '2026-01-01',
        1,
        NOW()
    ),
    (
        2,
        1,
        8500.00,
        'Electricity and internet bills',
        '2026-01-05',
        1,
        NOW()
    ),
    (
        4,
        2,
        5000.00,
        'Facebook ads campaign',
        '2026-01-10',
        1,
        NOW()
    ),
    (
        5,
        2,
        3500.00,
        'AC repair and servicing',
        '2026-01-15',
        1,
        NOW()
    ),
    (
        2,
        1,
        2000.00,
        'Water bill payment',
        '2026-01-20',
        1,
        NOW()
    );
-- 13. PURCHASES (Sample purchase orders)
INSERT IGNORE INTO `purchases` (
        `supplier_id`,
        `date`,
        `total_amount`,
        `paid_amount`,
        `due_amount`,
        `payment_status`,
        `notes`,
        `created_by`,
        `created_at`
    )
VALUES (
        1,
        '2026-01-10',
        195000.00,
        150000.00,
        45000.00,
        'partial',
        'Electronics purchase order #1',
        1,
        NOW()
    ),
    (
        2,
        '2026-01-12',
        27000.00,
        27000.00,
        0.00,
        'paid',
        'Clothing stock purchase',
        1,
        NOW()
    ),
    (
        3,
        '2026-01-15',
        12500.00,
        0.00,
        12500.00,
        'due',
        'Food items purchase',
        1,
        NOW()
    ),
    (
        4,
        '2026-01-18',
        60000.00,
        30000.00,
        30000.00,
        'partial',
        'Furniture order',
        1,
        NOW()
    ),
    (
        5,
        '2026-01-20',
        8750.00,
        8750.00,
        0.00,
        'paid',
        'Office supplies stock',
        1,
        NOW()
    );
-- 14. PURCHASE ITEMS (Items for above purchases)
INSERT IGNORE INTO `purchase_items` (
        `purchase_id`,
        `product_id`,
        `quantity`,
        `unit_price`,
        `total_price`
    )
VALUES (1, 1, 3, 65000.00, 195000.00),
    (2, 4, 6, 4500.00, 27000.00),
    (3, 5, 50, 250.00, 12500.00),
    (4, 3, 5, 8000.00, 40000.00),
    (5, 5, 35, 250.00, 8750.00);
-- 15. SALES (Sample sales orders)
INSERT IGNORE INTO `sales` (
        `customer_id`,
        `date`,
        `total_amount`,
        `tax_amount`,
        `grand_total`,
        `paid_amount`,
        `due_amount`,
        `payment_status`,
        `sale_type`,
        `notes`,
        `created_by`,
        `created_at`
    )
VALUES (
        1,
        '2026-01-11',
        75000.00,
        3750.00,
        78750.00,
        78750.00,
        0.00,
        'paid',
        'retail',
        'Cash sale',
        1,
        NOW()
    ),
    (
        2,
        '2026-01-13',
        220000.00,
        11000.00,
        231000.00,
        200000.00,
        31000.00,
        'partial',
        'wholesale',
        'Corporate order',
        1,
        NOW()
    ),
    (
        3,
        '2026-01-16',
        12000.00,
        0.00,
        12000.00,
        12000.00,
        0.00,
        'paid',
        'retail',
        'Furniture sale',
        1,
        NOW()
    ),
    (
        4,
        '2026-01-19',
        19500.00,
        0.00,
        19500.00,
        10000.00,
        9500.00,
        'partial',
        'retail',
        'Shoes purchase',
        1,
        NOW()
    ),
    (
        5,
        '2026-01-22',
        1750.00,
        0.00,
        1750.00,
        1750.00,
        0.00,
        'paid',
        'retail',
        'Office supplies',
        1,
        NOW()
    );
-- 16. SALE ITEMS (Items for above sales)
INSERT IGNORE INTO `sale_items` (
        `sale_id`,
        `product_id`,
        `quantity`,
        `unit_price`,
        `tax_rate`,
        `tax_amount`,
        `total_price`
    )
VALUES (1, 1, 1, 75000.00, 5.00, 3750.00, 78750.00),
    (2, 2, 2, 110000.00, 5.00, 11000.00, 231000.00),
    (3, 3, 1, 12000.00, 0.00, 0.00, 12000.00),
    (4, 4, 3, 6500.00, 0.00, 0.00, 19500.00),
    (5, 5, 5, 350.00, 0.00, 0.00, 1750.00);
-- 17. ATTENDANCE (For current month)
INSERT IGNORE INTO `attendance` (
        `staff_id`,
        `date`,
        `status`,
        `time_in`,
        `time_out`,
        `notes`,
        `created_at`
    )
VALUES (
        1,
        '2026-01-20',
        'present',
        '09:00:00',
        '18:00:00',
        NULL,
        NOW()
    ),
    (
        2,
        '2026-01-20',
        'present',
        '09:15:00',
        '18:30:00',
        NULL,
        NOW()
    ),
    (
        3,
        '2026-01-20',
        'present',
        '08:45:00',
        '17:45:00',
        NULL,
        NOW()
    ),
    (
        4,
        '2026-01-20',
        'half_day',
        '09:00:00',
        '13:00:00',
        'Left early for personal work',
        NOW()
    ),
    (
        5,
        '2026-01-20',
        'present',
        '08:30:00',
        '17:30:00',
        NULL,
        NOW()
    );
-- 18. SALARIES
INSERT IGNORE INTO `salaries` (
        `staff_id`,
        `month`,
        `year`,
        `basic_salary`,
        `allowances`,
        `deductions`,
        `net_salary`,
        `payment_date`,
        `payment_status`,
        `notes`,
        `created_at`
    )
VALUES (
        1,
        12,
        2025,
        50000.00,
        5000.00,
        0.00,
        55000.00,
        '2026-01-05',
        'paid',
        'December 2025 salary',
        NOW()
    ),
    (
        2,
        12,
        2025,
        40000.00,
        4000.00,
        0.00,
        44000.00,
        '2026-01-05',
        'paid',
        'December 2025 salary',
        NOW()
    ),
    (
        3,
        12,
        2025,
        35000.00,
        3500.00,
        0.00,
        38500.00,
        '2026-01-05',
        'paid',
        'December 2025 salary',
        NOW()
    ),
    (
        4,
        12,
        2025,
        25000.00,
        2500.00,
        0.00,
        27500.00,
        '2026-01-05',
        'paid',
        'December 2025 salary',
        NOW()
    ),
    (
        5,
        12,
        2025,
        20000.00,
        2000.00,
        0.00,
        22000.00,
        '2026-01-05',
        'paid',
        'December 2025 salary',
        NOW()
    );
-- 19. STOCK ADJUSTMENTS
INSERT IGNORE INTO `stock_adjustments` (
        `product_id`,
        `adjustment_type`,
        `quantity`,
        `reason`,
        `date`,
        `created_by`,
        `created_at`
    )
VALUES (
        1,
        'damage',
        -2,
        'Screen damage during handling',
        '2026-01-12',
        1,
        NOW()
    ),
    (
        4,
        'loss',
        -1,
        'Missing from inventory',
        '2026-01-14',
        1,
        NOW()
    ),
    (
        5,
        'adjustment',
        10,
        'Stock correction after physical count',
        '2026-01-16',
        1,
        NOW()
    ),
    (
        2,
        'damage',
        -1,
        'Water damage',
        '2026-01-18',
        1,
        NOW()
    ),
    (
        3,
        'adjustment',
        -2,
        'Returned to supplier - defective',
        '2026-01-21',
        1,
        NOW()
    );
-- 20. RMA REQUESTS (Return Merchandise Authorization)
INSERT IGNORE INTO `rma_requests` (
        `customer_id`,
        `product_id`,
        `serial_number`,
        `issue_description`,
        `status`,
        `request_date`,
        `resolution_date`,
        `resolution_notes`,
        `created_at`
    )
VALUES (
        1,
        1,
        'SN-123456789',
        'Screen flickering issue',
        'approved',
        '2026-01-14',
        '2026-01-16',
        'Replaced with new unit',
        NOW()
    ),
    (
        2,
        2,
        'SN-987654321',
        'Battery draining fast',
        'pending',
        '2026-01-18',
        NULL,
        NULL,
        NOW()
    ),
    (
        3,
        1,
        'SN-456789123',
        'Camera not working',
        'in_progress',
        '2026-01-19',
        NULL,
        'Sent to service center',
        NOW()
    ),
    (
        4,
        2,
        'SN-321654987',
        'Touch screen unresponsive',
        'approved',
        '2026-01-21',
        '2026-01-23',
        'Repaired and returned',
        NOW()
    ),
    (
        5,
        1,
        'SN-789123456',
        'WiFi connectivity issues',
        'pending',
        '2026-01-24',
        NULL,
        NULL,
        NOW()
    );
-- Update product stock based on purchases and sales
UPDATE `products`
SET `stock_quantity` = 51
WHERE `code` = 'PRD001';
-- +3 purchase, -1 sale, -2 damage
UPDATE `products`
SET `stock_quantity` = 27
WHERE `code` = 'PRD002';
-- +0 purchase, -2 sales, -1 damage
UPDATE `products`
SET `stock_quantity` = 22
WHERE `code` = 'PRD003';
-- +5 purchase, -1 sale, -2 adjustment
UPDATE `products`
SET `stock_quantity` = 45
WHERE `code` = 'PRD004';
-- +6 purchase, -3 sales, -1 loss
UPDATE `products`
SET `stock_quantity` = 180
WHERE `code` = 'PRD005';
-- +85 purchase, -5 sale, +10 adjustment
-- Success message
SELECT 'Demo data inserted successfully! 5 records added to each major table.' as message;
