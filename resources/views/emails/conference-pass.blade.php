<!DOCTYPE html>
<html>
<head>
    <title>SLIA National Conference 2026 - Registration</title>
    <style>
        body { font-family: 'Arial', sans-serif; padding: 20px; line-height: 1.6; color: #333; background-color: #f9fafb; margin: 0; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-top: 5px solid #1e40af; }
        h1 { color: #1e40af; text-align: center; margin-bottom: 30px; font-size: 24px; }
        p { margin-bottom: 20px; }
        .details { background-color: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e5e7eb; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
        .detail-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .label { font-weight: bold; color: #4b5563; }
        .value { color: #111827; }
        .footer { text-align: center; font-size: 12px; color: #6b7280; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 20px; }
        .btn { display: inline-block; background-color: #1e40af; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; text-align: center; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Registration Confirmed! 🎉</h1>
        
        <p>Dear {{ $registration->full_name }},</p>
        
        <p>Thank you for registering for the <strong>SLIA National Conference 2026</strong>. Your payment has been successfully processed and your seat is confirmed.</p>
        
        <div class="details">
            <div class="detail-row">
                <span class="label">Reference No:</span>
                <span class="value">CONF-{{ $registration->id }}</span>
            </div>
           
             @if($registration->membership_number)
            <div class="detail-row">
                <span class="label">Membership No:</span>
                <span class="value">{{ $registration->membership_number }}</span>
            </div>
            @endif
             @if($registration->student_id)
            <div class="detail-row">
                <span class="label">Student ID:</span>
                <span class="value">{{ $registration->student_id }}</span>
            </div>
            @endif
             @if($registration->nic_passport)
            <div class="detail-row">
                <span class="label">NIC / Passport:</span>
                <span class="value">{{ $registration->nic_passport }}</span>
            </div>
            @endif
        </div>

        <p><strong>Your Conference Pass is attached to this email.</strong> Please examine the attached PDF. You will need to show the QR code on the pass at the entrance to gain admission.</p>


        <div style="text-align: center;">
            <p>We look forward to seeing you there!</p>
        </div>

        <div class="footer">
            <p>Sri Lanka Institute of Architects<br>
            +94 77 764 6289 | sliaoffice2@gmail.com</p>
            <p>This is an automated email. Please do not reply directly.</p>
        </div>
    </div>
</body>
</html>
