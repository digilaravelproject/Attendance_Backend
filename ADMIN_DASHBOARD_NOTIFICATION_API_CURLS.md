# Admin Dashboard, Notifications & Module Statistics API cURLs

Run the new migration before testing:

```bash
php artisan migrate
```

Start the API with `php artisan serve`. These requests use `http://127.0.0.1:8000`.

Replace `<ADMIN_TOKEN>`, `<EMPLOYEE_TOKEN>`, `<EMPLOYEE_ID>`, `<NOTIFICATION_ID>`,
`<LEAVE_REQUEST_ID>`, and `<SHIFT_ID>` with real values returned by your API.

Every successful admin `POST`, `PUT`, `PATCH`, or `DELETE` action creates an admin
notification. If the action affects an employee, it also creates a notification for
that employee.

## Authentication

### 1. Admin login

```bash
curl --location "http://127.0.0.1:8000/api/admin/login" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "email": "admin@empmanagement.com",
    "password": "admin123"
  }'
```

Copy `access_token` from the response and use it as `<ADMIN_TOKEN>`.

### 2. Employee login

```bash
curl --location "http://127.0.0.1:8000/api/admin/login" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "email": "employee@example.com",
    "password": "employee-password"
  }'
```

Copy `access_token` from the response and use it as `<EMPLOYEE_TOKEN>`.

## Admin Dashboard APIs

### 3. Get admin dashboard

Returns the greeting, company, notification badge count, today's employee totals and
percentages, current shift/check-in windows, and recent activities.

```bash
curl --location "http://127.0.0.1:8000/api/admin/dashboard" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

The same URL remains valid for an employee token and returns the existing employee
dashboard response.

### 4. Get module statistics

```bash
curl --location "http://127.0.0.1:8000/api/admin/modules/statistics" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

Alias: `GET /api/admin/module-statistics`.

## Actions That Automatically Create Notifications

The following examples demonstrate employee-related actions. Existing admin CRUD APIs
for departments, designations, roles, shifts, rotations, leave types, and holidays are
also logged automatically when successful.

### 5. Update an employee (creates admin + employee notifications)

```bash
curl --location --request PATCH "http://127.0.0.1:8000/api/admin/employees/<EMPLOYEE_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "name": "Updated Employee Name"
  }'
```

### 6. Approve leave (creates admin + employee notifications)

```bash
curl --location --request POST "http://127.0.0.1:8000/api/admin/leave-requests/<LEAVE_REQUEST_ID>/approve" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "note": "Approved by HR."
  }'
```

### 7. Reject leave (creates admin + employee notifications)

```bash
curl --location --request POST "http://127.0.0.1:8000/api/admin/leave-requests/<LEAVE_REQUEST_ID>/reject" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "note": "Insufficient leave balance."
  }'
```

### 8. Assign a shift (creates admin + employee notifications)

```bash
curl --location "http://127.0.0.1:8000/api/admin/assigned-shifts" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "shift_id": <SHIFT_ID>,
    "employee_ids": [<EMPLOYEE_ID>],
    "dates": ["2026-09-24"],
    "assignment_type": "By Employee"
  }'
```

## Authenticated User Notification APIs

Use `<ADMIN_TOKEN>` to manage the logged-in admin's notifications, or
`<EMPLOYEE_TOKEN>` to manage the logged-in employee's notifications. Users cannot read
or delete another user's notifications through these endpoints.

### 9. Get all notifications

```bash
curl --location "http://127.0.0.1:8000/api/admin/notifications" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

### 10. Get unread notifications with pagination

```bash
curl --location --get "http://127.0.0.1:8000/api/admin/notifications" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>" \
  --data-urlencode "status=unread" \
  --data-urlencode "per_page=20"
```

Supported filters: `status=all|read|unread`, `module`, `type`, and `per_page`.

### 11. View notification by ID

```bash
curl --location "http://127.0.0.1:8000/api/admin/notifications/<NOTIFICATION_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

### 12. Mark one notification as read

```bash
curl --location --request PATCH "http://127.0.0.1:8000/api/admin/notifications/<NOTIFICATION_ID>/read" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

### 13. Mark all notifications as read

```bash
curl --location --request PATCH "http://127.0.0.1:8000/api/admin/notifications/read-all" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

### 14. Delete one notification

```bash
curl --location --request DELETE "http://127.0.0.1:8000/api/admin/notifications/<NOTIFICATION_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

### 15. Delete all notifications belonging to the logged-in user

```bash
curl --location --request DELETE "http://127.0.0.1:8000/api/admin/notifications" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

## Employee Notification Aliases

These aliases return only the employee identified by `<EMPLOYEE_TOKEN>`; an employee ID
is not accepted from the client.

### 16. Employee get all notifications

```bash
curl --location "http://127.0.0.1:8000/api/admin/employee-notifications" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <EMPLOYEE_TOKEN>"
```

### 17. Employee view notification

```bash
curl --location "http://127.0.0.1:8000/api/admin/employee-notifications/<NOTIFICATION_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <EMPLOYEE_TOKEN>"
```

### 18. Employee mark notification as read

```bash
curl --location --request PATCH "http://127.0.0.1:8000/api/admin/employee-notifications/<NOTIFICATION_ID>/read" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <EMPLOYEE_TOKEN>"
```

### 19. Employee delete notification

```bash
curl --location --request DELETE "http://127.0.0.1:8000/api/admin/employee-notifications/<NOTIFICATION_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <EMPLOYEE_TOKEN>"
```

Employees can also use requests 9-15 with `<EMPLOYEE_TOKEN>`.

## Admin APIs for a Specific Employee's Notifications

These endpoints require an admin token and are useful for support/administration.

### 20. Admin get an employee's notifications

```bash
curl --location "http://127.0.0.1:8000/api/admin/employees/<EMPLOYEE_ID>/notifications" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

### 21. Admin view an employee notification

```bash
curl --location "http://127.0.0.1:8000/api/admin/employees/<EMPLOYEE_ID>/notifications/<NOTIFICATION_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

### 22. Admin mark an employee notification as read

```bash
curl --location --request PATCH "http://127.0.0.1:8000/api/admin/employees/<EMPLOYEE_ID>/notifications/<NOTIFICATION_ID>/read" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```

### 23. Admin delete an employee notification

```bash
curl --location --request DELETE "http://127.0.0.1:8000/api/admin/employees/<EMPLOYEE_ID>/notifications/<NOTIFICATION_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ADMIN_TOKEN>"
```
