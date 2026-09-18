<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to the Team</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fc;
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .header {
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #2563eb 100%);
            color: #ffffff;
            padding: 35px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 8px 0 0 0;
            font-size: 15px;
            opacity: 0.9;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
        }
        .intro-text {
            font-size: 15px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 25px;
        }
        .card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 2px solid #2563eb;
            display: inline-block;
            padding-bottom: 4px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 8px 0;
            font-size: 14px;
            vertical-align: top;
        }
        .info-table td.label {
            font-weight: 600;
            color: #64748b;
            width: 40%;
        }
        .info-table td.value {
            color: #0f172a;
            font-weight: 500;
        }
        .highlight-box {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 25px;
        }
        .highlight-box p {
            margin: 0;
            font-size: 14px;
            color: #1e40af;
            line-height: 1.5;
        }
        .btn-container {
            text-align: center;
            margin: 30px 0 15px 0;
        }
        .btn {
            background-color: #2563eb;
            color: #ffffff !important;
            padding: 14px 32px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            border-radius: 6px;
            display: inline-block;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            transition: background-color 0.3s ease;
        }
        .footer {
            background-color: #f1f5f9;
            padding: 20px 30px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
        .eyebrow {
            display: inline-block;
            padding: 6px 12px;
            margin-bottom: 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.16);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .credential {
            font-family: Consolas, monospace;
            background: #e0e7ff;
            color: #1e3a8a;
            padding: 5px 8px;
            border-radius: 5px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="eyebrow">Employee onboarding</div>
            <h1>Welcome to {{ config('app.name', 'Attendance System') }}</h1>
            <p>We are excited to have you on board!</p>
        </div>

        <div class="content">
            <div class="greeting">Hello {{ $employee->name }},</div>

            <p class="intro-text">
                Your employee account has been created successfully. Below are your account details and login credentials to access the Attendance Management System.
            </p>

            <div class="card">
                <div class="card-title">Employee Profile Details</div>
                <table class="info-table">
                    <tr>
                        <td class="label">Employee ID:</td>
                        <td class="value"><strong>{{ $employee->employee_id }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Full Name:</td>
                        <td class="value">{{ $employee->name }}</td>
                    </tr>
                    <tr>
                        <td class="label">Designation:</td>
                        <td class="value">{{ $employee->designationDetails->name ?? $employee->designation ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Department:</td>
                        <td class="value">{{ $employee->department ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Work Arrangement:</td>
                        <td class="value">{{ $employee->work_mode ?? 'N/A' }} · {{ $employee->employee_type ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Mobile Number:</td>
                        <td class="value">{{ $employee->mobile_number }}</td>
                    </tr>
                    <tr>
                        <td class="label">Date of Joining:</td>
                        <td class="value">{{ $employee->date_of_joining ? \Carbon\Carbon::parse($employee->date_of_joining)->format('M d, Y') : 'N/A' }}</td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <div class="card-title">Portal Access Credentials</div>
                <table class="info-table">
                    <tr>
                        <td class="label">Portal Email:</td>
                        <td class="value"><span class="credential">{{ $employee->email }}</span></td>
                    </tr>
                    @if(!empty($password))
                    <tr>
                        <td class="label">Temporary Password:</td>
                        <td class="value"><span class="credential">{{ $password }}</span></td>
                    </tr>
                    @endif
                </table>
            </div>

            <div class="highlight-box">
                <p><strong>First login:</strong> Use the credentials above, then change your temporary password immediately. Never share it with anyone.</p>
            </div>

            <div class="btn-container">
                <a href="{{ config('app.url') }}" class="btn" target="_blank">Access Portal Now</a>
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Attendance System') }}. All rights reserved.</p>
            <p>If you have any questions, please contact your HR or IT Support team.</p>
        </div>
    </div>
</body>
</html>
