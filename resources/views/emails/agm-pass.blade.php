<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>AGM Registration Pass</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1e40af; color: white; padding: 15px; text-align: center; }
        .content { padding: 20px; background: #f0f9ff; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>AGM Registration Pass</h2>
        </div>
        
        <div class="content">
            <h3>Hello {{ $name }},</h3>
            
            <p>We have sent your AGM registration pass</p>
            
            <p><strong>Membership Number:</strong> {{ $membership }}</p>
            
            <p>Your AGM attendance pass is attached to this email.</p>
            
            <p>Check your spam folder if you don't see the attachment.</p>
            
            <p>Best regards,<br>
            Sri Lanka Institute of Architects</p>
        </div>
    </div>
</body>
</html>