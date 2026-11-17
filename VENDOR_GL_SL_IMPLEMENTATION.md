# Vendor COA Account Assignment Implementation

## What was added

This implementation adds **Chart of Accounts** assignment to the Vendors module. Each vendor is assigned to a specific COA account that will be used for payment routing in DVs and other transactions.

## Files Changed

### 1. Database Migration
- **File**: `_assets/sql/01 - fims/03 - alter/50 - add_gl_sl_to_vendors.sql`
- **What**: Adds `coa_account_id` (VARCHAR(10), FK to `fims.chart_of_accounts(account_id)`) and `sl_code` (VARCHAR(100)) to `fims.vendors`
- **Run**: Manually execute the SQL against the existing database. It is safe to rerun: existing columns, the vendor foreign key, and indexes are preserved.

### 2. Backend Routes
- **File**: `app/routes/accounting.php` (NEW)
- **What**: Added `/accounting/coa/search` endpoint for autocomplete searches
- **Usage**: `GET /accounting/coa/search?q=search_term&limit=20`

### 3. Backend Service
- **File**: `app/services/VendorService.php`
- **Changes**:
  - Added `validateCoaAccount()` method to validate account exists
  - Updated `create()` to require and validate `coa_account_id`
  - Updated `update()` to allow COA account modification with validation
  - Updated `fetchById()` to include COA account code and name

### 4. Frontend Form
- **File**: `app/pages/form/vendor-form.php`
- **Changes**:
  - Added "Chart of Accounts Assignment" section
  - Added single Account picker field (required)
  - Search/autocomplete with account code and name

### 5. Frontend Page Logic
- **File**: `app/pages/vendors-page.php`
- **Changes**:
  - Added `vnd_searchCoaAccounts()` for autocomplete search
  - Added `vnd_selectCoaAccount()` for selection and display
  - Updated modal to populate COA data when editing
  - Updated validation to require COA account on add/edit

## Validation Rules

### Create (Add New Vendor)
- ✓ Vendor code (existing rule)
- ✓ Vendor name (existing rule)
- ✓ **COA Account** — must exist in `accounting.coa` table (NEW, REQUIRED)
- ✓ Email format (existing rule)

### Update (Edit Vendor)
- COA account can be changed or cleared
- If provided, must exist in the Chart of Accounts
- Vendor code cannot change (existing rule)
- Vendor name is required (existing rule)

## How to Test

### 1. Run Migration
```powershell
psql -h localhost -U postgres -d <database> -v ON_ERROR_STOP=1 -f "_assets\sql\01 - fims\03 - alter\50 - add_gl_sl_to_vendors.sql"
```

### 2. Add a Vendor with COA Assignment
```
POST /vendors/add
{
  "vendor_code": "VEN-2026-001",
  "vendor_name": "ACME Corp",
  "vendor_type": "Supplier",
  "coa_account_id": 1273,
  "is_vat_registered": true,
  "status": "ACTIVE"
}
```

Account ID 1273 = "01-01-010-01 CONSTRUCTION AND SUPPLIES OPC"

### 3. Edit a Vendor's COA Account
```
PUT /vendors/update/1
{
  "coa_account_id": 768
}
```

### 4. Search COA Accounts
```
GET /accounting/coa/search?q=Accounts%20Payable&limit=20
```

Returns matching accounts with `account_id`, `account_code`, `account_name`.

### 5. Browser Test
1. Go to Vendors page
2. Click "Add Vendor"
3. Fill basic info
4. In "Chart of Accounts Assignment" section, type account code or name
5. Select from dropdown (e.g., "01-01-010-01 CONSTRUCTION AND SUPPLIES OPC")
6. Click "Save Vendor" — validates account exists before saving
7. Edit vendor — COA account appears pre-filled in search field

## Next Steps (Future Integration)

When creating Disbursement Vouchers (DVs) for a vendor:
1. Query the vendor's `coa_account_id`
2. Look up the full account code from `accounting.coa`
3. Pre-populate DV detail lines with the assigned account
4. Allow override if needed

Example hookup in DV creation:
```php
$vendor = $VendorService->fetchById($vendorId);
$accountId = $vendor['data']['coa_account_id'];
$accountCode = $vendor['data']['coa_account_code']; // e.g., "01-01-010-01"
// → Use accountId/accountCode when creating voucher_details rows
```

## Data Structure

| Column | Type | Required | Description |
|--------|------|----------|-------------|
| `vendor_id` | INT | ✓ | Primary key |
| `vendor_code` | VARCHAR(50) | ✓ | Unique code |
| `vendor_name` | VARCHAR(500) | ✓ | Business name |
| `coa_account_id` | INT | ✓ | FK to accounting.coa |
| `status` | VARCHAR(20) | | ACTIVE/INACTIVE/BLACKLISTED |
| ... | | | (other existing fields) |

## Error Messages

| Scenario | Message |
|----------|---------|
| No COA account selected | "Chart of Accounts assignment is required." |
| Account doesn't exist | "Account #1234 does not exist in the Chart of Accounts." |
| Invalid account ID | "Account #{invalid} does not exist in the Chart of Accounts." |
