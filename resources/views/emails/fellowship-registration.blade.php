<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .header { background-color: #4f46e5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { padding: 30px; background-color: #ffffff; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        .button { display: inline-block; padding: 12px 24px; background-color: #4f46e5; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .details { background-color: #f9fafb; padding: 20px; border-radius: 8px; margin-top: 20px; }
        .qr-section { text-align: center; margin-top: 30px; padding: 20px; border: 2px dashed #e5e7eb; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Members Night 2026</h1>
            <p>Registration Confirmation</p>
        </div>
        <div class="content">
            <p>Dear {{ $name }},</p>
            <p>Thank you for registering for the <strong>Members Night 2026</strong>. We are delighted to confirm your participation.</p>
            
            <div class="details">
                <p><strong>Registration ID:</strong> {{ $registration->id }}</p>
                <p><strong>Membership No:</strong> {{ $membership ?? 'N/A' }}</p>
                <p><strong>Event Date:</strong> February 2026</p>
                <p><strong>Venue:</strong> BMICH, Colombo</p>
            </div>

            <p>Your official entrance pass is attached to this email as a PDF. Please bring a printed copy or show the digital version on your mobile device for entry.</p>

            <div class="qr-section">
                <p><strong>Your Entry QR Code</strong></p>
                <img src="{{ $qrCode }}" alt="QR Code" width="200">
                <p style="font-size: 12px; color: #666;">Scan at the entrance for quick check-in</p>
            </div>

            <p>If you have any questions, please contact the SLIA Secretariat.</p>
        </div>
        <div class="footer">
            <p>&copy; 2026 SLIA (Sri Lanka Institute of Architects). All rights reserved.</p>
        </div>
    </div>
</body>
</html>
