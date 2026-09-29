# Project and Admin Leave APIs - Complete cURL Guide

Base URL: `https://yellowgreen-stork-427223.hostingersite.com`

## Authentication (fixes the 401 shown in screenshots 1 and 2)

`YOUR_TOKEN` is only a placeholder. First log in using a real admin, manager, or employee account:

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/login' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data '{
  "email": "admin@example.com",
  "password": "your-password"
}'
```

Copy the `access_token` value from the response and use it instead of `YOUR_TOKEN` below. In Postman, Authorization must be **Bearer Token**, with only the token value in the Token field.

## Admin-only leave APIs

### All employees' leave requests

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/employees/leaves?status=all&from_date=2026-09-01&to_date=2026-09-30&per_page=100' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

### All employees' leave reports

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/employees/leave-reports?from_date=2026-09-01&to_date=2026-09-30&status=all' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

Optional report filters: `department_id` and `leave_type_id`.

## Admin/manager project APIs

An administrator or an active employee with a manager designation/role can use these APIs.

### Get project totals

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects/total' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

### Get all projects

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects?status=all&per_page=100' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

Valid status filters: `Not Started`, `In Progress`, `Completed`, and `On Hold`.

### Search projects

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects/search?query=website&per_page=100' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

### Create project and assign multiple employees

Remove the `files[]` lines if you do not want to upload attachments.

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN' \
--form 'name=Website Redesign' \
--form 'description=Redesign and develop the company website with new UI/UX.' \
--form 'category=Web Development' \
--form 'start_date=2026-09-29' \
--form 'end_date=2026-10-29' \
--form 'status=Not Started' \
--form 'progress=0' \
--form 'employee_ids[]=20' \
--form 'employee_ids[]=21' \
--form 'employee_ids[]=22' \
--form 'files[]=@"C:/Users/YourName/Documents/project-brief.pdf"' \
--form 'files[]=@"C:/Users/YourName/Pictures/design.png"'
```

Each file may be up to 10 MB. Supported types: JPG, PNG, WebP, PDF, DOC, DOCX, XLS, XLSX, CSV, TXT, and ZIP.

### Get project by ID

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects/1' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

### Update project and replace employee assignments

Only include `employee_ids` when you want to replace the current assigned team.

```bash
curl --location --request PATCH 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects/1' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN' \
--data '{
  "name": "Website Redesign Phase 2",
  "status": "In Progress",
  "progress": 41,
  "employee_ids": [20, 21, 25]
}'
```

To add new files while updating, use the multipart-compatible POST endpoint:

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects/1' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN' \
--form 'status=In Progress' \
--form 'progress=50' \
--form 'files[]=@"C:/Users/YourName/Documents/progress-report.pdf"'
```

### Remove one employee from a project

Here `1` is the project ID and `21` is the employee user ID.

```bash
curl --location --request DELETE 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects/1/employees/21' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

### Delete project

```bash
curl --location --request DELETE 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects/1' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_TOKEN'
```

## Manager/employee assigned-project APIs

These endpoints only return projects assigned to the authenticated user.

### Get assigned projects

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects/assigned?status=all&per_page=100' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_EMPLOYEE_TOKEN'
```

### Get assigned project details

```bash
curl --location 'https://yellowgreen-stork-427223.hostingersite.com/api/admin/projects/assigned/1' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_EMPLOYEE_TOKEN'
```

The details response contains `overview`, static `tasks`, `team`, `files`, and `timeline` sections. Tasks intentionally return static empty data until the future task workflow is implemented.

## Deployment

After deploying the code, run:

```bash
php artisan migrate --force
php artisan storage:link
```
