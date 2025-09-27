<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Plaid-Stripe Integration Demo</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

    <!-- Plaid Link SDK -->
    <script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .hero {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            margin: 40px auto;
            max-width: 800px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .logo {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, #00d4aa, #00b894);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }

        .hero h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-button {
            background: linear-gradient(135deg, #00d4aa, #00b894);
            color: white;
            border: none;
            padding: 18px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 8px 25px rgba(0, 180, 148, 0.3);
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 180, 148, 0.4);
        }

        .cta-button:active {
            transform: translateY(0);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 60px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .feature-icon.bank {
            background: linear-gradient(45deg, #4facfe, #00f2fe);
        }

        .feature-icon.security {
            background: linear-gradient(45deg, #43e97b, #38f9d7);
        }

        .feature-icon.payments {
            background: linear-gradient(45deg, #fa709a, #fee140);
        }

        .feature-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }

        .feature-card p {
            color: #666;
            line-height: 1.6;
        }

        .status-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            margin-top: 40px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .stored-accounts-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            margin-top: 40px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .status-section h2,
        .stored-accounts-section h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        #connection-status,
        #stored-accounts-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .account-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #00d4aa;
            text-align: left;
            transition: transform 0.2s ease;
        }

        .account-card:hover {
            transform: translateY(-2px);
        }

        .account-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }

        .disconnect-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.2s ease;
            margin-left: auto;
        }

        .disconnect-btn:hover {
            background: #c0392b;
        }

        .refresh-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            margin: 10px;
        }

        .refresh-btn:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .spinner {
            display: inline-block;
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #00d4aa;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .hero {
                margin: 20px;
                padding: 40px 20px;
            }

            .hero h1 {
                font-size: 2.2rem;
            }

            .features {
                grid-template-columns: 1fr;
                margin-top: 40px;
            }
        }
    </style>
</head>

