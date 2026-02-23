<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLIA Conference Registration Confirmation</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #1e40af;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 10px 10px;
        }
        .success-icon {
            font-size: 48px;
            color: #10b981;
            text-align: center;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background-color: #1e40af;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .details-box {
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .detail-item {
            margin-bottom: 10px;
            display: flex;
        }
        .detail-label {
            font-weight: bold;
            width: 150px;
            color: #4b5563;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
        }
        .important-note {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>SLIA National Conference 2026</h1>
        <p>Registration Confirmation</p>
    </div>
    
    <div class="content">
        <div style="text-align: center;">
            <div class="success-icon">✓</div>
            <h2 style="color: #10b981;">Registration Successful!</h2>
            <p>Dear {{ $registration->full_name }},</p>
            <p>Your registration for the SLIA National Conference 2026 has been confirmed successfully.</p>
        </div>
        
        <div class="details-box">
            <h3 style="color: #1e40af; margin-top: 0;">Registration Details</h3>
            <div class="detail-item">
                <span class="detail-label">Registration ID:</span>
                <span>CONF-{{ str_pad($registration->id, 6, '0', STR_PAD_LEFT) }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Name:</span>
                <span>{{ $registration->full_name }}</span>
            </div>
            @if($registration->membership_number)
            <div class="detail-item">
                <span class="detail-label">Membership No:</span>
                <span>{{ $registration->membership_number }}</span>
            </div>
            @endif
            @if($registration->student_id)
            <div class="detail-item">
                <span class="detail-label">Student ID:</span>
                <span>{{ $registration->student_id }}</span>
            </div>
            @endif
            @if($registration->nic_passport)
            <div class="detail-item">
                <span class="detail-label">NIC / Passport:</span>
                <span>{{ $registration->nic_passport }}</span>
            </div>
            @endif
            <div class="detail-item">
                <span class="detail-label">Category:</span>
                <span>
                    @if($registration->category == 'slia_member')
                        SLIA Member
                    @elseif($registration->category == 'student')
                        Student
                    @elseif($registration->category == 'general_public')
                        General Public
                    @else
                        International Participant
                    @endif
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Lunch Included:</span>
                <span>{{ $registration->include_lunch ? 'Yes (' . ($registration->meal_preference == 'veg' ? 'Vegetarian' : 'Non-Vegetarian') . ')' : 'No' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Payment Reference:</span>
                <span>{{ $registration->payment_ref_no ?? 'TEST MODE' }}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Payment Status:</span>
                <span style="color: #10b981; font-weight: bold;">Completed</span>
            </div>
        </div>
        
        @if(isset($qrCode))
        <div style="text-align: center; margin: 25px 0; padding: 15px; background: white; border: 2px dashed #1e40af; border-radius: 10px; display: inline-block; width: 100%; box-sizing: border-box;">
            <h4 style="color: #1e40af; margin-top: 0; margin-bottom: 10px;">Your QR Check-in Pass</h4>
            <img src="{{ $qrCode }}" alt="QR Code" style="width: 200px; height: 200px; border: 1px solid #eee;">
            <p style="font-size: 12px; color: #6b7280; margin-top: 10px; margin-bottom: 0;">Scan this at the registration desk</p>
        </div>
        @endif
        
        <div class="important-note">
            <h4 style="color: #92400e; margin-top: 0;">Important Information</h4>
            <p>Your Conference Pass is attached to this email as a PDF file. Please:</p>
            <ul>
                <li>Download and print the pass or save it on your phone</li>
                <li>Bring the pass to the conference venue for check-in</li>
                <li>Present the pass at the registration desk</li>
                @if($registration->include_lunch)
                    <li>Show the pass at the lunch counter to collect your meal</li>
                @endif
            </ul>
        </div>
        
    </div>
    
    <div class="footer">
        <p>This is an automated email from SLIA Conference Registration System.</p>
        <p>© 2026 Sri Lanka Institute of Architects. All rights reserved.</p>
    </div>
</body>
</html>