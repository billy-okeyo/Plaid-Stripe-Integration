<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Account Verification</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verification-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 16px;
            line-height: 1.5;
        }

        .status-badge {
            display: inline-block;
            background: #ffeaa7;
            color: #2d3436;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .verification-form {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .amount-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .instructions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .instructions h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .instructions ol {
            color: #555;
            padding-left: 20px;
        }

        .instructions li {
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }

        .alert-success {
            background: #efe;
            border: 1px solid #cfc;
            color: #363;
        }

        .alert-info {
            background: #eef;
            border: 1px solid #ccf;
            color: #336;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .transaction-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .transaction-details h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .detail-label {
            color: #666;
        }

        .detail-value {
            color: #333;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="header">
            <h1>🏦 Bank Account Verification</h1>
            <div class="status-badge">⏳ Verification Required</div>
            <p>Enter the two small deposit amounts from your bank statement to complete verification</p>
        </div>

        <div class="transaction-details">
            <h3>Transaction Details</h3>
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span class="detail-value">${{ $transaction->amount }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Description:</span>
                <span class="detail-value">{{ $transaction->description }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">Pending Verification</span>
            </div>
        </div>

        <div class="instructions">
            <h3>📋 Verification Instructions</h3>
            <ol>
                <li><strong>Check Your Bank Account:</strong> Look for two small deposits from STRIPE (usually $0.01-$0.99 each)</li>
                <li><strong>Find the Amounts:</strong> These deposits typically appear within 1-2 business days</li>
                <li><strong>Enter Below:</strong> Input the exact amounts in cents (e.g., for $0.32, enter 32)</li>
                <li><strong>Complete Transfer:</strong> Your ACH transfer will be processed automatically after verification</li>
            </ol>
        </div>

        <div id="alert-container"></div>

        <form class="verification-form" id="verificationForm">
            <input type="hidden" name="customer_id" value="{{ $customerId }}">
            <input type="hidden" name="bank_account_id" value="{{ $bankAccountId }}">
            <input type="hidden" name="transaction_id" value="{{ $transactionId }}">

            <div class="form-group">
                <label>Microdeposit Amounts (in cents)</label>
                <div class="amount-inputs">
                    <div>
                        <label for="amount1">First Amount</label>
                        <input type="number" id="amount1" name="amount1" min="1" max="99" required placeholder="e.g., 32">
                    </div>
                    <div>
                        <label for="amount2">Second Amount</label>
                        <input type="number" id="amount2" name="amount2" min="1" max="99" required placeholder="e.g., 45">
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                ✅ Verify & Complete Transfer
            </button>
        </form>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <script>
        // Set up CSRF token for all AJAX requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Form handling
        document.getElementById('verificationForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const alertContainer = document.getElementById('alert-container');

            // Clear previous alerts
            alertContainer.innerHTML = '';

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = '🔄 Verifying...';
            loadingOverlay.classList.add('show');

            try {
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());

                const response = await fetch('/plaid/complete-bank-verification', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    // Success - show success message and redirect
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <strong>✅ Success!</strong> ${result.message}
                            <br><br>
                            <strong>Transfer Details:</strong>
                            <br>• Charge ID: ${result.charge.id}
                            <br>• Amount: $${result.charge.amount}
                            <br>• Status: ${result.charge.status}
                        </div>
                    `;

                    // Update button
                    submitBtn.textContent = '✅ Verification Complete';
                    submitBtn.style.background = '#00b894';

                    // Redirect after a delay
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 3000);

                } else {
                    // Error - show error message
                    let errorMessage = result.message || 'Verification failed';

                    if (result.details && result.details.hint) {
                        errorMessage += '<br><br><strong>Hint:</strong> ' + result.details.hint;
                    }

                    alertContainer.innerHTML = `
                        <div class="alert alert-error">
                            <strong>❌ Verification Failed</strong>
                            <br>${errorMessage}
                        </div>
                    `;

                    // Reset button if retry is allowed
                    if (!result.details || result.details.retry_allowed !== false) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = '🔄 Try Again';
                    }
                }

            } catch (error) {
                console.error('Verification error:', error);

                alertContainer.innerHTML = `
                    <div class="alert alert-error">
                        <strong>❌ Network Error</strong>
                        <br>Unable to verify deposits. Please check your connection and try again.
                    </div>
                `;

                // Reset button
                submitBtn.disabled = false;
                submitBtn.textContent = '🔄 Try Again';
            }

            // Hide loading state
            loadingOverlay.classList.remove('show');
        });

        // Input validation
        const amountInputs = document.querySelectorAll('input[name^="amount"]');
        amountInputs.forEach(input => {
            input.addEventListener('input', function() {
                // Ensure values are between 1 and 99
                if (this.value < 1) this.value = 1;
                if (this.value > 99) this.value = 99;
            });
        });
    </script>
</body>
</html>
