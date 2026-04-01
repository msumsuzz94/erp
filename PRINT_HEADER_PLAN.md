# Standardized Global Print Header Plan

The goal is to implement a professional, full-width print header across all reports and list pages (e.g., Customer List, Supplier List, Expense Report), excluding Sale and Quotation invoices.

## Core Requirements

1. **Layout Proportions:**
    * **Left (10%):** Company Logo (no padding, left-aligned).
    * **Middle (65%):** Company Name & Slogan (center-aligned).
    * **Right (25%):** Address Details (bordered box, left-aligned text within).
2. **Full Width:** The layout must span the entire width of the page, similar to the sale invoice print, without excessive white space on the sides.
3. **Clean Print:** Hide all non-essential elements (buttons, filters, sidebar, navigation, headers, card headers).
4. **Database Connection:** Fetch all data from the `invoice_settings` table.
5. **No Duplicates:** Ensure only one header appears on the print page.

## Technical Implementation Details

### 1. Global Print Header Template

**File:** `templates/print-header.php`

Update the template with specific CSS to handle the 10-65-25 layout and force full-width printing.

**CSS Snippet:**

```css
@media print {
    * { box-sizing: border-box !important; }
    body, html { margin: 0 !important; padding: 0 !important; width: 100% !important; }
    .wrapper, #content, .container-fluid, .container, .card, .card-body { 
        width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; 
    }
    .container-fluid { padding: 0 15px !important; } /* Standardized print margin */
    
    .print-header { 
        display: flex !important; width: 100% !important; justify-content: space-between !important;
        border-bottom: 2px solid #000 !important; padding-bottom: 10px !important; margin-bottom: 20px !important;
    }
    .print-header-left { width: 10% !important; }
    .print-header-center { width: 65% !important; text-align: center !important; }
    .print-header-right { width: 25% !important; border: 1px solid #000 !important; padding: 10px !important; }
}
```

### 2. Preventing Duplicates

**File:** `templates/header.php`

Check if `standard-print-header.php` is being included automatically. If so, wrap it in a condition or ensure the new header replaces it cleanly to avoid seeing two headers on one page.

### 3. Integration Points

Include `print-header.php` in:
* `modules/reports/expense-report.php` (after `header.php` include)
* `modules/reports/sales-report.php`
* `modules/customers/customer-list.php`
* `modules/suppliers/supplier-list.php`
* `modules/purchase/purchase-print.php` (update embedded table)
* [And all other reports mentioned in previous lists]

## Success Criteria

- [ ] No more duplicate headers on print preview.
* [ ] Content spans the full page width.
* [ ] Exact 10-65-25 proportions observed.
* [ ] Non-table elements items are hidden on print.
