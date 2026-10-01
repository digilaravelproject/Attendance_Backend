# Task Management APIs - Complete Postman cURL Guide

All Task APIs operate **without restrictive middleware** so that any user (Admin, Manager, Employee, or external Postman testing) can call them directly.

Base URL: `https://yellowgreen-stork-427223.hostingersite.com` (or your local environment `http://127.0.0.1:8000`)

---

## 1. Get All Employees List (For Task Assignment)
Get all active employees and managers to populate assignee selectors.

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/employees' \
--header 'Accept: application/json'
```

*Search employee list by name, email, or designation:*
```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/employees?search=developer' \
--header 'Accept: application/json'
```

---

## 2. Create Task with Multiple Assigned Employees (Screenshots 1 & 2)
Supports assigning multiple employee IDs and uploading multiple task file attachments (up to 10MB each).

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks' \
--header 'Accept: application/json' \
--form 'task_name="UI/UX Redesign for Mobile App"' \
--form 'description="Design new interactive screens, dark mode theme, and component library for attendance module."' \
--form 'category="UI/UX Design"' \
--form 'priority="High"' \
--form 'status="Pending"' \
--form 'start_date="2026-10-01"' \
--form 'due_date="2026-10-15"' \
--form 'estimated_hours="05h 30m"' \
--form 'project_id="1"' \
--form 'user_id="1"' \
--form 'employee_ids[]=20' \
--form 'employee_ids[]=21' \
--form 'employee_ids[]=22' \
--form 'files[]=@"C:/Users/YourName/Pictures/wireframe1.png"' \
--form 'files[]=@"C:/Users/YourName/Documents/requirements.pdf"'
```

---

## 3. Get Total Tasks & Summary Statistics (Screenshots 3 & 4)
Returns task counters: total tasks, pending, in progress, on hold, completed, in review, submitted for testing, cancelled, and priority totals.

- **Admin**: Returns statistics for tasks created by Admin.
- **Manager**: Returns statistics for tasks created by OR assigned to Manager.
- **Employee**: Returns statistics for tasks assigned to Employee.

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/total' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

*Or pass `user_id` parameter directly:*
```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/total?user_id=20' \
--header 'Accept: application/json'
```

### Get Tasks List with Filtering & Search (Screenshots 3 & 4)
```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks?status=all&priority=all&per_page=20' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

---

## 4. Start / Pause / Stop Task Timer (Screenshots 12 & 13)
Tracks logged time in real-time. Works for Admin, Manager, and Employee.

### Start Task Timer
Changes task status to `In Progress` if currently `Pending` and starts tracking elapsed time.
```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/start' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
  "note": "Starting work on wireframe designs"
}'
```

### Pause Task Timer
Pauses running timer and accumulates logged seconds into `total_logged_seconds`.
```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/pause' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
  "note": "Taking a short lunch break"
}'
```

### Stop Task Timer
Stops running timer and finalizes logged seconds.
```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/stop' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
  "note": "Completed initial design iteration"
}'
```

*Unified Timer Action Endpoint:*
```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/timer' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
  "action": "start"
}'
```
*(Valid actions: `start`, `pause`, `stop`)*

---

## 5. Update Task Status (Screenshot 5)
Update task status popup options: `Pending`, `In Progress`, `On Hold`, `Completed`, `In Review`, `Submitted For Testing`, `Cancelled`.

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/status' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
  "status": "In Progress",
  "remarks": "Work initiated by design team"
}'
```

---

## 6. Get Task by ID (Screenshots 6, 7, 8, 9, 10)
Returns complete task object including assignees, project details, subtasks list, comments, attachments, time logs, and live timer state.

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1' \
--header 'Accept: application/json'
```

---

## 7. Add Subtask & Subtask Management (Screenshot 11)

### Add Subtask to Task
```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/subtasks' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
  "title": "Design Figma Mockup Components",
  "assigned_to": 20,
  "due_date": "2026-10-05"
}'
```

### Toggle Subtask Completion (Complete / Incomplete)
```bash
curl --location --request POST 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/subtasks/1/toggle' \
--header 'Accept: application/json'
```

### Delete Subtask
```bash
curl --location --request DELETE 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/subtasks/1' \
--header 'Accept: application/json'
```

---

## 8. Update Task
Update task details and sync assigned employee list.

```bash
curl --location --request PUT 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
  "task_name": "UI/UX Redesign Phase 2",
  "priority": "Urgent",
  "status": "In Progress",
  "estimated_hours": "08h 00m",
  "employee_ids": [20, 21, 25]
}'
```

---

## 9. Delete Task
```bash
curl --location --request DELETE 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1' \
--header 'Accept: application/json'
```

---

## 10. Submit for Testing (Screenshot 14)
Used by Employee/Manager to submit a task for QA/testing with remarks and optional proof attachment. Sets status to `Submitted For Testing`.

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/submit-for-testing' \
--header 'Accept: application/json' \
--form 'remarks="Completed all UI designs and component documentation. Ready for QA test."' \
--form 'file=@"C:/Users/YourName/Documents/test-report.pdf"'
```

---

## 11. Add Comment & Fetch Comments (Screenshot 15)

### Add Comment on Task
Add a text comment with optional file attachment.
```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/comments' \
--header 'Accept: application/json' \
--form 'comment="Updated the button colors as requested in review."' \
--form 'file=@"C:/Users/YourName/Pictures/updated_button.png"' \
--form 'user_id="20"'
```

### Get All Comments for Task
```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/tasks/1/comments' \
--header 'Accept: application/json'
```

---

## Direct Task Routes Alias (`/api/tasks`)
All endpoints are also directly available without `/admin` prefix:

- `GET /api/tasks/employees`
- `GET /api/tasks/total`
- `GET /api/tasks`
- `POST /api/tasks`
- `GET /api/tasks/{id}`
- `PUT /api/tasks/{id}`
- `DELETE /api/tasks/{id}`
- `POST /api/tasks/{id}/start`
- `POST /api/tasks/{id}/pause`
- `POST /api/tasks/{id}/stop`
- `POST /api/tasks/{id}/status`
- `POST /api/tasks/{id}/submit-for-testing`
- `POST /api/tasks/{id}/subtasks`
- `POST /api/tasks/{id}/comments`
