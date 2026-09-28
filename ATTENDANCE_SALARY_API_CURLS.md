# Attendance and Salary APIs

All endpoints require `Authorization: Bearer <token>` and `Accept: application/json`.

## Admin: all employee attendance

```bash
curl --get "http://localhost:8000/api/admin/attendance" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Accept: application/json" \
  --data-urlencode "date=2026-09-25" \
  --data-urlencode "search=Rahul" \
  --data-urlencode "status=all" \
  --data-urlencode "sort=name" \
  --data-urlencode "direction=asc"
```

`status` supports `all`, `present`, `absent`, `on_leave`, `half_day`, and `late`.

## Admin: salary listing

```bash
curl --get "http://localhost:8000/api/admin/salaries" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Accept: application/json" \
  --data-urlencode "month=2026-09" \
  --data-urlencode "search=Rahul" \
  --data-urlencode "status=all"
```

`status` supports `all`, `created`, and `pending`. The response includes total, created, and pending counts.

## Admin: employee salary preview/details

Both the numeric user ID and employee code are accepted.

```bash
curl --get "http://localhost:8000/api/admin/salaries/employee/EMP001" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Accept: application/json" \
  --data-urlencode "month=2026-09"
```

The response includes attendance totals, earnings, deductions, net payable salary, payment details, and the payslip URL when salary is already created.

The explicit breakdown alias is:

```text
GET /api/admin/salaries/employee/{employeeId}/breakdown?month=YYYY-MM
```

## Admin: create salary

```bash
curl -X POST "http://localhost:8000/api/admin/salaries" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": "EMP001",
    "salary_month": "2026-09",
    "payment_date": "2026-09-25",
    "payment_mode": "Bank Transfer",
    "bank_name": "HDFC Bank",
    "account_upi_address": "XXXX XXXX XXXX 1234",
    "remarks": "Processed successfully",
    "earnings": [
      {"name": "Basic", "amount": 22500},
      {"name": "HRA", "amount": 11250},
      {"name": "Allowances", "amount": 11250}
    ],
    "deductions": [
      {"name": "Leaves/LWP", "amount": 2500}
    ],
    "confirmed": true
  }'
```

`payment_mode` supports `Bank Transfer`, `UPI`, `Cash`, and `Cheque`. If earnings or deductions are omitted, the attendance-based preview values are used. A salary can only be created once per employee per month.

## Employee: my salary history

```bash
curl --get "http://localhost:8000/api/admin/salary-history" \
  -H "Authorization: Bearer EMPLOYEE_TOKEN" \
  -H "Accept: application/json" \
  --data-urlencode "year=2026"
```

Alias: `GET /api/admin/salaries/my-history`.

The response includes current monthly CTC, its Basic/HRA/Allowances split, recent payslips, and an authenticated `payslip_url` for every salary.

## View or download a payslip

```bash
curl "http://localhost:8000/api/admin/salaries/1/payslip?download=1" \
  -H "Authorization: Bearer EMPLOYEE_OR_ADMIN_TOKEN" \
  -o payslip.html
```

Employees can only access their own payslips. Without `download=1`, the printable payslip opens inline.
