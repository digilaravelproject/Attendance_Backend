# Designation, Shift & Employee API cURLs

Start the API with `php artisan serve`. These examples use `http://127.0.0.1:8000`.

Replace `<ACCESS_TOKEN>`, `<DESIGNATION_ID>`, `<SHIFT_ID>`, and `<EMPLOYEE_ID>` with values returned by earlier requests.

## 1. Admin login

```bash
curl --location "http://127.0.0.1:8000/api/admin/login" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "email": "admin@empmanagement.com",
    "password": "admin123"
  }'
```

## Designation APIs

### 2. Create designation with skills

```bash
curl --location "http://127.0.0.1:8000/api/admin/designations" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "name": "Senior Flutter Developer",
    "hierarchy_level": "senior",
    "skills": ["Flutter", "Dart", "Firebase", "REST API"]
  }'
```

### 3. List designations

```bash
curl --location "http://127.0.0.1:8000/api/admin/designations" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

### 4. Search designations

```bash
curl --location --get "http://127.0.0.1:8000/api/admin/designations/search" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --data-urlencode "query=Flutter"
```

### 5. Get designation by ID

```bash
curl --location "http://127.0.0.1:8000/api/admin/designations/<DESIGNATION_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

### 6. Update designation and skills

```bash
curl --location --request PATCH "http://127.0.0.1:8000/api/admin/designations/<DESIGNATION_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "name": "Lead Flutter Developer",
    "hierarchy_level": "manager",
    "skills": ["Flutter", "Dart", "Firebase", "Team Leadership"]
  }'
```

### 7. Remove an employee from a designation

```bash
curl --location --request DELETE "http://127.0.0.1:8000/api/admin/designations/<DESIGNATION_ID>/employees/<EMPLOYEE_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

### 8. Delete an empty designation

```bash
curl --location --request DELETE "http://127.0.0.1:8000/api/admin/designations/<DESIGNATION_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

## Shift APIs

### 9. Create the complete seven-step shift

`employee_ids` is optional. Add existing employee IDs when employees should be assigned during shift creation.

```bash
curl --location "http://127.0.0.1:8000/api/admin/shifts" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "name": "Morning Shift",
    "code": "MORNING",
    "shift_type": "Fixed Shift",
    "status": true,
    "description": "Morning working shift for sales and operations team.",
    "start_time": "10:00 AM",
    "end_time": "07:00 PM",
    "cross_midnight": false,
    "breaks_enabled": true,
    "breaks": [
      {
        "name": "Lunch Break",
        "type": "Paid",
        "start_time": "01:00 PM",
        "end_time": "02:00 PM"
      }
    ],
    "grace_period_minutes": 15,
    "late_after_minutes": 15,
    "minimum_working_minutes": 480,
    "early_leaving_allowed": false,
    "auto_mark_late": true,
    "auto_mark_half_day": true,
    "late_threshold_minutes": 30,
    "half_day_after_minutes": 240,
    "overtime_enabled": true,
    "overtime_starts_after_minutes": 480,
    "minimum_overtime_minutes": 30,
    "overtime_calculation": "Hourly",
    "overtime_approval_required": true,
    "working_days": [
      {"day": "Monday", "enabled": true, "start_time": "10:00", "end_time": "19:00"},
      {"day": "Tuesday", "enabled": true, "start_time": "10:00", "end_time": "19:00"},
      {"day": "Wednesday", "enabled": true, "start_time": "10:00", "end_time": "19:00"},
      {"day": "Thursday", "enabled": true, "start_time": "10:00", "end_time": "19:00"},
      {"day": "Friday", "enabled": true, "start_time": "10:00", "end_time": "19:00"},
      {"day": "Saturday", "enabled": false, "start_time": "10:00", "end_time": "19:00"},
      {"day": "Sunday", "enabled": false, "start_time": "10:00", "end_time": "19:00"}
    ],
    "employee_ids": []
  }'
```

### 10. List or search shifts

```bash
curl --location --get "http://127.0.0.1:8000/api/admin/shifts" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --data-urlencode "search=Morning" \
  --data-urlencode "status=Active"
```

### 11. Get shift by ID

