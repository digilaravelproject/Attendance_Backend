# Client, Converted Lead, Project, and Communication API cURL Commands

Set these Postman environment variables first:

- `base_url_live` (example: `http://127.0.0.1:8000`)
- `token`
- `lead_id`, `project_id`, `client_id`, `manager_id`, `communication_id`

All APIs below accept an authenticated admin token or an authenticated employee token. Employee results are limited to records the employee owns, manages, or is assigned to.

## Shared fixes

### Get lead statuses (admin or employee)

```bash
curl --location '{{base_url_live}}/api/admin/lead-statuses' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

### Get employee birthdays occurring within the next 30 days

```bash
curl --location '{{base_url_live}}/api/admin/birthdays/upcoming?days=30' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

`total` is the number of returned active employees whose birthdays occur in the requested window. The `data` array contains only those employees.

## Converted lead and project APIs

### Create a project from a converted lead

Use `form-data` because project documents may be attached. Repeat `files[]` or `employee_ids[]` for multiple values.

```bash
curl --location '{{base_url_live}}/api/admin/leads/{{lead_id}}/projects' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}' \
--form 'name="Messaging - Generator Dealer"' \
--form 'category="Web Development"' \
--form 'priority="Standard"' \
--form 'start_date="2026-10-23"' \
--form 'end_date="2026-11-22"' \
--form 'deliverables="Client requirement: Generator Dealer. Deliverables discussed during lead conversion."' \
--form 'special_instructions="Client requested dark mode design; weekly progress review every Monday."' \
--form 'project_manager_id="{{manager_id}}"' \
--form 'employee_ids[]="{{manager_id}}"' \
--form 'agreed_value="50000"' \
--form 'files[]=@"C:/path/to/proposal.pdf"'
```

The lead must have `status=converted`. This operation also creates or updates the client from the lead and links the lead, client, and project.

### Calculate working days inclusively

```bash
curl --location '{{base_url_live}}/api/admin/working-days?start_date=2026-10-23&end_date=2026-11-22' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

The result excludes configured holidays, every Sunday, and only the second and fourth Saturdays. First, third, and fifth Saturdays remain working days.

### Get all active managers

```bash
curl --location '{{base_url_live}}/api/admin/managers' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

Optional search:

```bash
curl --location '{{base_url_live}}/api/admin/managers?q=Vikash' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

### Get project by ID

```bash
curl --location '{{base_url_live}}/api/admin/projects/{{project_id}}' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

### Get employee lead details, including the converted project

```bash
curl --location '{{base_url_live}}/api/employee/leads/{{lead_id}}' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

For an administrator, use:

```bash
curl --location '{{base_url_live}}/api/admin/leads/{{lead_id}}' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

The response field is `data.converted_project` and includes its linked client and project manager.

## Client APIs

### Get client list

```bash
curl --location '{{base_url_live}}/api/admin/clients?status=all&per_page=15' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

Use `status=Active` or `status=Inactive` for the tabs. Clients created during lead conversion appear in this list.

### Search clients

```bash
curl --location '{{base_url_live}}/api/admin/clients/search?query=Webcraft&status=all&per_page=15' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

### Add client

```bash
curl --location '{{base_url_live}}/api/admin/clients' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer {{token}}' \
--data-raw '{
  "name": "R K Enterprises",
  "email": "info@rkenterprises.com",
  "mobile_number": "+91 98765 43210",
  "website": "https://www.rkenterprises.com",
  "address": "Suite 12, Tech Tower, Mumbai",
  "status": "Active",
  "notes": "Preferred client"
}'
```

### Get client by ID

```bash
curl --location '{{base_url_live}}/api/admin/clients/{{client_id}}' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

### Update client

```bash
curl --location --request PATCH '{{base_url_live}}/api/admin/clients/{{client_id}}' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer {{token}}' \
--data-raw '{
  "name": "R K Enterprises Pvt Ltd",
  "website": "https://www.rkenterprises.com",
  "status": "Active",
  "notes": "Updated client notes"
}'
```

### Delete client

```bash
curl --location --request DELETE '{{base_url_live}}/api/admin/clients/{{client_id}}' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

A client with assigned projects returns HTTP `409`; remove or reassign those projects before deleting the client.

## Client project APIs

### Create and assign a new project to a client

```bash
curl --location '{{base_url_live}}/api/admin/clients/{{client_id}}/projects' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer {{token}}' \
--data-raw '{
  "name": "CRM Software Development",
  "description": "Develop a custom CRM software as per the client business process.",
  "category": "Custom ERP / CRM",
  "start_date": "2026-10-23",
  "end_date": "2027-01-21",
  "project_manager_id": {{manager_id}},
  "employee_ids": [{{manager_id}}],
  "estimated_value": 550000,
  "status": "Active"
}'
```

`status=Active` is saved as the project status `In Progress`.

### Assign an existing project to a client

```bash
curl --location '{{base_url_live}}/api/admin/clients/{{client_id}}/projects' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer {{token}}' \
--data-raw '{
  "project_id": {{project_id}}
}'
```

### Get projects assigned to a client

```bash
curl --location '{{base_url_live}}/api/admin/clients/{{client_id}}/projects?status=all' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

Supported filters are `all`, `active`, `completed`, `Not Started`, `In Progress`, `Completed`, and `On Hold`.

## Client communication APIs

### Add communication

```bash
curl --location '{{base_url_live}}/api/admin/clients/{{client_id}}/communications' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}' \
--form 'type="Email"' \
--form 'subject="Project Update / Consultation"' \
--form 'communicated_at="2026-10-23 11:00:00"' \
--form 'recipient="info@client.com"' \
--form 'description="Shared the project update and recorded the discussion summary."' \
--form 'attachment=@"C:/path/to/meeting-notes.pdf"'
```

Supported types: `Email`, `Call`, `Meeting`, and `Note`. The attachment is optional.

### Get communication list

```bash
curl --location '{{base_url_live}}/api/admin/clients/{{client_id}}/communications?type=all' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

Use `type=Email`, `type=Call`, `type=Meeting`, or `type=Note` to filter the history.

### Get communication by ID

```bash
curl --location '{{base_url_live}}/api/admin/clients/{{client_id}}/communications/{{communication_id}}' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

### Update communication

```bash
curl --location --request PATCH '{{base_url_live}}/api/admin/clients/{{client_id}}/communications/{{communication_id}}' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer {{token}}' \
--data-raw '{
  "type": "Meeting",
  "subject": "Updated project review meeting",
  "communicated_at": "2026-10-24 15:30:00",
  "recipient": "info@client.com",
  "description": "Reviewed milestones and agreed on the next delivery."
}'
```

### Delete communication

```bash
curl --location --request DELETE '{{base_url_live}}/api/admin/clients/{{client_id}}/communications/{{communication_id}}' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {{token}}'
```

