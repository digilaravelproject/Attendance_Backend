# Designation, sales-target, and employee-leave API cURLs

Base URL: `http://127.0.0.1:8000`. Import each cURL block into Postman. Replace the bearer tokens and example numeric IDs (`1`, `2`, `3`) with IDs returned by your API. All authenticated endpoints use the existing `/api/admin` prefix; employees sign in through the same login URL.

## Sign in as admin

```bash
curl --location 'http://127.0.0.1:8000/api/admin/login' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{"email":"admin@empmanagement.com","password":"admin123"}'
```

Save the returned `access_token` as `ADMIN_TOKEN`.

## Sign in as employee

```bash
curl --location 'http://127.0.0.1:8000/api/admin/login' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{"email":"rahul@example.com","password":"rahulsharma@123"}'
```

Save the returned `access_token` as `EMPLOYEE_TOKEN`. A changed password must be used if the employee has reset it.

## Get designations (find a designation ID)

```bash
curl --location 'http://127.0.0.1:8000/api/admin/designations' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer ADMIN_TOKEN'
```

## Get employees (find an employee ID)

```bash
curl --location 'http://127.0.0.1:8000/api/admin/employees' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer ADMIN_TOKEN'
```

## Assign one employee to a designation

The `1` in the URL is a designation ID and the `2` in the body is an employee user ID. You may instead send `"employee_ids":[2,3]` to assign several employees.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/designations/1/employees' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer ADMIN_TOKEN' \
--data-raw '{"employee_id":2}'
```

## Remove an employee from a designation

```bash
curl --location --request DELETE 'http://127.0.0.1:8000/api/admin/designations/1/employees/2' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer ADMIN_TOKEN'
```

## Create an employee with sales target enabled

`sales_target_metric_type`, `sales_target`, and `sales_target_period` are required when `sales_target_enabled` is `1`. `incentive_commission_percent` is optional and must be between 0 and 100. Metric choices: `Revenue`, `Deals Closed`, `Units Sold`. Period choices: `Weekly`, `Monthly`, `Quarterly`, `Yearly`. Replace `designation_id` with an existing designation ID and use a unique email and employee ID.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/employees' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer ADMIN_TOKEN' \
--data-raw '{
  "employee_id":"SALES-2026-001",
  "name":"Neha Gupta",
  "mobile_number":"9876543210",
  "email":"neha@example.com",
  "emergency_contact":"9876543211",
  "address":"Pune, Maharashtra",
  "designation_id":1,
  "monthly_base_salary":60000,
  "date_of_joining":"2026-09-21",
  "skills":["Sales","Negotiation"],
  "sales_target_enabled":1,
  "sales_target_metric_type":"Revenue",
  "sales_target":500000,
  "sales_target_period":"Monthly",
  "incentive_commission_percent":5
}'
```

## Update an employee's sales target

The `2` in the URL is the employee user ID.

```bash
curl --location --request PATCH 'http://127.0.0.1:8000/api/admin/employees/2' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer ADMIN_TOKEN' \
--data-raw '{
  "sales_target_enabled":1,
  "sales_target_metric_type":"Deals Closed",
  "sales_target":25,
  "sales_target_period":"Quarterly",
  "incentive_commission_percent":7.5
}'
```

Send `{"sales_target_enabled":0}` to disable the target and clear its target-specific fields.

## Get leave types (find a leave type ID)

```bash
curl --location 'http://127.0.0.1:8000/api/admin/leave-types' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

## Get available administrators and managers for leave approval

```bash
curl --location 'http://127.0.0.1:8000/api/admin/leave-approvers' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

Use one of the returned IDs as `assigned_to_user_id` in the next request.

## Apply for leave with optional document

The employee is identified from the bearer token; no `user_id` is required or accepted as a way to apply for someone else. `session` can be `Full Day`, `1st Half`, or `2nd Half`. For a half-day request, `from_date` and `to_date` must match. Remove the `attachment` line if there is no document.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/leave-requests' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN' \
--form 'leave_type_id="1"' \
--form 'from_date="2026-09-23"' \
--form 'to_date="2026-09-25"' \
--form 'session="Full Day"' \
--form 'reason="Family event out of town"' \
--form 'contact_during_leave="9876543210"' \
--form 'address_during_leave="Pune, Maharashtra"' \
--form 'assigned_to_user_id="1"' \
--form 'attachment=@"C:/path/to/proof.pdf"'
```

Accepted attachments: JPG, PNG, and PDF, maximum 5 MB.

## Get logged-in employee's leave history and balances

`status` defaults to `all`; supported values are `all`, `pending`, `approved`, `rejected`, and `cancelled`. `year` is optional. This response includes only the logged-in employee's leave requests.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/leave-requests?status=all&year=2026&per_page=15' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

## Get a single leave request

```bash
curl --location 'http://127.0.0.1:8000/api/admin/leave-requests/1' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

## Get logged-in employee's approved leave calendar by year

```bash
curl --location 'http://127.0.0.1:8000/api/admin/leave-requests/calendar?year=2026' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

The same endpoint also supports `month=2026-09` for a monthly calendar.

## Get holiday calendar by year

This is the year-grouped national/restricted/optional holiday data shown in screenshots 8–9. Holidays are read from the database; an empty year returns zero counts rather than invented example holidays.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/holidays?year=2026' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

## Add a holiday (admin, to populate the calendar)

```bash
curl --location 'http://127.0.0.1:8000/api/admin/holidays' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer ADMIN_TOKEN' \
--data-raw '{
  "name":"Founders Day",
  "date":"2026-11-10",
  "type":"Optional",
  "description":"Company observance"
}'
```

## Approve an assigned leave (admin or the assigned manager)

```bash
curl --location 'http://127.0.0.1:8000/api/admin/leave-requests/1/approve' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer ADMIN_TOKEN' \
--data-raw '{"note":"Approved"}'
```

Replace `ADMIN_TOKEN` with the assigned manager's token when the approver is a manager.