```bash
curl --location "http://127.0.0.1:8000/api/admin/shifts/<SHIFT_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

### 12. Update shift and assigned employees

Sending `employee_ids` replaces the shift's current employee selection.

```bash
curl --location --request PUT "http://127.0.0.1:8000/api/admin/shifts/<SHIFT_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "late_threshold_minutes": 45,
    "overtime_enabled": true,
    "employee_ids": [<EMPLOYEE_ID>]
  }'
```

### 13. Shift management overview

```bash
curl --location "http://127.0.0.1:8000/api/admin/shifts/overview" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

### 14. Delete shift

```bash
curl --location --request DELETE "http://127.0.0.1:8000/api/admin/shifts/<SHIFT_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

## Employee APIs

### 15. Create complete employee with profile photo

This request sends the attractive welcome email. If `password` is omitted, a secure temporary password is generated and included only in the email.

```bash
curl --location "http://127.0.0.1:8000/api/admin/employees" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --form "avatar=@C:/path/to/profile.jpg" \
  --form "name=Rahul Sharma" \
  --form "employee_id=EMP-2026-007" \
  --form "gender=Male" \
  --form "date_of_birth=1996-05-15" \
  --form "marital_status=Single" \
  --form "blood_group=O+" \
  --form "mobile_number=9876543210" \
  --form "alternate_mobile_number=9876500001" \
  --form "email=rahul@example.com" \
  --form "emergency_contact=9876500000" \
  --form "street_address=Flat 402, Sunshine Heights" \
  --form "city=Bengaluru" \
  --form "postal_code=560038" \
  --form "state=Karnataka" \
  --form "country=India" \
  --form "work_mode=Office" \
  --form "employee_type=Full-time" \
  --form "department=Engineering" \
  --form "designation_id=<DESIGNATION_ID>" \
  --form "team=Team Alpha" \
  --form "assigned_shift_id=<SHIFT_ID>" \
  --form "date_of_joining=2026-09-17" \
  --form "employment_status=Active" \
  --form "probation_period=3 Months" \
  --form "notice_period=30 Days" \
  --form "salary_type=Monthly" \
  --form "monthly_base_salary=60000" \
  --form "sales_target_enabled=0" \
  --form "account_holder_name=Rahul Sharma" \
  --form "bank_name=HDFC Bank" \
  --form "account_number=50100456789123" \
  --form "ifsc_code=HDFC0001234" \
  --form "branch_name=Main Branch" \
  --form "skills[]=Flutter" \
  --form "skills[]=Dart" \
  --form "skills[]=Teamwork"
```

To assign a reporting manager, also add `--form "reporting_manager_id=<EMPLOYEE_ID>"`.

### 16. List or filter employees

```bash
curl --location --get "http://127.0.0.1:8000/api/admin/employees" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --data-urlencode "department=Engineering" \
  --data-urlencode "employment_status=Active"
```

### 17. Search employees

```bash
curl --location --get "http://127.0.0.1:8000/api/admin/employees/search" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --data-urlencode "query=Rahul"
```

### 18. Get employee by ID

```bash
curl --location "http://127.0.0.1:8000/api/admin/employees/<EMPLOYEE_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

### 19. Update employee JSON fields

```bash
curl --location --request PATCH "http://127.0.0.1:8000/api/admin/employees/<EMPLOYEE_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "employment_status": "Probation",
    "probation_period": "6 Months",
    "monthly_base_salary": 65000,
    "skills": ["Flutter", "Dart", "Firebase", "Teamwork"]
  }'
```

### 20. Update employee profile photo

Use the POST update alias for multipart file uploads.

```bash
curl --location "http://127.0.0.1:8000/api/admin/employees/<EMPLOYEE_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --form "avatar=@C:/path/to/new-profile.jpg"
```

### 21. Delete employee

```bash
curl --location --request DELETE "http://127.0.0.1:8000/api/admin/employees/<EMPLOYEE_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

## Email delivery

The application calls the configured Laravel mail transport synchronously during employee creation. The current local `.env` uses `MAIL_MAILER=log`, so local test emails are written to `storage/logs/laravel.log`. Configure SMTP values in `.env` to deliver to a real inbox.
