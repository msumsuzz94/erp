# Stock Transfer Serial Number Issue - Fix Guide

## Problem Description

When attempting to transfer products with serial numbers in the Stock Transfer module, you may encounter the following error:

```
Error: Serial tsa256g2507020485 is not in current stock
```

This error occurs even when the product with that serial number exists in your inventory.

## Root Cause

The issue is located in `modules/stock-transfer/transfer-create.php` at **lines 153-159**.

The validation code strictly checks if a serial number belongs to a specific `stock_type` (current, rma, or damaged):

```php
if ($serial['stock_type'] != $from_stock_type) {
    throw new Exception("Serial {$serial['serial_number']} is not in $from_stock_type stock");
}
```

### Why It Fails on cPanel

On localhost (XAMPP), serial numbers might have `NULL` or empty `stock_type` values, which pass certain validations. However, on cPanel with strict MySQL modes or different default values, this validation becomes more strict.

## Temporary Workaround

### Option 1: Use Database to Check Serial Stock Type

Before creating a stock transfer:

1. Go to phpMyAdmin in cPanel
2. Select your database
3. Run th query:

   ```sql
   SELECT id, serial_number, product_id, stock_type, status 
   FROM product_serials 
   WHERE serial_number = 'YOUR_SERIAL_NUMBER';
   ```

4. Check the `stock_type` column
5. When create the transfer, select the matching stock_type as "From" type

### Option 2: Update Serial Stock Types

If serials have NULL or empty stock_type, update them to 'current':

```sql
UPDATE product_serials 
SET stock_type = 'current' 
WHERE stock_type IS NULL OR stock_type = '';
```

## Permanent Fix (For Developers)

Update `modules/stock-transfer/transfer-create.php` around line 157:

**Current Code:**

```php
if ($serial['stock_type'] != $from_stock_type) {
    throw new Exception("Serial {$serial['serial_number']} is not in $from_stock_type stock");
}
```

**Fixed Code:**

```php
// For warehouse transfers, allow any stock_type since we're moved locations
if ($transfer_mode === 'warehouse') {
    // No stock_type validation needed for warehouse transfers
} else {
    // For stock type transfers, validate stock_type matches
    $serial_stock_type = $serial['stock_type'] ?? 'current';
    if (empty($serial_stock_type)) $serial_stock_type = 'current';
    
    if ($serial_stock_type != $from_stock_type) {
        throw new Exception("Serial {$serial['serial_number']} is in '$serial_stock_type' stock, not '$from_stock_type' stock");
    }
}
```

## Testing After Fix

1. Try creating a warehouse transfer with serial products
2. Try creating a stock-type transfer (current → rma)
3. Verify serial numbers are properly validated
4. Check that transfers complete successfully

## Notes

- Warehouse transfers move products between physical locations
- Stock type transfers change product status (current/rma/damaged)
- Serial numbers should always have a stock_type value set
- Default stock_type for new serials should be 'current'

## Support

If you continue to experience issues after applying these fixes, check:

- PHP error logs in cPanel
- MySQL error logs
- Browser console for JavaScript errors
- Ensure `product_serials` table has proper indexes
