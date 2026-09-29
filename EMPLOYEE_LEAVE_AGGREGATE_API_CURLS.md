# Employee and Leave APIs - cURL

Base URL used below: `https://yellowgreen-stork-427223.hostingersite.com`

Replace `YOUR_TOKEN`, employee IDs, approver IDs, and leave type IDs with values from your database.

## 1. Apply for leave and assign multiple approvers

The preferred multipart field is `assigned_to_user_ids[]`. The legacy `assigned_to_user_id` field is still accepted.

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/leave-requests' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN' \
--form 'leave_type_id="1"' \
--form 'from_date="2026-10-01"' \
--form 'to_date="2026-10-02"' \
--form 'session="Full Day"' \
--form 'reason="Family event out of town"' \
--form 'contact_during_leave="9876543210"' \
--form 'address_during_leave="Pune, Maharashtra"' \
--form 'assigned_to_user_ids[]="1"' \
--form 'assigned_to_user_ids[]="5"' \
--form 'assigned_to_user_ids[]="8"' \
--form 'attachment=@"C:/Users/YourName/Documents/proof.pdf"'
```

The response contains both `assigned_to_user_ids` and full `assignees` data.

To change the approvers of a pending request:

```bash
curl --location --request PATCH 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/leave-requests/15' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN' \
--data '{
  "assigned_to_user_ids": [1, 5, 8]
}'
```

## 2. Get one employee with all related data and totals

Returns the employee profile, roles, permissions, designation, shift, manager, leave requests, attendance, salaries, notifications, and summary totals.

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/employees/20' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

## 3. Get all users, including administrators and employees

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/employees/all-users' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

Optional filters: `role=admin`, `role=employee`, `status=Active`, and `search=rahul`.

## 4. Get all leave requests of all employees

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/employees/leaves?status=all&from_date=2026-09-01&to_date=2026-09-30&per_page=100' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

Optional filters: `status`, `from_date`, `to_date`, `year`, `department_id`, `designation_id`, `leave_type_id`, `search`, and `per_page`.

Calendar-shaped approved leave data remains available here:

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/leave-requests/calendar?month=2026-09' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

## 5. Get leave reports for all employees

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/employees/leave-reports?from_date=2026-09-01&to_date=2026-09-30&department_id=1&leave_type_id=1&status=all' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

Remove any unwanted query filter. The response includes total requests/days, approved/rejected/pending/cancelled totals, leave-type distribution with percentages, department status breakdown, and matching employee leave rows.

## Database migration

Run this once after deploying the code:

```bash
php artisan migrate
```
