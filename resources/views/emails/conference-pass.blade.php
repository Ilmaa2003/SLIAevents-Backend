<!DOCTYPE html>
<html>
<head>
    <title>Conference Pass</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .pass { border: 2px solid #1e40af; padding: 20px; max-width: 600px; margin: 0 auto; }
        h1 { color: #1e40af; text-align: center; }
        .info { margin: 20px 0; }
        .label { font-weight: bold; color: #4b5563; }
    </style>
</head>
<body>
    <div class="pass">
        <h1>SLIA Conference 2026 Pass</h1>
        <div class="info">
            <div><span class="label">Name:</span> {{ \->full_name }}</div>
            <div><span class="label">Membership:</span> {{ \->membership_number }}</div>
            <div><span class="label">Category:</span> {{ \->category }}</div>
            <div><span class="label">Email:</span> {{ \->email }}</div>
            <div><span class="label">Payment Ref:</span> {{ \->payment_ref_no ?? 'TEST' }}</div>
        </div>
        <div style="text-align: center; margin: 20px 0;">
            {!! \ !!}
        </div>
        <div style="text-align: center; color: #666; font-size: 12px;">
            Generated on {{ now()->format('Y-m-d H:i:s') }}
        </div>
    </div>
</body>
</html>
