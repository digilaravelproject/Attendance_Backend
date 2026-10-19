# Lead Status and Integration Status API cURLs

Set `{{base_url}}` to `https://attendance.digiemperor.com` and set
`{{admin_access_token}}` from the login response. All APIs in this document
require an authenticated administrator token.

## 0. Admin login

```bash
curl --location --request POST '{{base_url}}/api/admin/login' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data-raw '{
    "email": "admin@example.com",
    "password": "YOUR_PASSWORD"
  }'
```

## 1. Create lead status

Allowed categories are `prospecting`, `active_lead`, `won`, and `lost`.
`badge_color` must be a six-digit hexadecimal color.

```bash
curl --location --request POST '{{base_url}}/api/admin/lead-statuses' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}' \
  --data-raw '{
    "name": "In Progress",
    "category": "active_lead",
    "description": "Technical evaluation or contract review is in progress.",
    "badge_color": "#6366F1",
    "order_position": 4
  }'
```

The screenshot field aliases are also accepted: `status_name`, `guideline`, and
`tag_color`. `Active Lead (In negotiation)` is accepted as the category and is
normalized to `active_lead`.

## 2. Get all lead statuses and total

```bash
curl --location --request GET '{{base_url}}/api/admin/lead-statuses' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}'
```

Optional filters: `?category=active_lead` and `?q=progress`.

## 3. Get lead-status totals

```bash
curl --location --request GET '{{base_url}}/api/admin/lead-statuses/total' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}'
```

## 4. Get lead status by ID

```bash
curl --location --request GET '{{base_url}}/api/admin/lead-statuses/12' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}'
```

## 5. Update lead status

```bash
curl --location --request PATCH '{{base_url}}/api/admin/lead-statuses/12' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}' \
  --data-raw '{
    "name": "Negotiation",
    "category": "active_lead",
    "description": "Commercial negotiation is currently in progress.",
    "badge_color": "#8B5CF6",
    "order_position": 4
  }'
```

If the generated slug changes, leads using the old slug are updated automatically.

## 6. Delete an unused lead status

```bash
curl --location --request DELETE '{{base_url}}/api/admin/lead-statuses/12' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}'
```

If the status has leads, provide another status ID. Existing leads are moved to
the replacement before deletion.

```bash
curl --location --request DELETE '{{base_url}}/api/admin/lead-statuses/12' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}' \
  --data-raw '{
    "replacement_status_id": 2
  }'
```

The default `new` status is protected and cannot be deleted.

## 7. Get Facebook and Google connection status

```bash
curl --location --request GET '{{base_url}}/api/admin/lead-integrations/status' \
  --header 'Accept: application/json' \
  --header 'Authorization: Bearer {{admin_access_token}}'
```

Facebook is connected only when an active integration has a page ID, page token,
and a non-expired user token. Google returns `not_configured` because this backend
does not currently contain a Google Ads OAuth integration. Historical Google lead
counts are returned separately and do not imply an active connection.
