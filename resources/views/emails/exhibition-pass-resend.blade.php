<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Exhibition Entry Pass</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header { 
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); 
            color: white; 
            padding: 25px 20px; 
            text-align: center; 
        }
        .header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .header .subtitle {
            margin-top: 8px;
            opacity: 0.9;
            font-size: 14px;
        }
        .content { 
            padding: 30px; 
        }
        .greeting {
            color: #1e293b;
            font-size: 18px;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f5f3ff;
            border-left: 4px solid #8b5cf6;
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 5px 5px 0;
        }
        .important-note {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #92400e;
        }
        .important-note strong {
            color: #78350f;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 25px 0;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .detail-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .detail-value {
            font-size: 15px;
            color: #1e293b;
            font-weight: 500;
        }
        .pass-attachment {
            background: #dcfce7;
            border: 1px solid #86efac;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 25px 0;
        }
        .footer {
            text-align: center;
            color: #64748b;
            font-size: 12px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .event-info {
            background: #e0e7ff;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .event-info h4 {
            margin: 0 0 10px 0;
            color: #3730a3;
        }
        .cta-box {
            text-align: center;
            margin: 25px 0;
        }
        .qr-hint {
            background: #f1f5f9;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            font-size: 13px;
            color: #475569;
        }
        .contact-info {
            background: #f0f9ff;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Exhibition Entry Pass</h2>
            <div class="subtitle">SLIA Annual Exhibition 2026</div>
        </div>
        
        <div class="content">
            <div class="greeting">
                <strong>Hello {{ $name }},</strong>
            </div>
            
            <p>Thank you for registering for the SLIA Annual Exhibition 2026! Your exhibition entry pass has been generated and is attached to this email.</p>
            
            
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Membership No.</div>
                    <div class="detail-value">{{ $membership }}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Registered Email</div>
                    <div class="detail-value">{{ $email }}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Registration Date</div>
                    <div class="detail-value">{{ $date }}</div>
                </div>

            </div>
            
            <div class="pass-attachment">
                <h4 style="color: #166534; margin-top: 0;">📎 Attachment Included</h4>
                <p><strong>File:</strong> SLIA-Exhibition-Pass-{{ $membership }}.pdf</p>
                <p style="font-size: 13px; color: #4b5563;">This PDF contains your exhibition entry pass with QR code for entry verification.</p>
            </div>

            <div class="contact-info">
                <p><strong>Need Help?</strong></p>
                <p style="margin: 8px 0;">
                    <strong>Email:</strong> sliaoffice2@gmail.com<br>
                    <strong>Phone:</strong> +94 77 764 6289
                </p>
                <p style="margin: 8px 0; font-size: 12px;">
                    If you don't see the attachment, please check your spam or junk folder.
                </p>
            </div>
            
            <div class="footer">
                <p>Sri Lanka Institute of Architects</p>
                <p>© SLIA Annual Exhibition</p>
                <p style="font-size: 11px; color: #94a3b8; margin-top: 10px;">
                    This is an automated email. Please do not reply to this message.
                </p>
            </div>
        </div>
    </div>
</body>
</html>