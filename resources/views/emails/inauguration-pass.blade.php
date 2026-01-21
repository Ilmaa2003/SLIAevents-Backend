<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SLIA Inauguration Pass</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #059669; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; background: #f0fdf4; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>SLIA Inauguration Pass </h2>
        </div>
        
        <div class="content">
            <h3>Hello {{ $name }},</h3>
            
            <p>We have sent your SLIA Inauguration event pass</p>
            
            <p><strong>Membership Number:</strong> {{ $membership }}</p>
            
            <p>Your event pass is attached to this email.</p>
            
            <p>Check your spam folder if you don't see the attachment.</p>
            
            <p>Best regards,<br>
            Sri Lanka Institute of Architects</p>
        </div>
    </div>
</body>
</html>