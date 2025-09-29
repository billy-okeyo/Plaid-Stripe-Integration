<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Plaid-Stripe Token Validation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }

        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .results {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            display: none;
        }

        .status-good { color: #28a745; font-weight: 600; }
        .status-error { color: #dc3545; font-weight: 600; }
        .status-warning { color: #ffc107; font-weight: 600; }
        .status-info { color: #17a2b8; font-weight: 600; }

        .test-section {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }

        .test-header {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e0e0e0;
        }

        .test-detail {
            margin: 8px 0;
            font-size: 14px;
        }

        .recommendation {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-top: 20px;
            border-radius: 0 5px 5px 0;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Plaid-Stripe Token Validation</h1>
        <p class="subtitle">Test your Plaid processor tokens and diagnose Stripe verification issues</p>

        <form id="validationForm">
            @csrf
            <div class="form-group">
                <label for="plaidAccountId">Plaid Account ID:</label>
                <input type="number" id="plaidAccountId" name="plaid_account_id" required
                       placeholder="Enter the database ID of your Plaid account (e.g., 1, 2, 3...)">
            </div>

            <button type="submit" id="validateBtn">
                <span id="btnText">🚀 Validate Integration</span>
                <span id="loading" class="loading" style="display: none;"></span>
            </button>
        </form>

        <div id="results" class="results">
            <!-- Results will be populated here -->
        </div>
    </div>

    <script>
        document.getElementById('validationForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('validateBtn');
            const btnText = document.getElementById('btnText');
            const loading = document.getElementById('loading');
            const results = document.getElementById('results');

            // Show loading state
            btn.disabled = true;
            btnText.style.display = 'none';
            loading.style.display = 'inline-block';
            results.style.display = 'none';

            try {
                const formData = new FormData(this);
                const response = await fetch('/plaid/validate-integration', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    displayResults(data.validation_results);
                } else {
                    displayError(data.message || 'Validation failed');
                }

            } catch (error) {
                displayError('Network error: ' + error.message);
            } finally {
                // Reset button
                btn.disabled = false;
                btnText.style.display = 'inline-block';
                loading.style.display = 'none';
                results.style.display = 'block';
            }
        });

        function displayResults(validation) {
            const results = document.getElementById('results');
            let html = '<h2>🔍 Validation Results</h2>';

            // Plaid Account Info
            html += `
                <div class="test-section">
                    <div class="test-header">📊 Plaid Account Information</div>
                    <div class="test-detail"><strong>Account:</strong> ${validation.plaid_account_info.account_name} (${validation.plaid_account_info.institution})</div>
                    <div class="test-detail"><strong>Environment:</strong> ${validation.plaid_account_info.environment}</div>
                    <div class="test-detail"><strong>Access Token:</strong> <span class="${validation.plaid_account_info.has_access_token ? 'status-good' : 'status-error'}">${validation.plaid_account_info.has_access_token ? 'Available' : 'Missing'}</span></div>
                </div>
            `;

            // Plaid Processor Token Test
            if (validation.plaid_processor_token) {
                const status = validation.plaid_processor_token.status;
                const statusClass = status === 'success' ? 'status-good' : 'status-error';

                html += `
                    <div class="test-section">
                        <div class="test-header">🔗 Plaid Processor Token Test</div>
                        <div class="test-detail"><strong>Status:</strong> <span class="${statusClass}">${status.toUpperCase()}</span></div>
                        ${validation.plaid_processor_token.token_type ?
                            `<div class="test-detail"><strong>Token Type:</strong> ${validation.plaid_processor_token.token_type}</div>` : ''}
                        ${validation.plaid_processor_token.token_prefix ?
                            `<div class="test-detail"><strong>Token:</strong> ${validation.plaid_processor_token.token_prefix}</div>` : ''}
                        ${validation.plaid_processor_token.error ?
                            `<div class="test-detail"><strong>Error:</strong> <span class="status-error">${validation.plaid_processor_token.error}</span></div>` : ''}
                    </div>
                `;
            }

            // Stripe Integration Test
            if (validation.stripe_integration) {
                const stripe = validation.stripe_integration;
                let statusClass = 'status-info';
                if (stripe.status === 'token_valid') statusClass = 'status-good';
                else if (stripe.status === 'token_invalid' || stripe.status === 'error') statusClass = 'status-error';
                else if (stripe.status === 'test_mode') statusClass = 'status-info';

                html += `
                    <div class="test-section">
                        <div class="test-header">💳 Stripe Integration Test</div>
                        <div class="test-detail"><strong>Status:</strong> <span class="${statusClass}">${stripe.status.replace('_', ' ').toUpperCase()}</span></div>
                        ${stripe.verification_status ?
                            `<div class="test-detail"><strong>Bank Account Status:</strong> ${stripe.verification_status}</div>` : ''}
                        ${stripe.verification_required !== undefined ?
                            `<div class="test-detail"><strong>Verification Required:</strong> <span class="${stripe.verification_required ? 'status-warning' : 'status-good'}">${stripe.verification_required ? 'YES' : 'NO'}</span></div>` : ''}
                        ${stripe.next_step ?
                            `<div class="test-detail"><strong>Next Step:</strong> ${stripe.next_step}</div>` : ''}
                        ${stripe.error ?
                            `<div class="test-detail"><strong>Error:</strong> <span class="status-error">${stripe.error}</span></div>` : ''}
                        ${stripe.note ?
                            `<div class="test-detail"><strong>Note:</strong> <span class="status-info">${stripe.note}</span></div>` : ''}
                    </div>
                `;
            }

            // Bank Account Details Test
            if (validation.bank_account_details) {
                const bank = validation.bank_account_details;
                const statusClass = bank.status === 'success' ? 'status-good' : 'status-error';

                html += `
                    <div class="test-section">
                        <div class="test-header">🏦 Bank Account Details Test</div>
                        <div class="test-detail"><strong>Status:</strong> <span class="${statusClass}">${bank.status.toUpperCase()}</span></div>
                        ${bank.routing_number ?
                            `<div class="test-detail"><strong>Routing Number:</strong> ${bank.routing_number}</div>` : ''}
                        ${bank.account_number ?
                            `<div class="test-detail"><strong>Account Number:</strong> ${bank.account_number}</div>` : ''}
                        ${bank.account_type ?
                            `<div class="test-detail"><strong>Account Type:</strong> ${bank.account_type}</div>` : ''}
                        ${bank.note ?
                            `<div class="test-detail"><strong>Note:</strong> <span class="status-info">${bank.note}</span></div>` : ''}
                        ${bank.error ?
                            `<div class="test-detail"><strong>Error:</strong> <span class="status-error">${bank.error}</span></div>` : ''}
                    </div>
                `;
            }

            // Diagnosis and Recommendation
            if (validation.diagnosis) {
                html += `
                    <div class="recommendation">
                        <h3>💡 Diagnosis & Recommendation</h3>
                        <p><strong>Overall Status:</strong> ${validation.diagnosis.overall_status}</p>
                        <p><strong>Plaid Working:</strong> <span class="${validation.diagnosis.plaid_working ? 'status-good' : 'status-error'}">${validation.diagnosis.plaid_working ? 'YES' : 'NO'}</span></p>
                        <p><strong>Stripe Token Valid:</strong> <span class="${validation.diagnosis.stripe_token_valid ? 'status-good' : 'status-error'}">${validation.diagnosis.stripe_token_valid ? 'YES' : 'NO'}</span></p>
                        <p><strong>Verification Needed:</strong> <span class="${validation.diagnosis.verification_needed ? 'status-warning' : 'status-good'}">${validation.diagnosis.verification_needed ? 'YES' : 'NO'}</span></p>
                        <p><strong>Recommendation:</strong> ${validation.diagnosis.recommendation}</p>
                    </div>
                `;
            }

            results.innerHTML = html;
        }

        function displayError(message) {
            const results = document.getElementById('results');
            results.innerHTML = `
                <div class="test-section">
                    <div class="test-header status-error">❌ Validation Error</div>
                    <div class="test-detail">${message}</div>
                </div>
            `;
        }
    </script>
</body>
</html>
