<!DOCTYPE html>
<html>
<head>
    <title>AGM Pass - {{ $membership }}</title>
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
            color: #1e40af;
            font-size: 22px;
            margin: 0 0 5px 0;
        }
        .header h2 {
            color: #3b82f6;
            font-size: 15px;
            margin: 0;
            font-weight: normal;
        }
        .columns {
            display: flex;
            gap: 40px;
            margin-bottom: 10px;
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
        }
        .value {
            font-size: 15px;
            color: #333;
        }
        .meal {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 14px;
        }
        .veg {
            background: #10b981;
            color: white;
        }
        .non-veg {
            background: #ef4444;
            color: white;
        }
        .qr-area {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .qr-code {
            width: 150px;
            height: 150px;
            margin: 10px auto;
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
        }
        .meal-section {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 12px 15px;
            margin: 15px 0 25px 0;
            border-radius: 0 5px 5px 0;
        }
        .meal-section h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #065f46;
        }
    </style>
</head>

<body>
<div class="pass">

    <!-- Header -->
    <div class="header">
        <div class="id">ID: {{ $registration_id }}</div>
        <h1>SRI LANKA INSTITUTE OF ARCHITECTS</h1>
        <h2>Annual General Meeting – Attendance Pass</h2>
    </div>

    <!-- Personal Details -->
    <div class="columns">
        <div class="column">
            <div class="detail">
                <div class="label">Membership No.</div>
                <div class="value">{{ $membership }}</div>
            </div>

            <div class="detail">
                <div class="label">Name</div>
                <div class="value">{{ $name }}</div>
            </div>

            <div class="detail">
                <div class="label">Email</div>
                <div class="value">{{ $email }}</div>
            </div>

            <div class="detail">
                <div class="label">Mobile</div>
                <div class="value">{{ $mobile }}</div>
            </div>
        </div>
    </div>

    <!-- Meal Preference (Below Personal Data) -->
    <div class="meal-section">
        <h4>Meal Preference</h4>

        @if($meal_preference === 'veg')
            <span class="meal veg">Vegetarian</span>
        @elseif($meal_preference === 'non_veg')
            <span class="meal non-veg">Non-Vegetarian</span>
        @else
            <span class="meal">Not Specified</span>
        @endif
    </div>

    <!-- QR Code -->
    <div class="qr-area">
        <div style="font-weight: bold; color: #1e40af; margin-bottom: 8px;">
            Scan QR Code at Entrance (Attendance & Meal)
        </div>
        <img src="{{ $qr }}" alt="QR Code" class="qr-code">
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Official AGM Attendance Pass – Valid for one person only</p>
        <p>© {{ date('Y') }} SLIA | sliaoffice2@gmail.com | 077 764 6289</p>
    </div>

</div>
</body>
</html>
