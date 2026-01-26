<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLIA Conference Registration Pass</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: 3px solid #1e40af;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #1e40af;
            margin: 0;
            font-size: 28px;
        }
        .header h2 {
            color: #4b5563;
            margin: 5px 0 0 0;
            font-size: 20px;
        }
        .logo {
            height: 80px;
            margin-bottom: 15px;
        }
        .content {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 30px;
        }
        .details {
            flex: 2;
            min-width: 300px;
        }
        .qr-section {
            flex: 1;
            min-width: 200px;
            text-align: center;
        }
        .section-title {
            color: #1e40af;
            font-size: 18px;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .info-item {
            margin-bottom: 12px;
        }
        .info-label {
            font-weight: bold;
            color: #4b5563;
            font-size: 14px;
            display: block;
            margin-bottom: 3px;
        }
        .info-value {
            font-size: 16px;
            color: #1f2937;
            padding: 8px 12px;
            background-color: #f9fafb;
            border-radius: 6px;
            border-left: 4px solid #1e40af;
        }
        .qr-code {
            margin: 0 auto 15px;
            padding: 10px;
            background: white;
            border: 2px dashed #1e40af;
            border-radius: 10px;
            display: inline-block;
        }
        .qr-code img {
            max-width: 180px;
            height: auto;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 5px 15px;
            background-color: #10b981;
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            margin: 10px 0;
        }
        .important-notes {
            background-color: #fef3c7;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #f59e0b;
        }
        .important-notes h4 {
            color: #92400e;
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .important-notes ul {
            margin: 0;
            padding-left: 20px;
        }
        .important-notes li {
            margin-bottom: 5px;
            font-size: 13px;
            color: #92400e;
        }
        .registration-id {
            font-family: monospace;
            font-size: 18px;
            background-color: #1f2937;
            color: #fbbf24;
            padding: 10px 15px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            letter-spacing: 1px;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .container {
                box-shadow: none;
                border: 2px solid #1e40af;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <!-- Add your logo here or remove this if you don't have one -->
            <div style="text-align: center; margin-bottom: 15px;">
                <div style="font-size: 24px; font-weight: bold; color: #1e40af;">SLIA</div>
                <div style="font-size: 14px; color: #4b5563;">Sri Lanka Institute of Architects</div>
            </div>
            <h1>National Conference 2026</h1>
            <h2>Registration & Attendance Pass</h2>
            <div class="badge">PAID & CONFIRMED</div>
        </div>
        
        <div class="registration-id">
            PASS ID: CONF-{{ str_pad($registration->id, 6, '0', STR_PAD_LEFT) }}
        </div>
        
        <div class="content">
            <div class="details">
                <div class="section-title">Registration Details</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <div class="info-value">{{ $registration->full_name }}</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Membership Number</span>
                        <div class="info-value">{{ $registration->membership_number }}</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Address</span>
                        <div class="info-value">{{ $registration->email }}</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Mobile Number</span>
                        <div class="info-value">{{ $registration->phone }}</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Registration Category</span>
                        <div class="info-value">
                            @if($registration->category == 'slia_member')
                                SLIA Member (Special Rate)
                            @elseif($registration->category == 'general_public')
                                General Public
                            @else
                                International Participant
                            @endif
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Reference</span>
                        <div class="info-value">{{ $registration->payment_ref_no ?? 'TEST MODE' }}</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Registration Date</span>
                        <div class="info-value">{{ $registration->created_at->format('F j, Y') }}</div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Lunch Included</span>
                        <div class="info-value">{{ $registration->include_lunch ? 'Yes' : 'No' }}</div>
                    </div>
                </div>
                
                <div class="important-notes">
                    <h4>Important Instructions:</h4>
                    <ul>
                        <li>Please bring this pass (printed or digital) to the conference venue</li>
                        <li>This pass is required for entry and food collection</li>
                        <li>ID verification may be required at the entrance</li>
                        <li>Keep this pass safe - it cannot be replaced if lost</li>
                        @if($registration->include_lunch)
                            <li>Present this pass at the lunch counter to collect your meal</li>
                        @endif
                    </ul>
                </div>
            </div>
            
            <div class="qr-section">
                <div class="section-title">QR Code for Check-in</div>
                <p style="font-size: 13px; color: #6b7280; margin-bottom: 15px;">
                    Scan this QR code at the registration desk
                </p>
                <div class="qr-code">
                    {!! $qrCode !!}
                </div>
                <div style="font-size: 12px; color: #6b7280; margin-top: 10px;">
                    Conference Date: January 28, 2026<br>
                    Venue: Colombo Convention Center
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>
                <strong>Sri Lanka Institute of Architects</strong><br>
                Conference Registration System<br>
                For assistance, contact: conference@slia.lk | +94 11 2 345 678
            </p>
            <p style="font-size: 11px; margin-top: 10px;">
                This is an electronically generated pass. No signature required.<br>
                Pass ID: {{ $registration->id }} | Generated: {{ now()->format('Y-m-d H:i:s') }}
            </p>
        </div>
    </div>
</body>
</html>