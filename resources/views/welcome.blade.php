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

        .feature-icon.bank { background: linear-gradient(45deg, #4facfe, #00f2fe); }
        .feature-icon.security { background: linear-gradient(45deg, #43e97b, #38f9d7); }
        .feature-icon.payments { background: linear-gradient(45deg, #fa709a, #fee140); }

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

        .status-section h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        #connection-status {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
    <div class="container">
        <div class="hero">
            <div class="logo">
                <div class="logo-icon">P$</div>
                <h2>Plaid + Stripe</h2>
            </div>

            <h1>Connect Your Bank Account</h1>
            <p>Securely link your bank account using Plaid and manage payments through Stripe. Experience seamless financial integration with bank-level security.</p>

            <button id="link-account" class="cta-button">
                🏦 Link Bank Account
            </button>
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
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const linkButton = document.getElementById('link-account');
            const statusSection = document.getElementById('status-section');
            const connectionStatus = document.getElementById('connection-status');
            const loading = document.getElementById('loading');
            let plaidHandler = null;

            // Initialize Plaid Link
            async function initializePlaidLink() {
                try {
                    // Get link token from backend
                    const linkTokenResponse = await fetch('/plaid/create-link-token', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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
                            connectionStatus.innerHTML = '<p>Exchanging token...</p>';

                            try {
                                // Exchange public token for access token
                                const exchangeResponse = await fetch('/plaid/token-exchange', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    },
                                    body: JSON.stringify({
                                        public_token: public_token,
                                        metadata: metadata
                                    })
                                });

                                const exchangeData = await exchangeResponse.json();
                                loading.style.display = 'none';

                                if (exchangeData.access_token) {
                                    // Get account information
                                    const accountsResponse = await fetch('/plaid/accounts', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        },
                                        body: JSON.stringify({
                                            access_token: exchangeData.access_token
                                        })
                                    });

                                    const accountsData = await accountsResponse.json();

                                    let accountsHtml = '<div style="color: #27ae60; font-weight: 600; margin-bottom: 20px;"><p>✅ Successfully Connected!</p></div>';

                                    if (accountsData.accounts && accountsData.accounts.length > 0) {
                                        accountsHtml += '<div style="text-align: left;"><h3>Connected Accounts:</h3>';
                                        accountsData.accounts.forEach(account => {
                                            accountsHtml += `
                                                <div style="background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid #00d4aa;">
                                                    <p><strong>Bank:</strong> ${metadata.institution.name}</p>
                                                    <p><strong>Account:</strong> ${account.name}</p>
                                                    <p><strong>Type:</strong> ${account.type} (${account.subtype})</p>
                                                    <p><strong>Account ID:</strong> ${account.account_id}</p>
                                                    ${account.balances && account.balances.available ? `<p><strong>Available Balance:</strong> $${account.balances.available}</p>` : ''}
                                                </div>
                                            `;
                                        });
                                        accountsHtml += '</div>';
                                    }

                                    connectionStatus.innerHTML = accountsHtml;
                                } else {
                                    connectionStatus.innerHTML = `
                                        <div style="color: #e74c3c; font-weight: 600;">
                                            <p>❌ Token Exchange Failed</p>
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

            linkButton.addEventListener('click', async function() {
                // Show status section
                statusSection.style.display = 'block';
                statusSection.scrollIntoView({ behavior: 'smooth' });

                // Show loading state
                loading.style.display = 'block';
                connectionStatus.innerHTML = '<p>Initializing Plaid Link...</p>';

                // Initialize and open Plaid Link
                const initialized = await initializePlaidLink();
                loading.style.display = 'none';

                if (initialized && plaidHandler) {
                    connectionStatus.innerHTML = '<p>Click continue in the Plaid popup to connect your bank account...</p>';
                    plaidHandler.open();
                }
            });
        });
    </script>
</body>
</html>
