<!DOCTYPE html>
<html>
<head>
    <title>Welcome to Baliapur Filling Station</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header { text-align: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #2563eb; font-size: 24px; margin: 0; }
        .content { line-height: 1.6; }
        .credentials { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .credentials p { margin: 5px 0; font-size: 16px; }
        .credentials strong { color: #0f172a; display: inline-block; width: 90px; }
        .btn { display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; margin-top: 20px; text-align: center; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Baliapur Filling Station</h1>
        </div>
        
        <div class="content">
            <p>Hello <strong>{{ $user->name }}</strong>,</p>
            <p>An account has been created for you on the Baliapur Filling Station portal. You have been assigned the role of <strong>{{ $user->role }}</strong>.</p>
            
            <p>Here are your login credentials:</p>
            
            <div class="credentials">
                <p><strong>Email ID:</strong> {{ $user->email }}</p>
                <p><strong>Password:</strong> {{ $plainPassword }}</p>
            </div>
            
            <p>Please log in using the button below and make sure to change your password after logging in for security purposes.</p>
            
            <div style="text-align: center;">
                <a href="https://baliapurfillingstation.in/login" class="btn">Login to Your Account</a>
            </div>
        </div>
        
        <div class="footer">
            <p>If you did not request this account or if you need assistance, please contact the Super Admin.</p>
            <p>&copy; {{ date('Y') }} Baliapur Filling Station. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
