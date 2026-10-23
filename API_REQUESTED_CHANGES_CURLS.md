# Requested API cURLs

Replace `{{base_url}}`, IDs, and the bearer token with values from your environment.

## 1. Get employees who can approve/reject leave (public; no middleware/token)

```bash
curl --location --request GET '{{base_url}}/api/admin/employees/all-users' \
  --header 'Accept: application/json'
```

Only active employees whose assigned role contains the `Approve / Reject Leave`
permission are returned. Optional filters: `status` and `search`.

## 2. Get permissions for a role (public; no middleware/token)

```bash
curl --location --request GET '{{base_url}}/api/admin/permissions?role_id=10' \
  --header 'Accept: application/json'
```

Each module contains `permissions`; the previous `actions` object is no longer returned.

## 3. Get all users and upcoming birthdays (admin or employee token)

`total` and `data` return every record from the `users` table, including users
without a saved birthday. `upcoming_total` and `upcoming_data` contain only
birthdays within the requested number of days.

```bash
curl --location --request GET '{{base_url}}/api/admin/birthdays/upcoming?days=30' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}'
```

Employee records must have `date_of_birth` stored as `YYYY-MM-DD`. It can be supplied when creating or updating an employee:

```bash
curl --location --request POST '{{base_url}}/api/admin/employees' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "employee_id": "EMP-1001",
    "name": "Rahul Sharma",
    "gender": "Male",
    "date_of_birth": "1995-10-20",
    "mobile_number": "9876543210",
    "email": "rahul@example.com",
    "emergency_contact": "9876500000",
    "address": "Pune, Maharashtra",
    "designation_id": 1,
    "monthly_salary": 60000,
    "date_of_joining": "2026-10-01",
    "skills": ["PHP"]
  }'
```

## 4. Handover a task

Admin-prefixed endpoint:

```bash
curl --location --request POST '{{base_url}}/api/admin/tasks/25/handover' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-User-Id: 7' \
  --data-raw '{
    "from_user_id": 7,
    "to_user_id": 12,
    "reason": "Current workload is full; specialized backend expertise is needed."
  }'
```

Top-level alias:

```bash
curl --location --request POST '{{base_url}}/api/tasks/25/handover' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'X-User-Id: 7' \
  --data-raw '{
    "from_user_id": 7,
    "to_user_id": 12,
    "reason": "Current workload is full; specialized backend expertise is needed."
  }'
```

`from_user_id` may be omitted when the caller is a current assignee or the task has exactly one assignee. A running timer is stopped and saved before the assignment is transferred. Every transfer is written to `task_handovers` and returned as `data.handover`.

Before calling the handover endpoint in an existing database, run:

```bash
php artisan migrate
```

## 5. Get a project with database-backed tasks

The `data.tasks.summary` and `data.tasks.items` values are calculated from the
`tasks` table using the task's `project_id`.

```bash
curl --location --request GET '{{base_url}}/api/admin/projects/2' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}'
```

## 6. Get salary history and working payslip links

```bash
curl --location --request GET '{{base_url}}/api/admin/salary-history?year=2026' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}'
```

Each salary row contains:

- `payslip_url`: a signed PDF URL that opens inline in a browser.
- `payslip_download_url`: a signed PDF URL that downloads the same formatted payslip.

The signed URL is valid for seven days and does not need an Authorization header.
Copy the complete URL returned by the API, including `expires` and `signature`:

```bash
curl --location --request GET '{{payslip_url_from_response}}' \
  --output payslip.pdf
```

Download URL:

```bash
curl --location --request GET '{{payslip_download_url_from_response}}' \
  --output payslip.pdf
```

## 7. Add a reminder to an assigned lead

`reminder_type` accepts `call`, `whatsapp`, `sms`, or `meeting`. The API also
accepts the UI aliases `action`, `scheduled_at`, and `agenda` shown below.

```bash
curl --location --request POST '{{base_url}}/api/employee/leads/201/reminder' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "action": "call",
    "scheduled_at": "2026-10-23T16:00:00+05:30",
    "agenda": "Call client for price quotation and demo follow-up."
  }'
```

The plural endpoint `/api/employee/leads/201/reminders` is also supported.

## 8. Get assigned leads with reminder/new flags and priority ordering

```bash
curl --location --request GET '{{base_url}}/api/employee/leads?page=1&per_page=20' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}'
```

Every item includes `is_reminder`, `is_new`, and `next_reminder`. Pending reminder
leads are returned first, then leads created/imported during the last 10 days,
then the remaining leads. The response summary includes `reminders` and
`new_last_10_days` counts.

Get one lead with the same reminder fields and its full history:

```bash
curl --location --request GET '{{base_url}}/api/employee/leads/201' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{employee_access_token}}'
```

Before using lead reminders in an existing database, run:

```bash
php artisan migrate --force
```
