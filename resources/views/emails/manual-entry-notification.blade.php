<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .alert-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-table td:first-child {
            font-weight: bold;
            width: 180px;
            color: #6b7280;
        }
        .footer {
            background: #f3f4f6;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-radius: 0 0 8px 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0;">⚠️ Manual Entry Alert</h1>
        <p style="margin: 5px 0 0 0; opacity: 0.9;">{{ $eventType }} Registration</p>
    </div>
    
    <div class="content">
        <div class="alert-box">
            <strong>⚠️ Attention Required</strong>
            <p style="margin: 10px 0 0 0;">A user has registered for {{ $eventType }} with a membership number that was <strong>not found in the system</strong>. They entered their details manually.</p>
        </div>

        <h3 style="color: #374151; margin-top: 30px;">Registration Details:</h3>
        
        <table class="info-table">
            <tr>
                <td>Event Type:</td>
                <td><strong>{{ $eventType }}</strong></td>
            </tr>
            <tr>
                <td>Membership Number:</td>
                <td><strong>{{ $membershipNumber }}</strong></td>
            </tr>
            <tr>
                <td>Full Name:</td>
                <td>{{ $fullName }}</td>
            </tr>
            <tr>
                <td>Email Address:</td>
                <td>{{ $email }}</td>
            </tr>
            <tr>
                <td>Mobile Number:</td>
                <td>{{ $mobile }}</td>
            </tr>
            @if($mealPreference)
            <tr>
                <td>Meal Preference:</td>
                <td>{{ ucfirst(str_replace('_', ' ', $mealPreference)) }}</td>
            </tr>
            @endif
            <tr>
                <td>Registration Time:</td>
                <td>{{ now()->format('F j, Y \a\t h:i A') }}</td>
            </tr>
        </table>

        <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin-top: 20px; border-radius: 4px;">
            <strong>ℹ️ Action Required:</strong>
            <p style="margin: 10px 0 0 0;">Please verify this membership number in your records. If the member is valid, consider adding them to the member_details table. If this is suspicious activity, you may want to review or remove this registration.</p>
        </div>
    </div>
    
    <div class="footer">
        <p style="margin: 0;">This is an automated notification from SLIA Event Registration System</p>
        <p style="margin: 5px 0 0 0;">{{ config('app.name') }} &copy; {{ date('Y') }}</p>
    </div>
</body>
</html>
