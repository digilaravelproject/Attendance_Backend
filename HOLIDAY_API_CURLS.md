# Holiday API cURL Commands

Base URL used below: `http://127.0.0.1:8000`

Run the migration once before testing:

```bash
php artisan migrate
```

Login first and copy the `access_token` value from the response. Use an administrator account for create, detail, update, and delete. The yearly listing accepts either an administrator or employee token.

## 0. Login

```bash
curl --location 'http://127.0.0.1:8000/api/admin/login' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{
  "email": "admin@example.com",
  "password": "your_password"
}'
```

## 1. Add holiday (admin)

Valid `type` values are `National`, `Restricted`, and `Optional`. The API also accepts the UI labels `National Holiday`, `Restricted Holiday`, and `Optional Holiday`.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/holidays' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer YOUR_ADMIN_TOKEN' \
--data-raw '{
  "name": "Republic Day",
  "date": "2027-01-26",
  "type": "National Holiday",
  "location": "All Locations",
  "repeat_every_year": true,
  "description": "National holiday commemorating the Constitution of India."
}'
```

## 2. Get holiday listing (admin)

```bash
curl --location 'http://127.0.0.1:8000/api/admin/holidays?year=2027&location=All%20Locations' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_ADMIN_TOKEN'
```

The response contains `summary`, month-grouped `months`, and a flat `data` array. Every item includes the database `id`, `name`, `date`, `day`, `day_name`, `type`, `location`, `repeat_every_year`, and `description`.

## 3. Get holiday listing (employee)

This is the same database-backed API for the employee Holiday Calendar screen.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/holidays?year=2027&location=All%20Locations' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_EMPLOYEE_TOKEN'
```

For a location-specific calendar, use a value such as `location=Pune`. The response includes holidays for Pune plus holidays assigned to `All Locations`.

## 4. Get holiday by ID (admin)

Replace `1` with the `id` returned by Add Holiday or Get Holiday Listing.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/holidays/1' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_ADMIN_TOKEN'
```

## 5. Update holiday (admin)

`PATCH` supports partial updates. Only fields included in the JSON body are changed.

```bash
curl --location --request PATCH 'http://127.0.0.1:8000/api/admin/holidays/1' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer YOUR_ADMIN_TOKEN' \
--data-raw '{
  "name": "Republic Day",
  "date": "2027-01-26",
  "type": "National",
  "location": "All Locations",
  "repeat_every_year": true,
  "description": "Updated holiday description."
}'
```

A full `PUT` update is also supported:

```bash
curl --location --request PUT 'http://127.0.0.1:8000/api/admin/holidays/1' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer YOUR_ADMIN_TOKEN' \
--data-raw '{
  "name": "Republic Day",
  "date": "2027-01-26",
  "type": "National",
  "location": "All Locations",
  "repeat_every_year": true,
  "description": "Updated holiday description."
}'
```

## 6. Delete holiday (admin)

```bash
curl --location --request DELETE 'http://127.0.0.1:8000/api/admin/holidays/1' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_ADMIN_TOKEN'
```