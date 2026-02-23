<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: Helvetica, sans-serif; margin: 0; padding: 0; color: #1a1a1a; }
        .pass-container { width: 100%; border: 2px solid #4f46e5; border-radius: 15px; overflow: hidden; }
        .header { background-color: #4f46e5; color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; text-transform: uppercase; letter-spacing: 2px; }
        .header p { margin: 10px 0 0; opacity: 0.9; }
        .content { padding: 40px; position: relative; }
        .qr-code { position: absolute; top: 40px; right: 40px; }
        .info-section { margin-top: 20px; }
        .info-item { margin-bottom: 25px; }
        .label { font-size: 12px; color: #666; text-transform: uppercase; font-weight: bold; margin-bottom: 5px; }
        .value { font-size: 20px; font-weight: bold; color: #111827; }
        .footer { background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb; font-size: 11px; color: #6b7280; }
        .event-details { background-color: #eff6ff; padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid #bfdbfe; }
        .status-badge { display: inline-block; padding: 5px 15px; background-color: #10b981; color: white; border-radius: 20px; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="pass-container">
        <div class="header">
            <p>OFFICIAL ENTRY PASS</p>
            <h1>Members Night 2026</h1>
        </div>
        
        <div class="content">
            <div class="qr-code">
                <img src="{{ $qrCode }}" width="180">
            </div>

            <div class="event-details">
                <p style="margin: 0; font-size: 14px; font-weight: bold; color: #1e40af;">DATE: FEBRUARY 2026</p>
                <p style="margin: 5px 0 0; font-size: 14px; color: #1e40af;">VENUE: BMICH, COLOMBO</p>
            </div>

            <div class="info-section">
                <div class="info-item">
                    <div class="label">Participant Name</div>
                    <div class="value">{{ $name }}</div>
                </div>
                
                <div class="info-item">
                    <div class="label">Reference ID</div>
                    <div class="value">FELL-{{ $registration->id }}</div>
                </div>

                <div class="info-item">
                    <div class="label">Registration Category</div>
                    <div class="value">{{ ucwords(str_replace('_', ' ', $registration->category)) }}</div>
                </div>

                <div class="info-item">
                    <div class="label">Payment Status</div>
                    <div class="status-badge">PAID</div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This pass is mandatory for entry. Fraudulent reproduction is strictly prohibited.</p>
            <p>Sri Lanka Institute of Architects (SLIA) &copy; 2026</p>
        </div>
    </div>
</body>
</html>
