# Admin, Documents, Roles & Permissions API

## One-time setup

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Base URL used below: `http://127.0.0.1:8000`

Seeded admin credentials:

- Email: `admin@empmanagement.com`
- Password: `admin123`

After login, copy `access_token` from the response and replace `<ACCESS_TOKEN>` below. Replace `<ROLE_ID>` and `<DOCUMENT_ID>` with IDs returned by the corresponding APIs.

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

## 2. Get admin profile and all uploaded documents

```bash
curl --location "http://127.0.0.1:8000/api/admin/profile" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

## 3. Update admin profile and optionally upload documents

Use `POST` for multipart uploads. The `documents[]` fields are optional and may be omitted. Up to 10 files can be sent per request; each file can be up to 10 MB. Allowed extensions are PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, and PNG.

```bash
curl --location "http://127.0.0.1:8000/api/admin/update-profile" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --form "name=Administrator" \
  --form "company_name=Employee Management" \
  --form "mobile_number=9876543210" \
  --form "department=Management" \
  --form "designation=Administrator" \
  --form "address=Pune, Maharashtra" \
  --form "documents[]=@C:/path/to/document.pdf" \
  --form "documents[]=@C:/path/to/identity.jpg"
```

## 4. Delete one admin document

The admin can delete only a document belonging to their own account.

```bash
curl --location --request DELETE "http://127.0.0.1:8000/api/admin/documents/<DOCUMENT_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

## 5. Get total permission and category counts

```bash
curl --location "http://127.0.0.1:8000/api/admin/permissions/total" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

The supplied permission list contains 83 permissions in 13 categories.

## 6. Get all permissions grouped for the role form

```bash
curl --location "http://127.0.0.1:8000/api/admin/permissions" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

## 7. Get permissions with assigned status for one role

```bash
curl --location "http://127.0.0.1:8000/api/admin/permissions/<ROLE_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

Equivalent route:

```bash
curl --location "http://127.0.0.1:8000/api/admin/roles/<ROLE_ID>/permissions" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

## 8. Create a role

First call the permission-list API and use its permission IDs. An empty array is valid if no permissions should initially be granted.

```bash
curl --location "http://127.0.0.1:8000/api/admin/roles" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "name": "Senior Flutter Developer",
    "department": "Engineering",
    "description": "Builds and maintains mobile applications",
    "status": true,
    "permission_ids": []
  }'
```

## 9. Get all roles

```bash
curl --location "http://127.0.0.1:8000/api/admin/roles" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

## 10. Search roles

```bash
curl --location --get "http://127.0.0.1:8000/api/admin/roles/search" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --data-urlencode "query=Flutter"
```

## 11. Get role by ID

```bash
curl --location "http://127.0.0.1:8000/api/admin/roles/<ROLE_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

## 12. Update a role and its permissions

Replace the sample IDs with IDs returned by `GET /api/admin/permissions`.

```bash
curl --location --request PUT "http://127.0.0.1:8000/api/admin/roles/<ROLE_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "name": "Senior Flutter Developer",
    "department": "Engineering",
    "description": "Leads Flutter application development",
    "status": true,
    "permission_ids": [1, 2, 3]
  }'
```

## 13. Replace only a role's assigned permissions

```bash
curl --location "http://127.0.0.1:8000/api/admin/roles/<ROLE_ID>/permissions" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "permission_ids": [1, 2, 3]
  }'
```

Alternative route accepting `role_id` in the body:

```bash
curl --location "http://127.0.0.1:8000/api/admin/roles/assign-permissions" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --header "Content-Type: application/json" \
  --data-raw '{
    "role_id": <ROLE_ID>,
    "permission_ids": [1, 2, 3]
  }'
```

## 14. Delete a role

```bash
curl --location --request DELETE "http://127.0.0.1:8000/api/admin/roles/<ROLE_ID>" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>"
```

## 15. Admin logout

```bash
curl --location "http://127.0.0.1:8000/api/admin/logout" \
  --header "Accept: application/json" \
  --header "Authorization: Bearer <ACCESS_TOKEN>" \
  --request POST
```

## Removed role APIs

The following role-related APIs no longer exist:

- `GET /api/admin/users`
- `DELETE /api/admin/roles/{roleId}/users/{userId}`
- `POST|DELETE /api/admin/roles/{roleId}/remove-user`
