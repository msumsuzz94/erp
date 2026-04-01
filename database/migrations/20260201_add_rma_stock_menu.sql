-- Migration: Add RMA Stock menu item to Products menu
-- Date: 2026-02-01
-- Description: Adds RMA Stock management page link to Products menu
-- Add RMA Stock menu item to Products menu (parent_id = 5)
INSERT INTO menu_items (
        name,
        slug,
        icon,
        url,
        parent_id,
        sort_order,
        is_active,
        created_at
    )
SELECT 'RMA Stock',
    'products.rma_stock',
    NULL,
    '/modules/products/rma-stock.php',
    5,
    COALESCE(
        (
            SELECT MAX(sort_order)
            FROM menu_items
            WHERE parent_id = 5
        ),
        0
    ) + 1,
    1,
    NOW()
WHERE NOT EXISTS (
        SELECT 1
        FROM menu_items
        WHERE slug = 'products.rma_stock'
    );
-- Verify the insert
SELECT *
FROM menu_items
WHERE slug = 'products.rma_stock';