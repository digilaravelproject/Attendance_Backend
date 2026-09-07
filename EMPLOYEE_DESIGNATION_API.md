# Designation and Employee API cURL reference

All endpoints require a Sanctum bearer token. These examples assume Laravel is served with `php artisan serve` at `http://127.0.0.1:8000`.

Set these values first in Bash/Git Bash:

```bash
BASE_URL="http://127.0.0.1:8000/api/admin"
TOKEN="REPLACE_WITH_LOGIN_ACCESS_TOKEN"
```

If the application is served directly through XAMPP instead, use:

```bash
BASE_URL="http://localhost/attendance/public/api/admin"
```

## Get an access token

```bash
curl --request POST "$BASE_URL/login" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data '{
    "email": "admin@example.com",
    "password": "password123"
  }'
```

Copy `access_token` from the response into `TOKEN`.

## Designations

### 1. Add designation

`hierarchy_level` must be `junior`, `senior`, or `manager`. `employee_ids` is optional and may contain only users whose role is `employee`.

```bash
curl --request POST "$BASE_URL/designations" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $TOKEN" \
  --data '{
    "name": "Senior Flutter Developer",
    "hierarchy_level": "senior",
    "employee_ids": []
  }'
```

### 2. Search designations

```bash
curl --get "$BASE_URL/designations/search" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer $TOKEN" \
  --data-urlencode "query=Flutter"
```

### 3. List all designations

```bash
curl --request GET "$BASE_URL/designations" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer $TOKEN"
```

The list response contains `total` and an `employees_count` for every designation. The same endpoint also accepts an optional `?search=Flutter` query.

### 4. Get designation by ID with assigned employee details

```bash
curl --request GET "$BASE_URL/designations/1" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer $TOKEN"
```

### 5. Remove employee from designation

This clears both `users.designation_id` and the backward-compatible `users.designation` text value; it does not delete the employee.

```bash
curl --request DELETE "$BASE_URL/designations/1/employees/2" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer $TOKEN"
```

## Employees

Employees are saved in the `users` table. The API always writes `role=employee`, even if a client attempts to submit another role. `password` is optional; when omitted, a secure random password is stored.

### 1. Add employee

```bash
curl --request POST "$BASE_URL/employees" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $TOKEN" \
  --data '{
    "employee_id": "EMP-2026-003",
    "name": "Rohit Sharma",
    "mobile_number": "9876543210",
    "email": "rohit@example.com",
    "emergency_contact": "9876500000",
    "address": "Pune, Maharashtra",
    "designation_id": 1,
    "monthly_salary": 85000,
    "date_of_joining": "2026-09-05",
    "skills": ["Flutter", "React Native", "PHP"],
    "status": "Active"
  }'
```

### 2. Search employees

Search matches name, email, mobile number, employee ID, or designation name.

```bash
curl --get "$BASE_URL/employees/search" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer $TOKEN" \
  --data-urlencode "query=Rohit"
```

### 3. Employee list

```bash
curl --request GET "$BASE_URL/employees" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer $TOKEN"
```

The same endpoint accepts an optional `?search=Rohit` query.

### 4. Get employee details by ID

```bash
curl --request GET "$BASE_URL/employees/2" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer $TOKEN"
```

### 5. Update employee

Updates are partial, so send only changed fields. Both `PATCH` and `PUT` are supported.

```bash
curl --request PATCH "$BASE_URL/employees/2" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --header "Authorization: Bearer $TOKEN" \
  --data '{
    "name": "Rohit S. Sharma",
    "designation_id": 1,
    "monthly_salary": 90000,
    "skills": ["Flutter", "React Native", "PHP", "Node.js"],
    "status": "Active"
  }'
```

### 6. Delete employee

```bash
curl --request DELETE "$BASE_URL/employees/2" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer $TOKEN"
```

## Database setup

Run the new schema migration once in the target environment:

```bash
php artisan migrate
```
