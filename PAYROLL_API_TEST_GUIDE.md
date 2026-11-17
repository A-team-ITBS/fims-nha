# Payroll API Testing Guide

## Status Check

### ✅ What's Working
- UI pages load successfully (employees.php, records.php, remittances.php)
- API server is running and responding (HTTP 1.1 at localhost:8090)
- Routes are being registered (no more PHP fatal errors)
- Session system is active (PHPSESSID cookie being set)

### ⚠️ What Needs Setup
- Database tables need to be created (SQL migrations haven't run yet)
- API requires authentication (401 Unauthorized response)
- Test data (deduction types, sample periods) need to be seeded

---

## Step 1: Deploy Database Schema

### Run Migrations

```bash
cd D:\PROJECTS\fims-nha

# Install/reset database with all migrations
npm run install-system

# OR just run SQL migrations
npm run sql-reset
```

This will:
1. Create all 7 payroll tables (employees, deduction_types, periods, records, deductions, remittances)
2. Add foreign keys and indexes
3. Seed 11 deduction types (SSS, PhilHealth, Pag-IBIG, BIR tax, etc.)
4. Seed 3 sample payroll periods

### Verify Tables Created

Once migrations complete, verify tables exist:

```bash
# In docker or via psql
docker exec fims-nha-postgres psql -U postgres -d govflow360_nha -c \
  "SELECT table_name FROM information_schema.tables WHERE table_schema='fims' AND table_name LIKE 'payroll%';"
```

Expected output:
```
payroll_deductions
payroll_deduction_types
payroll_employees
payroll_periods
payroll_records
payroll_remittances
```

---

## Step 2: Authenticate for API Testing

### Option A: Use JWT Token

```bash
# Login to get JWT token
curl -X POST http://localhost:8090/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "superadmin",
    "password": "superadmin123"
  }'

# Response:
# {"success": true, "token": "eyJ0eXAi..."}

# Copy token and use in subsequent requests:
export TOKEN="eyJ0eXAi..."
```

### Option B: Use Session Cookie

```bash
# Get session with login
curl -c /tmp/cookies.txt -b /tmp/cookies.txt \
  -X POST http://localhost:8090/app/login \
  -d "login_email=superadmin&login_password=superadmin123"

# Use in subsequent requests:
curl -b /tmp/cookies.txt http://localhost:8090/api/payroll/deduction-types
```

---

## Step 3: Test API Endpoints

### Test 1: Get Deduction Types (Should return 11 types)

```bash
curl -X GET http://localhost:8090/api/payroll/deduction-types \
  -H "X-Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "success": true,
  "data": [
    {"code": "SSS", "name": "Social Security System (SSS)", "category": "SOCIAL_SECURITY", "default_rate": 3.63},
    {"code": "PHILHEALTH", "name": "PhilHealth", "category": "SOCIAL_SECURITY", "default_rate": 2.25},
    {"code": "PAGIBIG", "name": "Pag-IBIG", "category": "SOCIAL_SECURITY", "default_rate": 1.00},
    {"code": "BIR_TAX", "name": "BIR Withholding Tax", "category": "TAX"},
    ...
  ]
}
```

### Test 2: Get Payroll Periods (Should return 3 periods)

```bash
curl -X GET http://localhost:8090/api/payroll/periods \
  -H "X-Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "success": true,
  "data": [
    {"payroll_period_id": 1, "fiscal_yr": 2025, "payroll_month": 1, "period_name": "January 2025", ...},
    {"payroll_period_id": 2, "fiscal_yr": 2025, "payroll_month": 2, "period_name": "February 2025", ...},
    {"payroll_period_id": 3, "fiscal_yr": 2025, "payroll_month": 13, "period_name": "13th Month 2024", ...}
  ]
}
```

### Test 3: List Employees (Should be empty initially)

```bash
curl -X GET http://localhost:8090/api/payroll/employees \
  -H "X-Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "success": true,
  "data": [],
  "count": 0
}
```

### Test 4: Import Employees via CSV

```bash
# Create sample CSV
cat > /tmp/employees.csv << 'EOF'
employee_no,first_name,last_name,middle_name,tin,sss_no,philhealth_no,pagibig_no,position,department,monthly_salary,date_hired
TEST-001,Juan,Dela Cruz,Santos,111-111-111-111,33-1111111-1,031111111111,121111111111,Engineer,Engineering,50000.00,2023-01-01
TEST-002,Maria,Santos,Garcia,222-222-222-222,33-2222222-2,032222222222,122222222222,Accountant,Finance,40000.00,2023-06-01
TEST-003,Pedro,Reyes,Lopez,333-333-333-333,33-3333333-3,033333333333,123333333333,Technician,IT,35000.00,2023-03-01
EOF

# Upload CSV
curl -X POST http://localhost:8090/api/payroll/employees/import \
  -H "X-Authorization: Bearer $TOKEN" \
  -F "file=@/tmp/employees.csv"
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Import complete: 3 new, 0 updated.",
  "data": {
    "imported": 3,
    "updated": 0,
    "errors": []
  }
}
```

---

## Step 4: Test Complete Payroll Workflow

### 4a. Get Payroll Period ID

```bash
curl -s http://localhost:8090/api/payroll/periods \
  -H "X-Authorization: Bearer $TOKEN" | jq '.data[0].payroll_period_id'
# Output: 1
```

### 4b. Create Payroll Record

```bash
curl -X POST http://localhost:8090/api/payroll/records \
  -H "X-Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "payroll_period_id": 1,
    "employee_id": 1,
    "days_worked": 21,
    "regular_pay": 47000,
    "overtime_pay": 3000,
    "apply_standard_deductions": true
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Payroll record created.",
  "data": {
    "payroll_record_id": 1
  }
}
```

### 4c. Get Payroll Record with Deductions

```bash
curl -s http://localhost:8090/api/payroll/records/1/detail \
  -H "X-Authorization: Bearer $TOKEN" | jq '.'
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "payroll_record_id": 1,
    "employee_no": "TEST-001",
    "full_name": "Juan Dela Cruz Santos",
    "gross_amount": 50000,
    "deductions": [
      {
        "payroll_deduction_id": 1,
        "code": "SSS",
        "name": "Social Security System (SSS)",
        "deduction_base": 50000,
        "rate_percent": 3.63,
        "calculated_amount": 1815.00
      },
      {
        "payroll_deduction_id": 2,
        "code": "PHILHEALTH",
        "name": "PhilHealth",
        "deduction_base": 50000,
        "rate_percent": 2.25,
        "calculated_amount": 1125.00
      },
      ...
    ],
    "total_deductions": 3075.00,
    "net_amount": 46925.00
  }
}
```

### 4d. Get Period Summary

```bash
curl -s http://localhost:8090/api/payroll/periods/1/summary \
  -H "X-Authorization: Bearer $TOKEN" | jq '.'
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "employee_count": 1,
    "total_gross": 50000,
    "total_deductions": 3075,
    "total_net": 46925
  }
}
```

---

## Testing Checklist

- [ ] Database migrations completed (tables created)
- [ ] Deduction types seeded (11 types visible)
- [ ] Sample periods seeded (3 periods for FY 2025 + 13th month)
- [ ] API authentication working (Bearer token accepted)
- [ ] GET /api/payroll/deduction-types returns 11 types
- [ ] GET /api/payroll/periods returns 3+ periods
- [ ] GET /api/payroll/employees returns empty array (initially)
- [ ] POST /api/payroll/employees/import uploads CSV successfully
- [ ] POST /api/payroll/records creates new record
- [ ] GET /api/payroll/records/{periodId}/detail shows deductions calculated
- [ ] GET /api/payroll/periods/{periodId}/summary shows correct totals

---

## Troubleshooting

### Issue: 401 Unauthorized

**Cause:** Missing or invalid authentication token

**Solution:**
```bash
# Get a fresh token
TOKEN=$(curl -s -X POST http://localhost:8090/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"superadmin","password":"superadmin123"}' | jq -r '.token')

# Test with token
curl -H "X-Authorization: Bearer $TOKEN" http://localhost:8090/api/payroll/deduction-types
```

### Issue: 404 Not Found

**Cause:** Route not registered or migration incomplete

**Solution:**
1. Verify SQL migrations ran: Check database for `fims.payroll_deduction_types` table
2. Reload PHP: `npm run reload`
3. Check route file syntax: Ensure payroll.php is valid PHP

### Issue: 500 Internal Server Error

**Cause:** Missing database tables or service class not found

**Solution:**
1. Run database migrations: `npm run install-system`
2. Verify service files exist in `app/services/`
3. Check PHP error logs: `docker logs fims-nha-nginx 2>&1 | grep -i error`

### Issue: Service class not found

**Cause:** PayrollEmployeeService, etc. classes not auto-loaded

**Solution:**
1. Ensure service files are in correct location: `app/services/Payroll*.php`
2. Clear any OpCache: `npm run reload`
3. Run composer autoload: `composer dump-autoload`

---

## Next Steps After Testing

Once all tests pass:

1. ✅ API is working
2. ⬜ Update DV form to integrate payroll_record_id
3. ⬜ Implement BIR tax calculation (real tax tables)
4. ⬜ Build tax annualization module
5. ⬜ Create payroll dashboard with charts

---

## API Response Format

All responses follow this format:

```json
{
  "success": true|false,
  "message": "Optional status message",
  "data": {...},
  "count": 0,
  "pagination": {...}
}
```

**Status Codes:**
- `200 OK` — Request successful
- `201 Created` — Resource created
- `400 Bad Request` — Invalid input
- `401 Unauthorized` — Missing/invalid token
- `404 Not Found` — Resource not found
- `500 Internal Server Error` — Server error

