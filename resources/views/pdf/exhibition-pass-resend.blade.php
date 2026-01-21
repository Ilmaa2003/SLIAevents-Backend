<!DOCTYPE html>
<html>
<head>
    <title>Exhibition Pass - {{ $membership }}</title>
    <style>
        body { 
            font-family: 'Arial', sans-serif; 
            margin: 40px;
            padding: 0;
        }
        .pass {
            max-width: 700px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
            border-radius: 8px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #7c3aed;
            font-size: 22px;
            margin: 0 0 5px 0;
        }
        .header h2 {
            color: #a855f7;
            font-size: 15px;
            margin: 0;
            font-weight: normal;
        }
        .columns {
            display: flex;
            gap: 40px;
            margin-bottom: 20px;
        }
        .column {
            flex: 1;
        }
        .detail {
            margin-bottom: 12px;
        }
        .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .value {
            font-size: 15px;
            color: #333;
            font-weight: 500;
        }
        .qr-area {
            text-align: center;
            padding: 25px;
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            border-radius: 8px;
            margin: 25px 0;
            border: 1px solid #ddd6fe;
        }
        .qr-code {
            width: 160px;
            height: 160px;
            margin: 15px auto;
        }
        .footer {
            text-align: center;
            color: #777;
            font-size: 12px;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .id {
            float: right;
            font-size: 20px;
            color: #666;
            font-weight: bold;
        }
        .event-info {
            background: #f8fafc;
            border-left: 4px solid #8b5cf6;
            padding: 12px 15px;
            margin: 15px 0 20px 0;
            border-radius: 0 5px 5px 0;
        }
        .event-info h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #5b21b6;
        }
        .event-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        .pass-type {
            display: inline-block;
            padding: 6px 16px;
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            margin-top: 10px;
        }
        .disclaimer {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 6px;
            padding: 12px;
            margin: 15px 0;
            font-size: 12px;
            color: #92400e;
        }
        .disclaimer strong {
            color: #78350f;
        }
        .event-name {
            font-size: 16px;
            font-weight: bold;
            color: #7c3aed;
            margin-bottom: 5px;
        }
    </style>
</head>

<body>
<div class="pass">

    <!-- Header -->
    <div class="header">
        <div class="id">ID: {{ $registration_id }}</div>
        <h1>SRI LANKA INSTITUTE OF ARCHITECTS</h1>
        <h2>{{ $event_name ?? 'Annual Exhibition 2026' }}</h2>
    </div>

    <!-- Event Information -->
    <div class="event-info">
        <div class="event-name">{{ $pass_type ?? 'Exhibition Entry Pass' }}</div>
        <div style="color: #6d28d9; font-size: 13px;">Valid for exhibition entry only</div>
    </div>

    <!-- Personal Details -->
    <div class="columns">
        <div class="column">
            <div class="detail">
                <div class="label">Membership No.</div>
                <div class="value">{{ $membership }}</div>
            </div>

            <div class="detail">
                <div class="label">Full Name</div>
                <div class="value">{{ $name }}</div>
            </div>

            <div class="detail">
                <div class="label">Email Address</div>
                <div class="value">{{ $email }}</div>
            </div>

            <div class="detail">
                <div class="label">Mobile Number</div>
                <div class="value">{{ $mobile }}</div>
            </div>
        </div>
    </div>

    <!-- Exhibition Disclaimer -->
    <div class="disclaimer">
        <strong>⚠️ Important:</strong> This pass provides entry to the exhibition only. 
        Food, beverages, and refreshments are available for separate purchase at the venue.
    </div>

    <!-- QR Code -->
    <div class="qr-area">
        <div style="font-weight: bold; color: #5b21b6; margin-bottom: 10px; font-size: 14px;">
            Scan QR Code at Exhibition Entrance
        </div>
        <div style="color: #6b7280; font-size: 12px; margin-bottom: 15px;">
            One-time entry • Non-transferable
        </div>
        <img src="{{ $qr }}" alt="Exhibition QR Code" class="qr-code">
        <div>
            <span class="pass-type">Exhibition Entry Only</span>
        </div>
    </div>

    <!-- Event Details -->
    <div class="event-details">
        <div class="detail">
            <div class="label">Registration Date</div>
            <div class="value">{{ $date }}</div>
        </div>
        <div class="detail">
            <div class="label">Registration Time</div>
            <div class="value">{{ $time }}</div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Official Exhibition Entry Pass – Valid for one person only</p>
        <p>SLIA Exhibition Hours: 9:00 AM - 6:00 PM | Please arrive 15 minutes before closing</p>
        <p>© {{ date('Y') }} SLIA | sliaoffice2@gmail.com | +94 77 764 6289</p>
    </div>

</div>
</body>
</html>