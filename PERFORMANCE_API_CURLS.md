# Performance API

All endpoints require a Sanctum bearer token. Performance values are calculated from database projects, assigned tasks, task completion timestamps, and task quality reviews. No demo values are returned.

## Access rules

- Admin: all employee performance.
- Manager: their own performance and employees whose `reporting_manager_id` is the manager's user ID.
- Employee: their own performance only.

Managers are identified by a manager-level designation or an assigned role containing `manager`.

## Period filters

Use either `month=2026-10` or both `from=2026-10-01&to=2026-10-31`. When omitted, the current month is used.

```bash
curl --request GET "http://localhost/Attendance_Backend/public/api/admin/performance/employees?month=2026-10&view=team&search=" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN"
```

Department view supports `view=department`, `department_id`, and the same search/pagination filters.

## Employee overview and drill-downs

```bash
# Overall project/task performance
curl --request GET "http://localhost/Attendance_Backend/public/api/admin/performance/employees/EMPLOYEE_ID?month=2026-10" \
  --header "Authorization: Bearer YOUR_TOKEN"

# Attendance calendar and summary
curl --request GET "http://localhost/Attendance_Backend/public/api/admin/performance/employees/EMPLOYEE_ID/attendance?month=2026-10" \
  --header "Authorization: Bearer YOUR_TOKEN"

# Leave balances and requests
curl --request GET "http://localhost/Attendance_Backend/public/api/admin/performance/employees/EMPLOYEE_ID/leave?month=2026-10" \
  --header "Authorization: Bearer YOUR_TOKEN"

# Task completion data
curl --request GET "http://localhost/Attendance_Backend/public/api/admin/performance/employees/EMPLOYEE_ID/task-completion?month=2026-10" \
  --header "Authorization: Bearer YOUR_TOKEN"

# Timely-submission data (same task records with deadline calculations)
curl --request GET "http://localhost/Attendance_Backend/public/api/admin/performance/employees/EMPLOYEE_ID/timely-submissions?month=2026-10" \
  --header "Authorization: Bearer YOUR_TOKEN"

# Quality criteria and review history
curl --request GET "http://localhost/Attendance_Backend/public/api/admin/performance/employees/EMPLOYEE_ID/quality?month=2026-10" \
  --header "Authorization: Bearer YOUR_TOKEN"
```

## Quality reviews

An admin or the employee's reporting manager can create/update one review per task, employee, and reviewer. The task must be assigned to the employee.

```bash
curl --request POST "http://localhost/Attendance_Backend/public/api/admin/performance/employees/EMPLOYEE_ID/quality-reviews" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{
    "task_id": 12,
    "deliverable_accuracy": 92,
    "deadline_adherence": 88,
    "defect_prevention": 90,
    "collaboration": 86,
    "feedback": "Review based on the submitted task.",
    "reviewed_at": "2026-10-15 10:30:00"
  }'
```

## Messages

```bash
# Conversation history
curl --request GET "http://localhost/Attendance_Backend/public/api/admin/performance/employees/EMPLOYEE_ID/messages" \
  --header "Authorization: Bearer YOUR_TOKEN"

# Admin/manager sends a performance message
curl --request POST "http://localhost/Attendance_Backend/public/api/admin/performance/employees/EMPLOYEE_ID/messages" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer YOUR_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{"message":"Please review the delayed task and share an updated timeline."}'
```

## Score calculation

The overall score uses only available database-backed metrics and rebalances when a metric has no records:

- Project progress: 25%
- Task completion: 45%
- Timely submission: 20%
- Task quality reviews: 10%

If an employee has no applicable project, task, or quality data, their score is `null` and evaluation is `Not Rated` rather than a fabricated percentage.
