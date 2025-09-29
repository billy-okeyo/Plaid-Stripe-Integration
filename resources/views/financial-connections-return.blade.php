<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment {{ $success ? 'Success' : 'Failed' }} - Financial Connections</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            color: #333;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
            margin: 20px;
        }
        .success {
            color: #10b981;
        }
        .error {
            color: #ef4444;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            margin-bottom: 20px;
            font-size: 24px;
        }
        p {
            margin-bottom: 30px;
            line-height: 1.6;
            color: #6b7280;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        .payment-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        @if($success)
            <div class="icon success">✅</div>
            <h1 class="success">Payment Successful!</h1>
            <p>Your bank account has been successfully connected via Financial Connections and your payment has been processed.</p>

            @if($payment_intent)
            <div class="payment-info">
                <strong>Payment Intent ID:</strong> {{ $payment_intent }}
            </div>
            @endif

            <div style="background: #f0f9ff; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #1d4ed8; margin-bottom: 10px;">✨ What happened?</h3>
                <ul style="text-align: left; color: #374151; line-height: 1.6;">
                    <li>You securely authenticated with your bank</li>
                    <li>Financial Connections verified your account instantly</li>
                    <li>Your payment was processed immediately</li>
                    <li>No microdeposits needed!</li>
                </ul>
            </div>
        @else
            <div class="icon error">❌</div>
            <h1 class="error">Payment Issue</h1>
            <p>{{ $message }}</p>

            <div style="background: #fef2f2; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #dc2626; margin-bottom: 10px;">What you can try:</h3>
                <ul style="text-align: left; color: #374151; line-height: 1.6;">
                    <li>Check your internet connection</li>
                    <li>Try a different bank account</li>
                    <li>Contact your bank if authentication failed</li>
                    <li>Retry the payment process</li>
                </ul>
            </div>
        @endif

        <a href="{{ route('dashboard') }}" class="btn">Return to Dashboard</a>
    </div>
</body>
</html>