<body>
    <button
        style="position: fixed; top: 20px; right: 20px; background: #fff; border: none; padding: 10px 15px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer;"
        onclick="window.location.href='{{ route('summary') }}'">
        View Summary
    </button>

    <div class="container">
        <div class="hero">
            <div class="logo">
                <div class="logo-icon">P$</div>
                <h2>Plaid + Stripe</h2>
            </div>

            <h1>Connect Your Bank Account</h1>
            <p>Securely link your bank account using Plaid and manage payments through Stripe. Experience seamless
                financial integration with bank-level security.</p>

            <button id="link-account" class="cta-button">
                🏦 Link Bank Account
            </button>

            <div style="margin-top: 20px;">
                <a href="/dashboard"
                    style="display: inline-block; background: linear-gradient(135deg, #2c3e50, #3498db); color: white; text-decoration: none; padding: 12px 24px; border-radius: 25px; font-weight: 600; transition: all 0.2s ease;">
                    💰 Go to Plaid + Stripe Dashboard
                </a>
            </div>
        </div>

        <div class="status-section" id="status-section" style="display: none;">
            <h2>Connection Status</h2>
            <div id="connection-status">
                <p>Ready to connect...</p>
            </div>
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Connecting to your bank...</p>
            </div>
        </div>

        <div class="stored-accounts-section" id="stored-accounts-section">
            <h2>Connected Accounts</h2>
            <div style="text-align: center; margin-bottom: 20px;">
                <button id="refresh-accounts" class="refresh-btn">🔄 Refresh Accounts</button>
            </div>
            <div id="stored-accounts-list">
                <p>Loading stored accounts...</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const linkButton = document.getElementById('link-account');
            const statusSection = document.getElementById('status-section');
            const connectionStatus = document.getElementById('connection-status');
            const loading = document.getElementById('loading');
            const refreshButton = document.getElementById('refresh-accounts');
            const storedAccountsList = document.getElementById('stored-accounts-list');
            let plaidHandler = null;

            // Load stored accounts on page load
            loadStoredAccounts();

            // Initialize Plaid Link
            async function initializePlaidLink() {
                try {
                    // Get link token from backend
                    const linkTokenResponse = await fetch('/plaid/create-link-token', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .getAttribute('content')
                        },
                        body: JSON.stringify({
                            user_id: 'demo_user_' + Date.now()
                        })
                    });

                    if (!linkTokenResponse.ok) {
                        throw new Error('Failed to get link token');
                    }

                    const linkTokenData = await linkTokenResponse.json();

                    if (linkTokenData.error) {
                        throw new Error(linkTokenData.error);
                    }

                    // Create Plaid Link handler
                    plaidHandler = Plaid.create({
                        token: linkTokenData.link_token,
                        onSuccess: async (public_token, metadata) => {
                            loading.style.display = 'block';
                            connectionStatus.innerHTML =
                                '<p>Exchanging token and saving accounts...</p>';

                            try {
                                // Exchange public token and save accounts to database
                                const exchangeResponse = await fetch('/plaid/token-exchange', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector(
                                            'meta[name="csrf-token"]').getAttribute(
                                            'content')
                                    },
                                    body: JSON.stringify({
                                        public_token: public_token,
                                        metadata: metadata
                                    })
                                });

                                const exchangeData = await exchangeResponse.json();
                                loading.style.display = 'none';

                                if (exchangeData.access_token && exchangeData.accounts) {
                                    let accountsHtml =
                                        '<div style="color: #27ae60; font-weight: 600; margin-bottom: 20px;"><p>✅ Successfully Connected & Saved!</p></div>';
                                    accountsHtml +=
                                        `<div style="text-align: left;"><p><strong>Accounts Saved:</strong> ${exchangeData.accounts_saved}</p>`;
                                    accountsHtml +=
                                        '<h3 style="margin-top: 20px;">Connected Accounts:</h3>';

                                    exchangeData.accounts.forEach(account => {
                                        accountsHtml += `
                                            <div class="account-card">
                                                <p><strong>Bank:</strong> ${account.institution_name}</p>
                                                <p><strong>Account:</strong> ${account.account_name}</p>
                                                <p><strong>Type:</strong> ${account.account_type} (${account.account_subtype})</p>
                                                ${account.formatted_available_balance ? `<p><strong>Available Balance:</strong> ${account.formatted_available_balance}</p>` : ''}
                                                ${account.formatted_current_balance ? `<p><strong>Current Balance:</strong> ${account.formatted_current_balance}</p>` : ''}
                                            </div>
                                        `;
                                    });
                                    accountsHtml += '</div>';

                                    connectionStatus.innerHTML = accountsHtml;

                                    // Refresh the stored accounts list
                                    setTimeout(() => {
                                        loadStoredAccounts();
                                    }, 1000);
                                } else {
                                    connectionStatus.innerHTML = `
                                        <div style="color: #e74c3c; font-weight: 600;">
                                            <p>❌ Connection Failed</p>
                                            <p style="font-weight: normal; margin-top: 10px;">${exchangeData.message || 'Unknown error occurred'}</p>
                                        </div>
                                    `;
                                }
                            } catch (error) {
                                loading.style.display = 'none';
                                connectionStatus.innerHTML = `
                                    <div style="color: #e74c3c; font-weight: 600;">
                                        <p>❌ Connection Error</p>
                                        <p style="font-weight: normal; margin-top: 10px;">${error.message}</p>
                                    </div>
                                `;
                            }
                        },
                        onExit: (err, metadata) => {
                            loading.style.display = 'none';
                            if (err != null) {
                                connectionStatus.innerHTML = `
                                    <div style="color: #e74c3c; font-weight: 600;">
                                        <p>❌ Connection Cancelled</p>
                                        <p style="font-weight: normal; margin-top: 10px;">${err.error_message || 'User cancelled the connection process'}</p>
                                    </div>
                                `;
                            } else {
                                connectionStatus.innerHTML = `
                                    <div style="color: #f39c12; font-weight: 600;">
                                        <p>⚠️ Connection Cancelled</p>
                                        <p style="font-weight: normal; margin-top: 10px;">You can try linking your account again.</p>
                                    </div>
                                `;
                            }
                        },
                        onEvent: (eventName, metadata) => {
                            console.log('Plaid Event:', eventName, metadata);
                        }
                    });

                    return true;
                } catch (error) {
                    console.error('Error initializing Plaid Link:', error);
                    connectionStatus.innerHTML = `
                        <div style="color: #e74c3c; font-weight: 600;">
                            <p>❌ Initialization Error</p>
                            <p style="font-weight: normal; margin-top: 10px;">${error.message}</p>
                            <div style="background: #f8f9fa; padding: 15px; margin-top: 15px; border-radius: 8px; text-align: left;">
                                <p style="font-family: monospace; font-size: 0.9em;">
                                    Make sure your Plaid credentials are correctly configured in .env:<br>
                                    PLAID_CLIENT_ID=your_client_id<br>
                                    PLAID_SECRET=your_secret_key<br>
                                    PLAID_ENV=sandbox
                                </p>
                            </div>
                        </div>
                    `;
                    return false;
                }
            }

            // Load stored accounts from database
            async function loadStoredAccounts() {
                try {
                    const response = await fetch('/plaid/stored-accounts', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .getAttribute('content')
                        }
                    });

                    const data = await response.json();

                    if (data.accounts && data.accounts.length > 0) {
                        let accountsHtml = `<div style="text-align: center; margin-bottom: 20px; color: #27ae60; font-weight: 600;">
                            <p>📊 ${data.total} Connected Account${data.total > 1 ? 's' : ''}</p>
                        </div>`;

                        data.accounts.forEach(account => {
                            accountsHtml += `
                                <div class="account-card">
                                    <div class="account-header">
                                        <div>
                                            <p style="font-size: 1.1em; font-weight: 600; color: #333; margin-bottom: 5px;">
                                                🏦 ${account.institution_name}
                                            </p>
                                            <p style="color: #666; font-size: 0.9em;">Connected: ${account.connected_at}</p>
                                        </div>
                                        <button class="disconnect-btn" onclick="disconnectAccount(${account.id})">
                                            Disconnect
                                        </button>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                        <div>
                                            <p><strong>Account:</strong> ${account.account_name}</p>
                                            <p><strong>Type:</strong> ${account.account_type} (${account.account_subtype})</p>
                                        </div>
                                        <div style="text-align: right;">
                                            ${account.formatted_available_balance ? `<p><strong>Available:</strong> <span style="color: #27ae60;">${account.formatted_available_balance}</span></p>` : ''}
                                            ${account.formatted_current_balance ? `<p><strong>Current:</strong> <span style="color: #2c3e50;">${account.formatted_current_balance}</span></p>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });

                        storedAccountsList.innerHTML = accountsHtml;
                    } else {
                        storedAccountsList.innerHTML = `
                            <div style="text-align: center; color: #666; padding: 40px;">
                                <p>🔗 No accounts connected yet</p>
                                <p style="font-size: 0.9em; margin-top: 10px;">Click "Link Bank Account" above to get started!</p>
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error loading stored accounts:', error);
                    storedAccountsList.innerHTML = `
                        <div style="color: #e74c3c; text-align: center;">
                            <p>❌ Error loading accounts</p>
                            <p style="font-size: 0.9em;">${error.message}</p>
                        </div>
                    `;
                }
            }

            // Disconnect account function
            window.disconnectAccount = async function(accountId) {
                if (!confirm('Are you sure you want to disconnect this account?')) {
                    return;
                }

                try {
                    const response = await fetch(`/plaid/accounts/${accountId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .getAttribute('content')
                        }
                    });

                    const data = await response.json();

                    if (response.ok) {
                        // Refresh the accounts list
                        loadStoredAccounts();
                        alert('Account disconnected successfully!');
                    } else {
                        alert('Failed to disconnect account: ' + (data.message || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error disconnecting account:', error);
                    alert('Error disconnecting account: ' + error.message);
                }
            };

            // Event listeners
            linkButton.addEventListener('click', async function() {
                // Show status section
                statusSection.style.display = 'block';
                statusSection.scrollIntoView({
                    behavior: 'smooth'
                });

                // Show loading state
                loading.style.display = 'block';
                connectionStatus.innerHTML = '<p>Initializing Plaid Link...</p>';

                // Initialize and open Plaid Link
                const initialized = await initializePlaidLink();
                loading.style.display = 'none';

                if (initialized && plaidHandler) {
                    connectionStatus.innerHTML =
                        '<p>Click continue in the Plaid popup to connect your bank account...</p>';
                    plaidHandler.open();
                }
            });

            refreshButton.addEventListener('click', function() {
                storedAccountsList.innerHTML = '<p>Refreshing accounts...</p>';
                loadStoredAccounts();
            });
        });
    </script>
</body>

</html>
