# Requested API cURLs

Replace `{{base_url}}`, IDs, and the bearer token with values from your environment.

## 1. Get all users (public; no middleware/token)

```bash
curl --location --request GET '{{base_url}}/api/admin/employees/all-users' \
  --header 'Accept: application/json'
```

Optional filters: `role`, `status`, and `search`.

## 2. Get permissions for a role (public; no middleware/token)

```bash
curl --location --request GET '{{base_url}}/api/admin/permissions?role_id=10' \
  --header 'Accept: application/json'
```

Each module contains `permissions`; the previous `actions` object is no longer returned.

## 3. Get upcoming birthdays (admin or employee token)

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
