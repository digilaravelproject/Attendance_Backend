# Employee Portal API — Complete cURL Collection

Default local URL used below: `http://127.0.0.1:8000`

Replace these placeholders before sending protected requests:

- `ADMIN_TOKEN`: `access_token` returned by admin login.
- `EMPLOYEE_TOKEN`: `access_token` returned by employee login.
- Example numeric IDs (`1`) with the correct IDs from your database.

## 1. Admin login

```bash
curl --location 'http://127.0.0.1:8000/api/admin/login' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{
  "email": "admin@empmanagement.com",
  "password": "admin123"
}'
```

Copy `access_token` from the response and use it as `ADMIN_TOKEN`.

## 2. List available roles and permissions

Use this request to get the role ID you want to assign to the employee.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/roles' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer ADMIN_TOKEN'
```

## 3. Create employee and send welcome email

The password is generated automatically from the employee name. For the example name `Rahul Sharma`, the generated password is `rahulsharma@123`. The response never exposes this password; it is sent in the welcome email.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/employees' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer ADMIN_TOKEN' \
--data-raw '{
  "employee_id": "EMP-2026-007",
  "name": "Rahul Sharma",
  "gender": "Male",
  "date_of_birth": "1996-05-15",
  "marital_status": "Single",
  "blood_group": "O+",
  "mobile_number": "9876543210",
  "alternate_mobile_number": "9876500001",
  "email": "rahul@example.com",
  "emergency_contact": "9876500000",
  "street_address": "Flat 402, Sunshine Heights",
  "city": "Bengaluru",
  "postal_code": "560038",
  "state": "Karnataka",
  "country": "India",
  "work_mode": "Office",
  "employee_type": "Full-time",
  "department": "Engineering",
  "designation_id": 1,
  "team": "Team Alpha",
  "assigned_shift_id": 1,
  "date_of_joining": "2026-09-19",
  "employment_status": "Active",
  "probation_period": "3 Months",
  "notice_period": "30 Days",
  "salary_type": "Monthly",
  "monthly_base_salary": 60000,
  "sales_target_enabled": false,
  "account_holder_name": "Rahul Sharma",
  "bank_name": "HDFC Bank",
  "account_number": "50100456789123",
  "ifsc_code": "HDFC0001234",
  "branch_name": "Main Branch",
  "skills": ["Flutter", "Dart", "Teamwork"],
  "role_ids": [1]
}'
```

The endpoint also accepts `multipart/form-data` when an `avatar` image must be uploaded.

## 4. Employee login using the shared Signin endpoint

```bash
curl --location 'http://127.0.0.1:8000/api/admin/login' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{
  "email": "rahul@example.com",
  "password": "rahulsharma@123"
}'
```

The login response includes the full employee profile, designation, department, assigned shift, assigned roles, flat permissions, `permission_ids`, grouped permissions, and an `access_token`. Copy the token and use it as `EMPLOYEE_TOKEN`.

## 5. Employee forgot password using the shared endpoint

```bash
curl --location 'http://127.0.0.1:8000/api/admin/forgot-password' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{
  "email": "rahul@example.com"
}'
```

## 6. Employee reset password using the shared endpoint

Replace `123456` with the OTP sent to the employee email.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/reset-password' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--data-raw '{
  "email": "rahul@example.com",
  "otp": "123456",
  "password": "NewPassword@123",
  "password_confirmation": "NewPassword@123"
}'
```

## 7. Get logged-in employee profile and permissions

```bash
curl --location 'http://127.0.0.1:8000/api/admin/profile' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

## 8. Update employee profile using the shared endpoint

```bash
curl --location 'http://127.0.0.1:8000/api/admin/update-profile' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN' \
--form 'name="Rahul Sharma"' \
--form 'email="rahul@example.com"' \
--form 'phone="+91 98765 43210"' \
--form 'department="Design"' \
--form 'designation="UI/UX Designer"' \
--form 'employee_id="EMP1025"' \
--form 'address="Pune, Maharashtra"' \
--form 'avatar=@"C:/path/to/avatar.jpg"' \
--form 'documents[]=@"C:/path/to/identity.pdf"'
```

Remove the `avatar` or `documents[]` lines when no files need to be uploaded.

## 9. Update employee password using the shared endpoint

```bash
curl --location 'http://127.0.0.1:8000/api/admin/update-password' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN' \
--data-raw '{
  "current_password": "rahulsharma@123",
  "new_password": "NewPassword@123",
  "confirm_password": "NewPassword@123"
}'
```

## 10. Delete the logged-in employee's document

Replace `1` with the document ID returned in the profile response.

```bash
curl --location --request DELETE 'http://127.0.0.1:8000/api/admin/documents/1' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

## 11. Mark attendance / check in

Location and notes are optional. An empty JSON body is also valid.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/attendance/check-in' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN' \
--data-raw '{
  "latitude": 18.5204,
  "longitude": 73.8567,
  "notes": "Checked in from the Pune office"
}'
```

Alias: `POST /api/admin/attendance/mark`

## 12. Mark logout / check out

```bash
curl --location 'http://127.0.0.1:8000/api/admin/attendance/check-out' \
--header 'Accept: application/json' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN' \
--data-raw '{
  "latitude": 18.5204,
  "longitude": 73.8567,
  "notes": "Work completed"
}'
```

Alias: `POST /api/admin/attendance/mark-logout`

## 13. Display employee dashboard

Returns the greeting, employee card, current shift, today's check-in/check-out, today's birthdays, working time, break time, overtime, and status.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/dashboard' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

## 14. Get upcoming birthdays

`days` is optional, defaults to `30`, and accepts `0` through `365`.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/birthdays/upcoming?days=30' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

## 15. Get attendance history

`month` must be in `YYYY-MM` format. If omitted, the current month is returned.

```bash
curl --location 'http://127.0.0.1:8000/api/admin/attendance/history?month=2026-09' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

The response contains present, half-day, absent, leave and weekend totals; working days; attendance percentage; every calendar date; and recent check-in/out records.

## 16. Employee logout from the panel using the shared endpoint

This revokes the current employee API token. It is different from attendance check-out.

```bash
curl --location --request POST 'http://127.0.0.1:8000/api/admin/logout' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer EMPLOYEE_TOKEN'
```

## Mail configuration

The current project must use a real mail transport to deliver the welcome email. Configure SMTP values in `.env`, for example:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="Attendance"
```

Then run `php artisan config:clear`.
