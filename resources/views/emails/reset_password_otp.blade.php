<!DOCTYPE html>
<html>
<head>
    <title>Password Reset OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
        <h2 style="color: #2563eb; text-align: center; margin-bottom: 20px;">Baliapur Filling Station</h2>
        <p style="font-size: 16px; color: #334155;">Hello,</p>
        <p style="font-size: 16px; color: #334155;">You recently requested to reset your password for your account. Please use the following 6-digit One-Time Password (OTP) to complete the process:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <span style="display: inline-block; font-size: 32px; font-weight: bold; color: #1e293b; background-color: #f1f5f9; padding: 10px 20px; border-radius: 6px; letter-spacing: 5px;">{{ $otp }}</span>
        </div>
        
        <p style="font-size: 16px; color: #334155;">This OTP is valid for the next 10 minutes. If you did not request a password reset, please ignore this email or contact the system administrator.</p>
        
        <p style="font-size: 14px; color: #64748b; margin-top: 40px; text-align: center;">
            &copy; {{ date('Y') }} Baliapur Filling Station. All rights reserved.
        </p>
    </div>
</body>
</html>
