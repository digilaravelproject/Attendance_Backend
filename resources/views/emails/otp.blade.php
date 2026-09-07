<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fc;
            margin: 0;
            padding: 0;
            color: #333333;
        }
        .container {
            max-width: 560px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }
        .header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            padding: 35px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 8px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 35px 30px;
            text-align: center;
        }
        .content p {
            font-size: 15px;
            line-height: 1.6;
            color: #4b5563;
            margin-bottom: 25px;
        }
        .otp-card {
            background: #f0f5ff;
            border: 2px dashed #3b82f6;
            border-radius: 10px;
            padding: 20px;
            margin: 25px 0;
            display: inline-block;
            width: 80%;
        }
        .otp-code {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: 12px;
            color: #1e40af;
            margin: 0;
            font-family: 'Courier New', Courier, monospace;
        }
        .otp-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            margin-top: 5px;
            font-weight: 600;
        }
        .warning {
            font-size: 13px;
            color: #ef4444;
            background: #fef2f2;
            padding: 10px 15px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 10px;
        }
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset Request</h1>
            <p>Attendance Management Portal</p>
        </div>
        <div class="content">
            <p>Hello {{ $name ?? 'User' }},</p>
            <p>We received a request to reset your password. Use the 6-digit Verification Code below to set a new password:</p>
            
            <div class="otp-card">
                <div class="otp-code">{{ $otp }}</div>
                <div class="otp-label">Verification Code</div>
            </div>

            <div class="warning">
                ⏱️ This code is valid for <strong>15 minutes</strong>. Do not share it with anyone.
            </div>

            <p style="margin-top: 25px; font-size: 13px; color: #6b7280;">
                If you did not request a password reset, please ignore this email or contact support if you have concerns.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Attendance Portal. All rights reserved.
        </div>
    </div>
</body>
</html>
