# Employee Leads API cURLs

Set `{{base_url}}` to your Laravel URL, for example `http://127.0.0.1:8000`. Copy each complete cURL into Postman using **Import > Raw text**.

These APIs require an active employee who has a `sale`/`sales` role or a sales designation.

## 0. Employee login

The project uses the shared admin/employee login endpoint.

```bash
curl --location --request POST '{{base_url}}/api/admin/login' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "email": "sales.employee@example.com",
    "password": "employee-password"
  }'
```

Copy `access_token` into `{{employee_access_token}}`.

## 1. Add a lead

Use an employee ID returned by the sales-employees API as `assign_to`. To self-assign, send the logged-in employee's ID.

```bash
curl --location --request POST '{{base_url}}/api/employee/leads' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "full_name": "Rohan Mehta",
    "company_name": "Mehta Enterprises",
    "email": "rohan.mehta@mehta.com",
    "mobile_number": "+91 9876543210",
    "designation": "Proprietor",
    "lead_source": "Referral",
    "lead_status": "Interested",
    "assign_to": 12,
    "requirement": "Custom attendance and payroll application",
    "location": "Mumbai, Maharashtra",
    "estimated_value": 250000,
    "expected_closing_date": "2026-11-30",
    "notes": "Self-brought referral lead."
  }'
```

Accepted aliases: `name`/`full_name`, `phone`/`mobile_number`, `source`/`lead_source`, `status`/`lead_status`, and `assigned_to`/`assign_to`.

## 2. Get the logged-in employee's assigned leads

```bash
curl --location --request GET '{{base_url}}/api/employee/leads?page=1&per_page=20' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}'
```

Filter by status:

```bash
curl --location --request GET '{{base_url}}/api/employee/leads?status=follow_up&page=1&per_page=20' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}'
```

Supported statuses: `new`, `interested`, `follow_up`, `pending_call`, `contacted`, `qualified`, `proposal_sent`, `converted`, `unreachable`, `wrong_number`, and `lost`.

The result includes employee-specific totals in `summary` and `assignment_origin` values: `self_brought`, `admin_assigned`, or `transferred`.

## 2A. Search assigned leads

Searches customer name, company, email, phone, city/location, requirement, and lead code. It never returns another employee's leads.

```bash
curl --location --request GET '{{base_url}}/api/employee/leads/search?q=Rajesh&page=1&per_page=20' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}'
```

## 3. Get assigned lead details with history

Replace `201` with the numeric database `id` returned by the listing API, not the displayed `lead_code`.

```bash
curl --location --request GET '{{base_url}}/api/employee/leads/201' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}'
```

An employee receives `404` when the lead is not currently assigned to them.

## 4. Get sales employees

```bash
curl --location --request GET '{{base_url}}/api/employee/leads/sales-employees' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}'
```

Search colleague name, employee ID, or designation:

```bash
curl --location --request GET '{{base_url}}/api/employee/leads/sales-employees?q=Neha' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}'
```

Each result includes `assigned_leads_count` and `deals_closed_count`.

## 5. Assign the lead to another sales employee

Only the employee who currently owns the lead can reassign it. Replace `201` with the lead database ID and `14` with the destination employee ID.

```bash
curl --location --request POST '{{base_url}}/api/employee/leads/201/assign' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "employee_id": 14,
    "reason": "workload_balancing",
    "notes": "Assigned for follow-up and client meeting."
  }'
```

Allowed `reason` values:

- `branch_location_transfer`
- `language_preference`
- `enterprise_specialist`
- `workload_balancing`
- `client_requested_callback`
- `special_deal_term_requirement`

The reassignment is added to lead history. After reassignment, the previous owner can no longer access or update that lead.

## 6. Update lead status

Replace `201` with the numeric lead database ID.

```bash
curl --location --request PATCH '{{base_url}}/api/employee/leads/201/status' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "status": "Follow-up",
    "call_note": "Client wants a product demo tomorrow."
  }'
```

The API accepts display-style status values such as `Follow-up` and saves the normalized value `follow_up`. Every status update creates a timestamped history event containing the old status, new status, employee, and call note.
