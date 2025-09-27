<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stripe Transaction Status Dashboard</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 40px;
            background: #f8f9fa;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header { text-align: center; margin-bottom: 30px; }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        .status-processing { background: #fff3cd; color: #856404; }
        .status-succeeded { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .status-pending { background: #e2e3e5; color: #383d41; }
        .status-requires_action { background: #cce5ff; color: #004085; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #f8f9fa; font-weight: 600; }
        .btn {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover { background: #0056b3; }
        .btn-small { padding: 4px 8px; font-size: 12px; }
        .amount { font-weight: 600; color: #28a745; }
        .error { color: #dc3545; font-size: 12px; }
        .loading { text-align: center; padding: 40px; color: #6c757d; }
        .refresh-btn { float: right; margin-bottom: 20px; }
        .check-form { margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        .metadata {
            font-size: 12px;
            color: #6c757d;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Stripe Transaction Status Dashboard</h1>
            <p>Check the status of ACH transfers and payment intents</p>
        </div>

        <!-- Check Individual Payment Intent -->
        <div class="card">
            <h3>Check Individual Payment Intent</h3>
            <div class="check-form">
                <div class="form-group">
                    <label for="paymentIntentId">Payment Intent ID:</label>
                    <input type="text" id="paymentIntentId" placeholder="pi_..." />
                </div>
                <button class="btn" onclick="checkPaymentIntent()">Check Status</button>
            </div>
            <div id="paymentIntentResult"></div>
        </div>

        <!-- All Transactions -->
        <div class="card">
            <h3>Recent Transactions</h3>
            <button class="btn refresh-btn" onclick="loadTransactions()">🔄 Refresh</button>
            <div id="transactionsList">
                <div class="loading">Loading transactions...</div>
            </div>
        </div>
    </div>

    <script>
        // Set up CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Load transactions on page load
        window.onload = function() {
            loadTransactions();
        };

        async function checkPaymentIntent() {
            const paymentIntentId = document.getElementById('paymentIntentId').value.trim();
            if (!paymentIntentId) {
                alert('Please enter a Payment Intent ID');
                return;
            }

            const resultDiv = document.getElementById('paymentIntentResult');
            resultDiv.innerHTML = '<div class="loading">Checking payment intent...</div>';

            try {
                const response = await fetch('/api/stripe/check-payment-intent', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ payment_intent_id: paymentIntentId })
                });

                const data = await response.json();

                if (data.success) {
                    resultDiv.innerHTML = `
                        <table>
                            <tr><th>Payment Intent ID</th><td>${data.stripe_data.id}</td></tr>
                            <tr><th>Status</th><td><span class="status-badge status-${data.stripe_data.status}">${data.stripe_data.status}</span></td></tr>
                            <tr><th>Amount</th><td class="amount">$${(data.stripe_data.amount / 100).toFixed(2)} ${data.stripe_data.currency.toUpperCase()}</td></tr>
                            <tr><th>Created</th><td>${new Date(data.stripe_data.created * 1000).toLocaleString()}</td></tr>
                            <tr><th>Description</th><td>${data.stripe_data.description || 'N/A'}</td></tr>
                            <tr><th>Payment Method</th><td>${data.stripe_data.payment_method || 'N/A'}</td></tr>
                            ${data.stripe_data.last_payment_error ?
                                `<tr><th>Error</th><td class="error">${data.stripe_data.last_payment_error.message}</td></tr>` :
                                ''
                            }
                        </table>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="error">Error: ${data.error}</div>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="error">Network error: ${error.message}</div>`;
            }
        }

        async function loadTransactions() {
            const listDiv = document.getElementById('transactionsList');
            listDiv.innerHTML = '<div class="loading">Loading transactions...</div>';

            try {
                const response = await fetch('/api/stripe/list-transactions', {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.success && data.transactions.length > 0) {
                    let html = `
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Amount</th>
                                    <th>Local Status</th>
                                    <th>Stripe Status</th>
                                    <th>Created</th>
                                    <th>Payment Intent</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                    data.transactions.forEach(transaction => {
                        const stripeStatus = transaction.stripe_status?.status || 'Unknown';
                        const stripeAmount = transaction.stripe_status ?
                            `$${(transaction.stripe_status.amount / 100).toFixed(2)}` :
                            `$${transaction.amount}`;

                        html += `
                            <tr>
                                <td>${transaction.id}</td>
                                <td class="amount">${stripeAmount}</td>
                                <td><span class="status-badge status-${transaction.local_status}">${transaction.local_status}</span></td>
                                <td>
                                    ${transaction.stripe_status ?
                                        `<span class="status-badge status-${stripeStatus}">${stripeStatus}</span>` :
                                        `<span class="error">${transaction.stripe_error || 'No Stripe data'}</span>`
                                    }
                                </td>
                                <td>${new Date(transaction.created_at).toLocaleString()}</td>
                                <td class="metadata">${transaction.stripe_payment_intent_id || 'N/A'}</td>
                                <td>
                                    <button class="btn btn-small" onclick="syncTransaction(${transaction.id})">Sync</button>
                                </td>
                            </tr>
                        `;
                    });

                    html += '</tbody></table>';
                    listDiv.innerHTML = html;
                } else if (data.success && data.transactions.length === 0) {
                    listDiv.innerHTML = '<div class="loading">No transactions found</div>';
                } else {
                    listDiv.innerHTML = `<div class="error">Error loading transactions: ${data.error}</div>`;
                }
            } catch (error) {
                listDiv.innerHTML = `<div class="error">Network error: ${error.message}</div>`;
            }
        }

        async function syncTransaction(transactionId) {
            try {
                const response = await fetch('/api/stripe/sync-transaction-status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ transaction_id: transactionId })
                });

                const data = await response.json();

                if (data.success) {
                    alert(`Transaction synced: ${data.old_status} → ${data.new_status}`);
                    loadTransactions(); // Refresh the list
                } else {
                    alert(`Sync failed: ${data.error}`);
                }
            } catch (error) {
                alert(`Network error: ${error.message}`);
            }
        }
    </script>
</body>
</html>
