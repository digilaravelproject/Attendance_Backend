# Leads Hub API cURLs

Set `{{base_url}}` to the running Laravel URL (for example `http://127.0.0.1:8000`). All lead APIs require an administrator token. Each block is complete and can be imported into Postman using **Import > Raw text**.

## 0. Admin login

```bash
curl --location --request POST '{{base_url}}/api/admin/login' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "email": "admin@empmanagement.com",
    "password": "admin123"
  }'
```

These are the credentials created by `AdminSeeder`; use your own admin credentials if they were changed. Copy `access_token` from the response into `{{admin_access_token}}`.

## 1. Get leads listing

```bash
curl --location --request GET '{{base_url}}/api/admin/leads?page=1&per_page=20' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}'
```

Example with filters:

```bash
curl --location --request GET '{{base_url}}/api/admin/leads?assignment=unassigned&source=website&status=new&page=1&per_page=20' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}'
```

Filters: `assignment=all|assigned|unassigned`, `source=website|google_ads|facebook|justdial|referral`, any `status` slug returned by `GET /api/admin/lead-statuses`, `assigned_to=ID`, and `per_page=1..100`.

## 2. Search leads

Search checks lead code, name, company, email, mobile, and requirement.

```bash
curl --location --request GET '{{base_url}}/api/admin/leads/search?q=attendance&page=1&per_page=20' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}'
```

## 3. Get active sales employees

```bash
curl --location --request GET '{{base_url}}/api/admin/leads/sales-employees' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}'
```

Employees match a `sale`/`sales` role or sales designation. Results include `assigned_leads_count` and `deals_closed_count`.

## 4. Assign or reassign a lead

Replace `1092` with the numeric lead database `id` and `12` with a sales employee ID.

```bash
curl --location --request POST '{{base_url}}/api/admin/leads/1092/assign' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "employee_id": 12,
    "notes": "Assigned to Rahul for initial discovery call."
  }'
```

Calling this with another employee creates a `reassigned` history event.

## 5. Get lead details with history

```bash
curl --location --request GET '{{base_url}}/api/admin/leads/1092' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}'
```

## 6. Create a referral lead

Replace `12` with an ID returned by the sales-employees API.

```bash
curl --location --request POST '{{base_url}}/api/admin/leads' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "full_name": "Rohan Mehta",
    "company_name": "Mehta Enterprises",
    "email": "rohan.mehta@mehta.com",
    "mobile_number": "+91 9876543210",
    "designation": "Proprietor",
    "lead_source": "Referral",
    "lead_status": "Interested",
    "assign_to": 12,
    "requirement": "Custom attendance and payroll application",
    "location": "Mumbai, Maharashtra",
    "estimated_value": 250000,
    "expected_closing_date": "2026-11-30",
    "notes": "Referred by an existing client."
  }'
```

Accepted aliases: `name`/`full_name`, `phone`/`mobile_number`, `source`/`lead_source`, `status`/`lead_status`, and `assigned_to`/`assign_to`.

## Website sync setup

```bash
php artisan migrate
php artisan leads:sync-website
php artisan schedule:work
```

The Laravel scheduler runs the import every two minutes with overlap protection. In production, run `php artisan schedule:run` every minute using cron or Windows Task Scheduler. The importer reads all pages and prevents duplicates with the unique database key `(source, external_id)`.
