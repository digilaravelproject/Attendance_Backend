# Lead Source and Sales Employee API cURLs

Set these Postman environment variables first:

- `base_url`: `https://attendance.digiemperor.com`
- `access_token`: a valid admin or employee bearer token
- `sales_employee_id`: an ID returned by the sales employee list API
- `sales_employee_id_1` and `sales_employee_id_2`: employee IDs used for round-robin examples
- `lead_source_id`: an ID returned by the create/list lead-source API

These APIs use authentication only. They do not apply administrator-only or
employee-only role middleware.

## 1. Get all users and upcoming birthdays

`total` and `data` represent every row in the `users` table. `upcoming_total`
and `upcoming_data` represent birthdays within the requested number of days.

```bash
curl --location --request GET '{{base_url}}/api/admin/birthdays/upcoming?days=30' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}'
```

## 2. Get sales employees

```bash
curl --location --request GET '{{base_url}}/api/admin/leads/sales-employees' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}'
```

## 3. Get one sales employee's CRM dashboard and managed leads

```bash
curl --location --request GET '{{base_url}}/api/admin/leads/sales-employees/{{sales_employee_id}}?per_page=20' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}'
```

The response includes revenue, target, target-achievement percentage, average
and total talk seconds, admin-assigned/self-brought/active lead counts, lead
items, recent activity, and pagination.

## 4. Create a lead-source assignment rule

Allowed allocation strategies are `round_robin` and `primary`. Known names such
as Facebook Ads, Google Ads, JustDial, Website, and Referral automatically map
to their integration source key. `employee_ids` must contain active sales
employees returned by API 2.

```bash
curl --location --request POST '{{base_url}}/api/admin/lead-sources' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "name": "Facebook Ads",
    "source_key": "facebook",
    "allocation_strategy": "round_robin",
    "employee_ids": [{{sales_employee_id_1}}, {{sales_employee_id_2}}],
    "is_active": true,
    "color": "#3B82F6"
  }'
```

The mobile-screen aliases are also accepted:

```bash
curl --location --request POST '{{base_url}}/api/admin/lead-sources' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "lead_source_name": "JustDial",
    "strategy": "Round-Robin",
    "assigned_employee_ids": [{{sales_employee_id_1}}, {{sales_employee_id_2}}],
    "is_active": true
  }'
```

## 5. List lead sources

```bash
curl --location --request GET '{{base_url}}/api/admin/lead-sources?status=all' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}'
```

Use `status=active` or `status=inactive` to filter. `search=facebook` is also
supported.

## 6. Get a lead source by ID

```bash
curl --location --request GET '{{base_url}}/api/admin/lead-sources/{{lead_source_id}}' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}'
```

## 7. Update a lead-source rule

```bash
curl --location --request PATCH '{{base_url}}/api/admin/lead-sources/{{lead_source_id}}' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "name": "Facebook Ads",
    "source_key": "facebook",
    "allocation_strategy": "round_robin",
    "employee_ids": [{{sales_employee_id_1}}, {{sales_employee_id_2}}],
    "is_active": true,
    "color": "#3B82F6"
  }'
```

## 8. Activate or deactivate a lead source

Deactivate:

```bash
curl --location --request PATCH '{{base_url}}/api/admin/lead-sources/{{lead_source_id}}' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{"is_active": false}'
```

Activate:

```bash
curl --location --request PATCH '{{base_url}}/api/admin/lead-sources/{{lead_source_id}}' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{access_token}}' \
  --header 'Content-Type: application/json' \
  --data-raw '{"is_active": true}'
```

When Facebook, JustDial, or Website is inactive, its integration does not insert
incoming leads. When active, each new lead is assigned using the configured
`primary` or `round_robin` employee allocation strategy.

## 9. Test an active JustDial source

```bash
curl --location --request POST '{{base_url}}/api/justdial_leads' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "leadid": "JD-POSTMAN-1001",
    "leadtype": "category",
    "name": "Postman Test Lead",
    "mobile": "9876543210",
    "email": "postman-lead@example.com",
    "date": "2026-10-22",
    "time": "14:30:00",
    "category": "Attendance Software",
    "area": "Ghatkopar West",
    "city": "Mumbai",
    "company": "Postman Test Company"
  }'
```

Run the database migration before testing:

```bash
php artisan migrate --force
```
