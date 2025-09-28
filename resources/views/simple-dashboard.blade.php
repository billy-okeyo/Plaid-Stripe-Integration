<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Plaid + Stripe Lending Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
        }

        .header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        /* Tab Navigation */
        .tab-nav {
            background: white;
            border-radius: 15px 15px 0 0;
            overflow: hidden;
            display: flex;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tab-button {
            flex: 1;
            padding: 15px 20px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .tab-button:hover {
            background: #e9ecef;
        }

        .tab-button.active {
            background: white;
            border-bottom-color: #667eea;
            color: #667eea;
        }

        /* Tab Content */
        .tab-content {
            background: white;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            min-height: 600px;
        }

        .tab-panel {
            display: none;
            padding: 30px;
        }

        .tab-panel.active {
            display: block;
        }

        /* Cards within tabs */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .dashboard-card {
            background: #f8f9ff;
            padding: 25px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #667eea;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
            width: auto;
        }

        .btn-secondary {
            background: #6c757d;
            margin-top: 10px;
        }

        /* Enhanced list styling for better visual separation */
        .users-list, .transactions-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 5px;
        }

        /* Custom scrollbar for lists */
        .users-list::-webkit-scrollbar, .transactions-list::-webkit-scrollbar {
            width: 6px;
        }

        .users-list::-webkit-scrollbar-track, .transactions-list::-webkit-scrollbar-track {
            background: #f1f3f5;
            border-radius: 3px;
        }

        .users-list::-webkit-scrollbar-thumb, .transactions-list::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 3px;
        }

        .users-list::-webkit-scrollbar-thumb:hover, .transactions-list::-webkit-scrollbar-thumb:hover {
            background: #5a67d8;
        }

        .user-item, .transaction-item {
            background: #f8f9ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .user-item:hover, .transaction-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
            background: #ffffff;
        }

        .user-item::before, .transaction-item::before {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
            border-radius: 8px;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .user-item:hover::before, .transaction-item:hover::before {
            opacity: 1;
        }

        .user-item h4, .transaction-item h4 {
            color: #667eea;
            margin-bottom: 5px;
        }

        .user-item p, .transaction-item p {
            color: #666;
            margin-bottom: 5px;
        }

        /* Special styling for bank account items */
        .dashboard-card .user-item {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            border: 1px solid #e9ecef;
            margin-bottom: 12px;
        }

        .dashboard-card .user-item:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
        }

        /* Enhanced status badges */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .status-active, .status-succeeded {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background: #cce5ff;
            color: #004085;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .status-requires_action {
            background: #e2e3e5;
            color: #383d41;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Table styling with better row distinction */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            background: white;
        }

        th {
            background: #f8f9fa !important;
            font-weight: 600;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        tr {
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-radius: 8px;
            transition: all 0.3s ease;
            background: white;
        }

        tr:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        tr td:first-child {
            border-radius: 8px 0 0 8px;
        }

        tr td:last-child {
            border-radius: 0 8px 8px 0;
        }

        tbody tr {
            margin-bottom: 8px;
        }

        .amount {
            font-weight: 600;
            color: #28a745;
        }

        .error {
            color: #dc3545;
            font-size: 12px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .check-form {
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .check-form:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #667eea;
        }

        .metadata {
            font-size: 12px;
            color: #6c757d;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .refresh-btn {
            float: right;
            margin-bottom: 20px;
            width: auto;
        }

        .info-box {
            background: linear-gradient(135deg, #d1ecf1 0%, #e8f4f5 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .info-box:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .success-box {
            background: linear-gradient(135deg, #d4edda 0%, #e8f5e8 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .success-box:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Toggle Switch Styles */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        input:checked + .toggle-slider {
            background-color: #28a745;
        }

        input:focus + .toggle-slider {
            box-shadow: 0 0 1px #28a745;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        .toggle-slider:hover {
            box-shadow: 0 0 8px rgba(40, 167, 69, 0.3);
        }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="header">
            <h1>🏦 Plaid + Stripe Lending Platform</h1>
            <p>ACH transfers via Plaid Processor + Stripe integration</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button class="tab-button active" onclick="openTab(event, 'users-tab')">👥 Users</button>
            <button class="tab-button" onclick="openTab(event, 'accounts-tab')">🏦 Bank Accounts</button>
            <button class="tab-button" onclick="openTab(event, 'transfer-tab')">💸 Transfers</button>
            <button class="tab-button" onclick="openTab(event, 'transactions-tab')">📊 Transactions</button>
            <button class="tab-button" onclick="openTab(event, 'stripe-status-tab')">🔍 Stripe Status</button>
            <button class="tab-button" onclick="openTab(event, 'health-tab')">🏥 Health Monitor</button>
            <button class="tab-button" onclick="openTab(event, 'settings-tab')">⚙️ Settings</button>
            <button class="tab-button" onclick="openTab(event, 'config-tab')">🔧 Configuration</button>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Users Tab -->
            <div id="users-tab" class="tab-panel active">
                <div class="dashboard-grid">
                    <!-- Create User -->
                    <div class="dashboard-card">
                        <h2 class="card-title">Create User</h2>
                        <form id="createUserForm">
                            <div class="form-group">
                                <label for="userType">User Type</label>
                                <select id="userType" name="user_type" required>
                                    <option value="">Select type</option>
                                    <option value="lender">Lender</option>
                                    <option value="borrower">Borrower</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="businessName">Business Name</label>
                                <input type="text" id="businessName" name="business_name" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                            <button type="submit" class="btn">Create User</button>
                        </form>
                    </div>

                    <!-- Users List -->
                    <div class="dashboard-card">
                        <h2 class="card-title">Platform Users</h2>
                        <div id="usersList" class="users-list">
                            <p>Loading users...</p>
                        </div>
                        <button onclick="loadUsers()" class="btn btn-secondary">Refresh Users</button>
                    </div>
                </div>
            </div>

            <!-- Bank Accounts Tab -->
            <div id="accounts-tab" class="tab-panel">
                <div class="dashboard-grid">
                    <!-- Link Bank Account -->
                    <div class="dashboard-card">
                        <h2 class="card-title">Link Bank Account</h2>
                        <p style="margin-bottom: 20px;">Connect bank accounts for ACH transfers using Plaid</p>
                        <button id="linkAccountButton" class="btn">Link Bank Account via Plaid</button>
                    </div>

                    <!-- Linked Accounts -->
                    <div class="dashboard-card">
                        <h2 class="card-title">Linked Bank Accounts</h2>
                        <div id="linkedAccounts" style="max-height: 400px; overflow-y: auto;">
                            <p>Loading accounts...</p>
                        </div>
                        <button onclick="loadLinkedAccounts()" class="btn btn-secondary">Refresh Accounts</button>
                    </div>
                </div>
            </div>

            <!-- Transfer Tab -->
            <div id="transfer-tab" class="tab-panel">
                <div class="dashboard-card">
                    <h2 class="card-title">Transfer Funds - ACH</h2>
                    <div id="transferSection">
                        <form id="transferForm">
                            <div class="form-group">
                                <label for="fromAccount">From Bank Account</label>
                                <select id="fromAccount" name="from_account_id" required>
                                    <option value="">Select source bank account</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="toAccount">To Bank Account</label>
                                <select id="toAccount" name="to_account_id" required>
                                    <option value="">Select destination bank account</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="amount">Amount ($)</label>
                                <input type="number" id="amount" name="amount" min="0.01" step="0.01" required placeholder="Enter amount">
                            </div>
                            <div class="form-group">
                                <label for="email">Email for Verification Notifications</label>
                                <input type="email" id="email" name="email" required placeholder="Enter your email" value="bewiran528@bitmens.com">
                                <small style="color: #666; font-size: 12px;">Verification links and status updates will be sent to this email</small>
                            </div>
                            <div class="form-group" style="background: #f8f9ff; padding: 15px; border-radius: 8px; border: 1px solid #e1e5f2;">
                                <label style="font-weight: bold; color: #667eea; margin-bottom: 10px; display: block;">💳 Payment Processing Options</label>
                                <div style="margin-bottom: 15px;">
                                    <label for="paymentMethod" style="font-weight: 600; margin-bottom: 5px; display: block;">Payment API Method</label>
                                    <select id="paymentMethod" name="payment_method" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <option value="payment_intents">PaymentIntents API (Modern, Faster)</option>
                                        <option value="charges">Charges API (Legacy, Traditional)</option>
                                    </select>
                                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                                        PaymentIntents supports instant verification. Charges uses traditional microdeposits.
                                    </small>
                                </div>
                                <div>
                                    <label for="verificationMethod" style="font-weight: 600; margin-bottom: 5px; display: block;">Verification Method</label>
                                    <select id="verificationMethod" name="verification_method" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <option value="instant">⚡ Instant Verification (Recommended)</option>
                                        <option value="microdeposit">🏦 Microdeposit Verification (3-5 days)</option>
                                    </select>
                                    <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                                        Instant verification works immediately. Microdeposits require waiting for small test deposits.
                                    </small>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <input type="text" id="description" name="description" placeholder="Optional transfer description">
                            </div>
                            <button type="submit" class="btn">Initiate ACH Transfer</button>
                        </form>
                        <div id="transferStatus" style="margin-top: 20px;"></div>
                    </div>
                </div>
            </div>

            <!-- Transactions Tab -->
            <div id="transactions-tab" class="tab-panel">
                <div class="dashboard-card">
                    <h2 class="card-title">Recent Transactions</h2>
                    <div id="transactionsList" class="transactions-list">
                        <p>Loading transactions...</p>
                    </div>
                    <button onclick="loadTransactions()" class="btn btn-secondary">Refresh Transactions</button>
                </div>
            </div>

            <!-- Stripe Status Tab -->
            <div id="stripe-status-tab" class="tab-panel">
                <div class="dashboard-grid">
                    <!-- Check Individual Payment Intent -->
                    <div class="dashboard-card">
                        <h2 class="card-title">Check Individual Payment Intent</h2>
                        <div class="check-form">
                            <div class="form-group">
                                <label for="paymentIntentId">Payment Intent ID:</label>
                                <input type="text" id="paymentIntentId" placeholder="pi_..." />
                            </div>
                            <button class="btn" onclick="checkPaymentIntent()">Check Status</button>
                        </div>
                        <div id="paymentIntentResult"></div>
                    </div>
                </div>

                <!-- All Transactions with Stripe Status -->
                <div class="dashboard-card" style="margin-top: 30px;">
                    <h2 class="card-title">All Transactions with Stripe Status</h2>
                    <button class="btn btn-small refresh-btn" onclick="loadStripeTransactions()">🔄 Refresh</button>
                    <div id="stripeTransactionsList">
                        <div class="loading">Loading transactions...</div>
                    </div>
                </div>
            </div>

            <!-- Health Monitoring Tab -->
            <div id="health-tab" class="tab-panel">
                <div class="dashboard-grid">
                    <!-- Service Health Status -->
                    <div class="dashboard-card">
                        <h2 class="card-title">Service Health Status</h2>
                        <button class="btn btn-small refresh-btn" onclick="loadServiceHealth()">🔄 Check Health</button>
                        <div id="serviceHealthStatus">
                            <div class="loading">Checking service health...</div>
                        </div>
                    </div>

                    <!-- Queue Statistics -->
                    <div class="dashboard-card">
                        <h2 class="card-title">Transaction Queue</h2>
                        <button class="btn btn-small refresh-btn" onclick="loadQueuedTransactions()">🔄 Refresh Queue</button>
                        <div id="queueStats">
                            <div class="loading">Loading queue statistics...</div>
                        </div>
                        <div style="margin-top: 15px;">
                            <button class="btn btn-primary" onclick="processQueue()" style="margin-right: 10px;">🔄 Process All Ready</button>
                            <button class="btn btn-secondary" onclick="loadQueuedTransactions()">📋 View Queue</button>
                        </div>
                    </div>
                </div>

                <!-- Queued Transactions List -->
                <div class="dashboard-card" style="margin-top: 30px;">
                    <h2 class="card-title">Queued Transactions</h2>
                    <div id="queuedTransactionsList">
                        <div class="loading">Loading queued transactions...</div>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="settings-tab" class="tab-panel">
                <div class="dashboard-grid">
                    <!-- Service Controls -->
                    <div class="dashboard-card">
                        <h2 class="card-title">🔧 Service Testing Controls</h2>
                        <p style="margin-bottom: 20px; color: #666;">Toggle services on/off to test the queue functionality when services are unavailable.</p>

                        <div id="serviceControls">
                            <div class="loading">Loading service controls...</div>
                        </div>

                        <div style="margin-top: 20px;">
                            <button class="btn btn-secondary" onclick="resetAllSettings()" style="margin-right: 10px;">🔄 Reset to Defaults</button>
                            <button class="btn btn-primary" onclick="loadServiceControls()">🔍 Refresh Status</button>
                        </div>
                    </div>

                    <!-- System Settings -->
                    <div class="dashboard-card">
                        <h2 class="card-title">⚙️ System Settings</h2>
                        <div id="systemSettings">
                            <div class="loading">Loading system settings...</div>
                        </div>

                        <div style="margin-top: 20px;">
                            <button class="btn btn-primary" onclick="saveSettings()">💾 Save Settings</button>
                        </div>
                    </div>
                </div>

                <!-- Settings Overview -->
                <div class="dashboard-card" style="margin-top: 30px;">
                    <h2 class="card-title">📋 All Settings</h2>
                    <button class="btn btn-small refresh-btn" onclick="loadAllSettings()">🔄 Refresh</button>
                    <div id="allSettingsList">
                        <div class="loading">Loading all settings...</div>
                    </div>
                </div>
            </div>

            <!-- Configuration Tab -->
            <div id="config-tab" class="tab-panel">
                <div class="dashboard-grid">
                    <!-- Current Configuration Status -->
                    <div class="dashboard-card">
                        <h2 class="card-title">🔧 Current Configuration</h2>
                        <p style="margin-bottom: 20px; color: #666;">Current API configuration status and credentials being used.</p>

                        <div id="configurationStatus">
                            <div class="loading">Loading configuration status...</div>
                        </div>

                        <div style="margin-top: 20px;">
                            <button class="btn btn-primary" onclick="loadConfigurationStatus()">🔄 Refresh Status</button>
                        </div>
                    </div>

                    <!-- Environment Toggle -->
                    <div class="dashboard-card">
                        <h2 class="card-title">🔄 Environment Toggle</h2>
                        <p style="margin-bottom: 20px; color: #666;">
                            Switch between production and sandbox/test credentials.
                            <strong>Note:</strong> This requires updating your .env file and will require an application restart.
                        </p>

                        <div id="environmentToggle">
                            <div class="setting-item">
                                <label>
                                    <input type="checkbox" id="useProductionApisToggle" onchange="handleProductionToggle(this)">
                                    <span class="checkmark"></span>
                                    Use Production APIs
                                </label>
                                <div class="setting-description">
                                    When enabled, uses production Plaid and Stripe credentials. When disabled, uses sandbox/test credentials.
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #17a2b8;">
                            <h4 style="color: #17a2b8; margin-bottom: 10px;">🔑 Credential Setup Instructions</h4>
                            <p style="margin-bottom: 10px; font-size: 14px;">
                                To use production credentials, update your <code>.env</code> file with:
                            </p>
                            <ul style="margin-left: 20px; font-size: 14px;">
                                <li><code>PLAID_PROD_CLIENT_ID</code> - Your production Plaid client ID</li>
                                <li><code>PLAID_PROD_SECRET</code> - Your production Plaid secret</li>
                                <li><code>STRIPE_PROD_SECRET_KEY</code> - Your production Stripe secret key</li>
                                <li><code>STRIPE_PROD_PUBLISHABLE_KEY</code> - Your production Stripe publishable key</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <!-- Include Plaid Link SDK -->
    <script src="https://cdn.plaid.com/link/v2/stable/link-initialize.js"></script>

    <script>
        // Store users for transfer dropdowns
        let allUsers = [];

        // Tab functionality
        function openTab(evt, tabName) {
            var i, tabPanel, tabButton;

            // Hide all tab panels
            tabPanel = document.getElementsByClassName("tab-panel");
            for (i = 0; i < tabPanel.length; i++) {
                tabPanel[i].classList.remove("active");
            }

            // Remove active class from all buttons
            tabButton = document.getElementsByClassName("tab-button");
            for (i = 0; i < tabButton.length; i++) {
                tabButton[i].classList.remove("active");
            }

            // Show the selected tab panel and mark button as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");

            // Load data for specific tabs when they're opened
            if (tabName === 'stripe-status-tab') {
                loadStripeTransactions();
            } else if (tabName === 'accounts-tab') {
                loadLinkedAccounts();
            } else if (tabName === 'transactions-tab') {
                loadTransactions();
            } else if (tabName === 'health-tab') {
                loadServiceHealth();
                loadQueuedTransactions();
            } else if (tabName === 'settings-tab') {
                loadServiceControls();
                loadSystemSettings();
                loadAllSettings();
            } else if (tabName === 'config-tab') {
                loadConfigurationStatus();
            } else if (tabName === 'transfer-tab') {
                updateTransferDropdowns();
            }
        }

        // Utility function to get CSRF token
        function getCSRFToken() {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                return metaTag.getAttribute('content');
            }
            console.warn('CSRF token not found');
            return '';
        }

        // Handle Stripe PaymentIntent next_action property
        function getNextActionHtml(result) {
            if (!result.transfer || !result.transfer.next_action) {
                return '';
            }

            const nextAction = result.transfer.next_action;

            switch (nextAction.type) {
                case 'verify_with_microdeposits':
                    return getMicrodepositVerificationHtml(nextAction.verify_with_microdeposits, result.transfer.id);

                case 'use_stripe_sdk':
                    return getStripeSdkHtml(nextAction.use_stripe_sdk, result.transfer.id);

                default:
                    return `
                        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-top: 15px;">
                            <h4>⚠️ Additional Action Required</h4>
                            <p><strong>Type:</strong> ${nextAction.type}</p>
                            <p>Please check with your banking institution or contact support for assistance.</p>
                            <p><strong>Payment Intent ID:</strong> ${result.transfer.id}</p>
                        </div>
                    `;
            }
        }

        // Handle microdeposit verification
        function getMicrodepositVerificationHtml(microdepositData, paymentIntentId) {
            const arrivalDate = new Date(microdepositData.arrival_date * 1000);
            const formattedDate = arrivalDate.toLocaleDateString();

            return `
                <div style="background: #d4edda; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-top: 15px;">
                    <h4>💳 Bank Verification Required</h4>
                    <p><strong>Verification Method:</strong> ${microdepositData.microdeposit_type === 'descriptor_code' ? 'Statement Descriptor' : 'Microdeposits'}</p>
                    <p><strong>Expected Completion:</strong> ${formattedDate}</p>

                    ${microdepositData.microdeposit_type === 'descriptor_code' ?
                        `<p>📋 Check your bank statement for a descriptor code from Stripe, then verify using the link below.</p>` :
                        `<p>💰 Small test deposits will appear in your account. You'll need to verify the amounts.</p>`
                    }

                    ${microdepositData.hosted_verification_url ?
                        `<div style="margin-top: 15px;">
                            <a href="${microdepositData.hosted_verification_url}"
                               target="_blank"
                               style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">
                                🔗 Complete Verification
                            </a>
                            <p style="font-size: 12px; color: #666; margin-top: 8px;">Opens in new window</p>
                        </div>` : ''
                    }

                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #c3e6cb;">
                        <button onclick="checkVerificationStatus('${paymentIntentId}')"
                                style="background: #17a2b8; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px;">
                            🔄 Check Status
                        </button>
                        <button onclick="loadTransactions()"
                                style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                            📋 Refresh Transactions
                        </button>
                    </div>
                </div>
            `;
        }

        // Handle Stripe SDK actions
        function getStripeSdkHtml(sdkData, paymentIntentId) {
            return `
                <div style="background: #cce5ff; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; margin-top: 15px;">
                    <h4>🔐 Additional Authentication Required</h4>
                    <p>Your bank requires additional authentication to complete this transfer.</p>
                    <div style="margin-top: 15px;">
                        <button onclick="handleStripeAuthentication('${paymentIntentId}')"
                                style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                            🔒 Complete Authentication
                        </button>
                    </div>
                </div>
            `;
        }

        // Check verification status
        async function checkVerificationStatus(paymentIntentId) {
            try {
                const response = await fetch(`/plaid/check-verification-status/${paymentIntentId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCSRFToken()
                    }
                });

                const result = await response.json();

                if (result.success) {
                    const statusDiv = document.getElementById('transferStatus');
                    statusDiv.innerHTML = `
                        <div style="color: green;">
                            <h4>✅ Verification Status Updated</h4>
                            <p><strong>Current Status:</strong> ${result.status}</p>
                            ${result.status === 'succeeded' ?
                                '<p>🎉 Your transfer has been completed!</p>' :
                                '<p>⏳ Verification is still pending. Please check back later.</p>'
                            }
                        </div>
                    `;
                    loadTransactions();
                } else {
                    alert('Unable to check verification status. Please try again later.');
                }
            } catch (error) {
                console.error('Error checking verification status:', error);
                alert('Error checking verification status. Please try again later.');
            }
        }

        // Handle Stripe authentication (placeholder for future implementation)
        async function handleStripeAuthentication(paymentIntentId) {
            alert(`Authentication handling for ${paymentIntentId} will be implemented with Stripe Elements SDK`);
        }

        // Create User Form Handler
        document.getElementById('createUserForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch('/api/users', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCSRFToken()
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    alert('User created successfully!');
                    loadUsers();
                    e.target.reset();
                } else {
                    let errorMessage = result.message || 'Failed to create user';
                    if (result.message && result.message.includes('CSRF')) {
                        errorMessage += '\n\nPlease refresh the page and try again.';
                    }
                    alert('Error: ' + errorMessage);
                    if (result.errors) {
                        console.error('Validation errors:', result.errors);
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to create user: ' + error.message);
            }
        });

        // Load Users from API
        async function loadUsers() {
            try {
                const response = await fetch('/api/users');
                const result = await response.json();

                if (result.success) {
                    displayUsers(result.users);
                    await updateTransferDropdowns(); // Load bank accounts for transfers
                } else {
                    console.error('Failed to load users:', result);
                    document.getElementById('usersList').innerHTML = '<p>Failed to load users</p>';
                }
            } catch (error) {
                console.error('Error loading users:', error);
                document.getElementById('usersList').innerHTML = '<p>Error loading users</p>';
            }
        }

        // Display Users
        function displayUsers(users) {
            const usersList = document.getElementById('usersList');

            if (users.length === 0) {
                usersList.innerHTML = '<p>No users created yet</p>';
                return;
            }

            let html = '';
            users.forEach(user => {
                const statusClass = user.status === 'active' ? 'status-active' : 'status-pending';
                html += `
                    <div class="user-item">
                        <h4>${user.business_name}</h4>
                        <p><strong>Type:</strong> ${user.user_type}</p>
                        <p><strong>Email:</strong> ${user.email}</p>
                        <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${user.status}</span></p>
                    </div>
                `;
            });

            usersList.innerHTML = html;
            allUsers = users;
        }

        // Update Transfer Dropdowns with Bank Accounts
        async function updateTransferDropdowns() {
            try {
                const response = await fetch('/plaid/stored-accounts');
                const result = await response.json();

                const fromSelect = document.getElementById('fromAccount');
                const toSelect = document.getElementById('toAccount');

                // Clear existing options except first
                fromSelect.innerHTML = '<option value="">Select source bank account</option>';
                toSelect.innerHTML = '<option value="">Select destination bank account</option>';

                if (result.success && result.accounts.length > 0) {
                    result.accounts.forEach(account => {
                        const optionText = `${account.institution_name} - ${account.account_name} (User ID: ${account.user_id}) - ${account.formatted_available_balance || 'N/A'}`;
                        const option1 = `<option value="${account.id}" data-user-id="${account.user_id}">${optionText}</option>`;
                        const option2 = `<option value="${account.id}" data-user-id="${account.user_id}">${optionText}</option>`;
                        fromSelect.innerHTML += option1;
                        toSelect.innerHTML += option2;
                    });
                } else {
                    fromSelect.innerHTML += '<option value="" disabled>No bank accounts linked yet</option>';
                    toSelect.innerHTML += '<option value="" disabled>No bank accounts linked yet</option>';
                }
            } catch (error) {
                console.error('Error loading bank accounts for transfer:', error);
            }
        }

        // Handle ACH Transfer
        document.getElementById('transferForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            // Validate that from and to accounts are different
            if (data.from_account_id === data.to_account_id) {
                alert('Please select different accounts for source and destination');
                return;
            }

            // Show status
            const statusDiv = document.getElementById('transferStatus');
            statusDiv.innerHTML = '<p>🔄 Initiating ACH transfer...</p>';

            try {
                const response = await fetch('/plaid/create-ach-transfer', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCSRFToken()
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    // Handle next action if required
                    const nextActionHtml = getNextActionHtml(result);

                    statusDiv.innerHTML = `
                        <div style="color: green;">
                            <h4>✅ Real ACH Transfer Initiated!</h4>
                            <p><strong>Payment Intent ID:</strong> ${result.transfer.id}</p>
                            <p><strong>Amount:</strong> $${parseFloat(data.amount).toLocaleString()}</p>
                            <p><strong>Status:</strong> ${result.transfer.status}</p>
                            <p><strong>Network:</strong> ACH via Plaid + Stripe</p>
                            <p><strong>Estimated Completion:</strong> ${result.estimated_completion}</p>
                            <p><em>Real money movement initiated via secure bank account token. Updates will be provided via webhooks.</em></p>
                            ${nextActionHtml}
                        </div>
                    `;

                    // Refresh data
                    loadTransactions();
                    loadLinkedAccounts();
                    e.target.reset();
                } else {
                    let errorMessage = result.error || result.message || 'ACH transfer failed';

                    // Check if this is a relink error
                    if (result.requires_relink) {
                        statusDiv.innerHTML = `
                            <div style="color: #856404; background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                                <h4>⚠️ Account Connection Expired</h4>
                                <p><strong>Institution:</strong> ${result.institution_name || 'Bank'}</p>
                                <p><strong>Account:</strong> ${result.account_name || 'Account'}</p>
                                <p>${errorMessage}</p>
                                <div style="margin-top: 15px;">
                                    <button
                                        class="btn"
                                        onclick="relinkBankAccount('${result.account_id}', getUserIdForAccount('${result.account_id}'))"
                                        style="background: #ffc107; color: #212529; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;"
                                    >
                                        🔄 Reconnect This Account
                                    </button>
                                    <button
                                        class="btn"
                                        onclick="loadLinkedAccounts()"
                                        style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;"
                                    >
                                        📋 View All Accounts
                                    </button>
                                </div>
                            </div>
                        `;
                    } else {
                        statusDiv.innerHTML = `
                            <div style="color: red;">
                                <h4>❌ Real ACH Transfer Failed</h4>
                                <p>${errorMessage}</p>
                                ${result.decline_code ? `<p><strong>Decline Code:</strong> ${result.decline_code}</p>` : ''}
                            </div>
                        `;
                    }
                    console.error('Real ACH transfer error details:', result);
                }
            } catch (error) {
                console.error('Error creating ACH transfer:', error);
                statusDiv.innerHTML = `
                    <div style="color: red;">
                        <h4>❌ Transfer Failed</h4>
                        <p>Network error: ${error.message}</p>
                    </div>
                `;
            }
        });

        // Load Transactions
        async function loadTransactions() {
            try {
                const response = await fetch('/api/stripe/transactions');
                const result = await response.json();

                if (result.success) {
                    displayTransactions(result.transactions.data || []);
                } else {
                    document.getElementById('transactionsList').innerHTML = '<p>Failed to load transactions</p>';
                }
            } catch (error) {
                console.error('Error:', error);
                // Show mock transactions for demo
                const mockTransactions = [
                    {
                        id: 1,
                        amount: 15000,
                        status: 'succeeded',
                        description: 'Business loan transfer',
                        created_at: '2025-09-26T15:30:00Z',
                        fromAccount: {business_name: 'ABC Lending Corp'},
                        toAccount: {business_name: 'XYZ Business'}
                    }
                ];
                displayTransactions(mockTransactions);
            }
        }

        // Display Transactions
        function displayTransactions(transactions) {
            const transactionsList = document.getElementById('transactionsList');

            if (transactions.length === 0) {
                transactionsList.innerHTML = '<p>No transactions yet</p>';
                return;
            }

            let html = '';
            transactions.forEach(transaction => {
                const statusClass = transaction.status === 'succeeded' ? 'status-succeeded' : 'status-pending';
                const date = new Date(transaction.created_at).toLocaleDateString();
                html += `
                    <div class="transaction-item">
                        <h4>$${parseFloat(transaction.amount).toLocaleString()}</h4>
                        <p><strong>From:</strong> ${transaction.fromAccount?.business_name || 'Unknown'}</p>
                        <p><strong>To:</strong> ${transaction.toAccount?.business_name || 'Unknown'}</p>
                        <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${transaction.status}</span></p>
                        <p><strong>Date:</strong> ${date}</p>
                        <p>${transaction.description}</p>
                    </div>
                `;
            });

            transactionsList.innerHTML = html;
        }

        // Stripe Status Functions
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
                        'X-CSRF-TOKEN': getCSRFToken()
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

        async function loadStripeTransactions() {
            const listDiv = document.getElementById('stripeTransactionsList');
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
                        'X-CSRF-TOKEN': getCSRFToken()
                    },
                    body: JSON.stringify({ transaction_id: transactionId })
                });

                const data = await response.json();

                if (data.success) {
                    alert(`Transaction synced: ${data.old_status} → ${data.new_status}`);
                    loadStripeTransactions(); // Refresh the list
                } else {
                    alert(`Sync failed: ${data.error}`);
                }
            } catch (error) {
                alert(`Network error: ${error.message}`);
            }
        }

        // Plaid Link Integration
        let plaidLinkHandler;
        let selectedUserId = null;

        async function initializePlaidLink() {
            try {
                // Get link token from backend
                const response = await fetch('/plaid/create-link-token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCSRFToken()
                    },
                    body: JSON.stringify({
                        user_id: selectedUserId || 'demo_user_' + Date.now()
                    })
                });

                const data = await response.json();

                if (!data.link_token) {
                    throw new Error('Failed to get link token');
                }

                plaidLinkHandler = Plaid.create({
                    token: data.link_token,
                    onSuccess: async (public_token, metadata) => {
                        console.log('Plaid Link success:', metadata);
                        await exchangePlaidToken(public_token, metadata);
                    },
                    onLoad: () => {
                        console.log('Plaid Link loaded');
                    },
                    onExit: (err, metadata) => {
                        if (err) {
                            console.error('Plaid Link exited with error:', err);
                            alert('Failed to link bank account: ' + err.error_message);
                        } else {
                            console.log('User exited Plaid Link');
                        }
                    },
                    onEvent: (eventName, metadata) => {
                        console.log('Plaid Link event:', eventName, metadata);
                    }
                });

            } catch (error) {
                console.error('Error initializing Plaid Link:', error);
                alert('Failed to initialize bank account linking: ' + error.message);
            }
        }

        async function exchangePlaidToken(publicToken, metadata) {
            try {
                const response = await fetch('/plaid/token-exchange', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCSRFToken()
                    },
                    body: JSON.stringify({
                        public_token: publicToken,
                        metadata: metadata,
                        user_id: selectedUserId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert(`Bank account linked successfully!
                    Institution: ${metadata.institution.name}
                    Account: ${metadata.accounts[0].name}`);
                    loadLinkedAccounts();
                } else {
                    throw new Error(result.message || 'Token exchange failed');
                }

            } catch (error) {
                console.error('Error exchanging token:', error);
                alert('Failed to complete bank account linking: ' + error.message);
            }
        }

        // Load all linked accounts (for display purposes)
        async function loadLinkedAccounts() {
            console.log('🔍 Loading linked accounts...');
            try {
                const response = await fetch('/plaid/stored-accounts');
                const result = await response.json();

                console.log('📋 API Response:', result);

                const linkedAccountsDiv = document.getElementById('linkedAccounts');
                console.log('📍 Found linkedAccounts div:', !!linkedAccountsDiv);

                if (result.success && result.accounts.length > 0) {
                    console.log('✅ Found', result.accounts.length, 'accounts');
                    let html = '<h4>All Linked Bank Accounts:</h4>';
                    result.accounts.forEach(account => {
                        const requiresRelink = account.requires_relink || account.status === 'requires_relink';
                        const statusColor = requiresRelink ? '#dc3545' : '#28a745';
                        const statusText = requiresRelink ? '⚠️ Needs Relinking' : '✅ Connected';
                        const statusBg = requiresRelink ? '#f8d7da' : '#d4edda';

                        html += `
                            <div class="user-item" style="margin: 10px 0; border-left-color: ${statusColor};">
                                <div style="display: flex; justify-content: between; align-items: flex-start;">
                                    <div style="flex: 1;">
                                        <h4>🏦 ${account.institution_name}</h4>
                                        <p><strong>Account:</strong> ${account.account_name}</p>
                                        <p><strong>Type:</strong> ${account.account_type}</p>
                                        <p><strong>User ID:</strong> ${account.user_id}</p>
                                        <p><strong>Status:</strong> <span class="status-badge" style="background: ${statusBg}; color: ${statusColor};">${statusText}</span></p>
                                        <p><strong>Stripe Integration:</strong> <span class="status-badge" style="background: ${account.has_stripe_token ? '#d4edda' : '#fff3cd'}; color: ${account.has_stripe_token ? '#155724' : '#856404'};">${account.stripe_status_display}</span></p>
                                        ${account.stripe_token_created_at ? `<p style="font-size: 0.85rem; color: #666;"><strong>Token Created:</strong> ${account.stripe_token_created_at}</p>` : ''}
                                        ${requiresRelink ?
                                            `<p style="color: ${statusColor}; margin-top: 10px;"><strong>Issue:</strong> ${account.error_message || 'Connection expired - please reconnect this account'}</p>` :
                                            `<div>
                                                <p><strong>Available Balance:</strong> ${account.formatted_available_balance || 'N/A'}</p>
                                                <p><strong>Current Balance:</strong> ${account.formatted_current_balance || 'N/A'}</p>
                                            </div>`
                                        }
                                        ${account.has_stripe_token ?
                                            '<div style="background: #d4edda; padding: 10px; border-radius: 4px; margin-top: 10px; border-left: 3px solid #28a745;"><p style="margin: 0; font-size: 0.9rem; color: #155724;">✅ <strong>Ready for ACH Transfers:</strong> This account has a valid Stripe bank account token and can be used for money transfers.</p></div>' :
                                            '<div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin-top: 10px; border-left: 3px solid #ffc107;"><p style="margin: 0; font-size: 0.9rem; color: #856404;">⚠️ <strong>Stripe Token Needed:</strong> This account needs a Stripe bank account token to process transfers. Try reconnecting the account.</p></div>'
                                        }
                                    </div>
                                </div>
                                <div style="margin-top: 15px;">
                                    ${requiresRelink ?
                                        `<div style="display: flex; gap: 10px;">
                                            <button
                                                onclick="relinkBankAccount('${account.id}', '${account.user_id}')"
                                                class="btn btn-warning btn-sm"
                                                style="background: #ffc107; color: #212529; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; transition: all 0.3s ease;"
                                                onmouseover="this.style.background='#e0a800'"
                                                onmouseout="this.style.background='#ffc107'"
                                            >
                                                🔄 Reconnect Account
                                            </button>
                                            <button
                                                onclick="disconnectBankAccount('${account.id}')"
                                                class="btn btn-danger btn-sm"
                                                style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; transition: all 0.3s ease;"
                                                onmouseover="this.style.background='#c82333'"
                                                onmouseout="this.style.background='#dc3545'"
                                            >
                                                🗑️ Remove Account
                                            </button>
                                        </div>` :
                                        `<button
                                            onclick="disconnectBankAccount('${account.id}')"
                                            class="btn btn-danger btn-sm"
                                            style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; transition: all 0.3s ease;"
                                            onmouseover="this.style.background='#c82333'"
                                            onmouseout="this.style.background='#dc3545'"
                                        >
                                            🗑️ Disconnect Account
                                        </button>`
                                    }
                                </div>
                            </div>
                        `;
                    });
                    linkedAccountsDiv.innerHTML = html;
                    console.log('✅ Accounts displayed successfully');
                } else {
                    console.log('⚠️ No accounts found or API error');
                    linkedAccountsDiv.innerHTML = '<p>No bank accounts linked yet</p>';
                }
            } catch (error) {
                console.error('❌ Error loading linked accounts:', error);
                document.getElementById('linkedAccounts').innerHTML = '<p>Error loading accounts</p>';
            }
        }

        // Load accounts for a specific user
        async function loadUserAccounts(userId) {
            try {
                const response = await fetch('/plaid/user-accounts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCSRFToken()
                    },
                    body: JSON.stringify({ user_id: userId })
                });

                if (!response.ok) {
                    throw new Error(`Failed to load user accounts: ${response.status}`);
                }

                const result = await response.json();
                return result.accounts || [];
            } catch (error) {
                console.error('Error loading user accounts:', error);
                return [];
            }
        }

        // Bank account linking with user selection
        function promptUserSelection() {
            if (allUsers.length === 0) {
                alert('Please create some users first before linking bank accounts');
                return;
            }

            let userOptions = allUsers.map((user, index) =>
                `${index + 1}. ${user.business_name} (${user.user_type})`
            ).join('\n');

            let selection = prompt(`Select a user to link bank account:\n\n${userOptions}\n\nEnter number (1-${allUsers.length}):`);

            if (selection && !isNaN(selection)) {
                let userIndex = parseInt(selection) - 1;
                if (userIndex >= 0 && userIndex < allUsers.length) {
                    selectedUserId = allUsers[userIndex].id;
                    return allUsers[userIndex];
                }
            }
            return null;
        }

        // Disconnect bank account function
        async function disconnectBankAccount(accountId) {
            if (!confirm('Are you sure you want to disconnect this bank account? This action cannot be undone.')) {
                return;
            }

            try {
                console.log('🗑️ Disconnecting account:', accountId);
                const response = await fetch(`/plaid/accounts/${accountId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();
                console.log('📋 Disconnect Response:', result);

                if (result.success) {
                    alert('Bank account disconnected successfully!');
                    // Reload the accounts display
                    await loadLinkedAccounts();
                    // Update transfer dropdowns to remove disconnected account
                    await updateTransferDropdowns();
                } else {
                    alert('Failed to disconnect account: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('❌ Error disconnecting account:', error);
                alert('Error disconnecting account: ' + error.message);
            }
        }

        // Relink bank account function
        async function relinkBankAccount(accountId, userId) {
            if (!confirm('This will disconnect the expired account and start the process to reconnect it. Continue?')) {
                return;
            }

            try {
                console.log('🔄 Relinking account:', accountId, 'for user:', userId);

                // First disconnect the old account
                const disconnectResponse = await fetch(`/plaid/accounts/${accountId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                });

                const disconnectResult = await disconnectResponse.json();

                if (disconnectResult.success) {
                    // Set the selected user for linking
                    const user = allUsers.find(u => u.id == userId);
                    if (user) {
                        selectedUserId = parseInt(userId);
                        alert(`Old connection removed. Now linking new account for ${user.business_name}...`);

                        // Initialize and open Plaid Link
                        await initializePlaidLink();
                        if (plaidLinkHandler) {
                            plaidLinkHandler.open();
                        }
                    } else {
                        alert('User not found. Please refresh the page and try again.');
                    }
                } else {
                    alert('Failed to disconnect old account: ' + (disconnectResult.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('❌ Error relinking account:', error);
                alert('Error relinking account: ' + error.message);
            }
        }

        // Helper function to get user ID for a given account ID
        function getUserIdForAccount(accountId) {
            // This would need to be populated when accounts are loaded
            // For now, we'll need to get it from the transfer dropdowns or stored data
            const fromSelect = document.getElementById('fromAccount');
            const toSelect = document.getElementById('toAccount');

            for (let option of [...fromSelect.options, ...toSelect.options]) {
                if (option.value === accountId) {
                    return option.getAttribute('data-user-id');
                }
            }

            // Fallback - ask user to select
            return promptUserSelection()?.id;
        }

        // ========== HEALTH MONITORING FUNCTIONS ==========

        // Load service health status
        async function loadServiceHealth() {
            try {
                const response = await fetch('/health/services');
                const result = await response.json();

                const statusDiv = document.getElementById('serviceHealthStatus');

                let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">';

                // Plaid Status
                const plaidStatus = result.services.plaid;
                const plaidColor = plaidStatus.status === 'healthy' ? '#28a745' :
                                 plaidStatus.status === 'disabled' ? '#ffc107' : '#dc3545';
                const plaidBg = plaidStatus.status === 'healthy' ? '#d4edda' :
                              plaidStatus.status === 'disabled' ? '#fff3cd' : '#f8d7da';
                html += `
                    <div class="user-item" style="border-left-color: ${plaidColor};">
                        <h4 style="color: ${plaidColor};">🔗 Plaid API</h4>
                        <p><strong>Status:</strong> <span class="status-badge" style="background: ${plaidBg}; color: ${plaidColor};">${plaidStatus.status.toUpperCase()}</span></p>
                        <p><strong>Response Time:</strong> ${plaidStatus.response_time || 'N/A'}</p>
                        <p><strong>Last Check:</strong> ${new Date(plaidStatus.last_check).toLocaleString()}</p>
                        ${plaidStatus.manually_disabled ? '<p style="color: #856404;"><strong>⚠️ Manually disabled for testing</strong></p>' : ''}
                        ${plaidStatus.error && !plaidStatus.manually_disabled ? `<p style="color: #dc3545;"><strong>Error:</strong> ${plaidStatus.error}</p>` : ''}
                    </div>
                `;

                // Stripe Status
                const stripeStatus = result.services.stripe;
                const stripeColor = stripeStatus.status === 'healthy' ? '#28a745' :
                                  stripeStatus.status === 'disabled' ? '#ffc107' : '#dc3545';
                const stripeBg = stripeStatus.status === 'healthy' ? '#d4edda' :
                               stripeStatus.status === 'disabled' ? '#fff3cd' : '#f8d7da';
                html += `
                    <div class="user-item" style="border-left-color: ${stripeColor};">
                        <h4 style="color: ${stripeColor};">💳 Stripe API</h4>
                        <p><strong>Status:</strong> <span class="status-badge" style="background: ${stripeBg}; color: ${stripeColor};">${stripeStatus.status.toUpperCase()}</span></p>
                        <p><strong>Response Time:</strong> ${stripeStatus.response_time || 'N/A'}</p>
                        <p><strong>Last Check:</strong> ${new Date(stripeStatus.last_check).toLocaleString()}</p>
                        ${stripeStatus.manually_disabled ? '<p style="color: #856404;"><strong>⚠️ Manually disabled for testing</strong></p>' : ''}
                        ${stripeStatus.error && !stripeStatus.manually_disabled ? `<p style="color: #dc3545;"><strong>Error:</strong> ${stripeStatus.error}</p>` : ''}
                    </div>
                `;

                html += '</div>';

                // Overall status
                const overallColor = result.overall_status === 'healthy' ? '#28a745' :
                                   result.overall_status === 'testing' ? '#6f42c1' : '#ffc107';
                const overallBg = result.overall_status === 'healthy' ? '#d4edda' :
                                result.overall_status === 'testing' ? '#e2d9f3' : '#fff3cd';
                const statusEmoji = result.overall_status === 'healthy' ? '🏥' :
                                  result.overall_status === 'testing' ? '🧪' : '⚠️';
                html += `
                    <div class="success-box" style="margin-top: 20px; background: linear-gradient(135deg, ${overallBg} 0%, ${overallBg}80 100%); border-left-color: ${overallColor};">
                        <h4 style="color: ${overallColor};">${statusEmoji} Overall System Status: ${result.overall_status.toUpperCase()}</h4>
                        <p>Last checked: ${new Date(result.checked_at).toLocaleString()}</p>
                        ${result.overall_status === 'testing' ? '<p style="color: #6f42c1;"><strong>🧪 One or more services manually disabled for testing</strong></p>' : ''}
                    </div>
                `;

                statusDiv.innerHTML = html;

            } catch (error) {
                console.error('Error loading service health:', error);
                document.getElementById('serviceHealthStatus').innerHTML =
                    '<div class="info-box" style="border-left-color: #dc3545;"><p style="color: #dc3545;">Error loading service health status</p></div>';
            }
        }

        // Load queued transactions
        async function loadQueuedTransactions() {
            try {
                const response = await fetch('/health/queue');
                const result = await response.json();

                if (result.success) {
                    // Update statistics
                    const statsDiv = document.getElementById('queueStats');
                    const stats = result.stats;

                    let statsHtml = `
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-top: 15px;">
                            <div class="status-badge" style="background: #cce5ff; color: #004085; text-align: center; padding: 10px;">
                                <div style="font-size: 1.5rem; font-weight: bold;">${stats.total}</div>
                                <div style="font-size: 0.8rem;">Total</div>
                            </div>
                            <div class="status-badge" style="background: #fff3cd; color: #856404; text-align: center; padding: 10px;">
                                <div style="font-size: 1.5rem; font-weight: bold;">${stats.queued}</div>
                                <div style="font-size: 0.8rem;">Queued</div>
                            </div>
                            <div class="status-badge" style="background: #e2e3e5; color: #6c757d; text-align: center; padding: 10px;">
                                <div style="font-size: 1.5rem; font-weight: bold;">${stats.retrying}</div>
                                <div style="font-size: 0.8rem;">Retrying</div>
                            </div>
                            <div class="status-badge" style="background: #f8d7da; color: #721c24; text-align: center; padding: 10px;">
                                <div style="font-size: 1.5rem; font-weight: bold;">${stats.failed}</div>
                                <div style="font-size: 0.8rem;">Failed</div>
                            </div>
                            <div class="status-badge" style="background: #d4edda; color: #155724; text-align: center; padding: 10px;">
                                <div style="font-size: 1.5rem; font-weight: bold;">${stats.ready_for_retry}</div>
                                <div style="font-size: 0.8rem;">Ready</div>
                            </div>
                        </div>
                    `;

                    statsDiv.innerHTML = statsHtml;

                    // Update transactions list
                    const listDiv = document.getElementById('queuedTransactionsList');

                    if (result.transactions.length === 0) {
                        listDiv.innerHTML = '<div class="info-box"><p>No transactions in queue</p></div>';
                    } else {
                        let listHtml = '';
                        result.transactions.forEach(transaction => {
                            const statusColor = transaction.status === 'queued' ? '#ffc107' :
                                              transaction.status === 'retrying' ? '#6c757d' :
                                              transaction.status === 'failed' ? '#dc3545' : '#28a745';

                            listHtml += `
                                <div class="transaction-item">
                                    <div style="display: flex; justify-content: between; align-items: center;">
                                        <div style="flex: 1;">
                                            <h4>Transaction #${transaction.id}</h4>
                                            <p><strong>Amount:</strong> $${parseFloat(transaction.amount).toFixed(2)}</p>
                                            <p><strong>Failed Service:</strong> ${transaction.failed_service.toUpperCase()}</p>
                                            <p><strong>Status:</strong> <span class="status-badge" style="background: ${statusColor}20; color: ${statusColor};">${transaction.status.toUpperCase()}</span></p>
                                            <p><strong>Retry Count:</strong> ${transaction.retry_count}/${transaction.max_retries}</p>
                                            <p><strong>Failed At:</strong> ${new Date(transaction.failed_at).toLocaleString()}</p>
                                            ${transaction.next_retry_at ? `<p><strong>Next Retry:</strong> ${new Date(transaction.next_retry_at).toLocaleString()}</p>` : ''}
                                        </div>
                                        <div style="margin-left: 20px;">
                                            ${transaction.status === 'queued' && transaction.retry_count < transaction.max_retries ?
                                                `<button class="btn btn-small" onclick="retryTransaction(${transaction.id})" style="background: #28a745; color: white;">🔄 Retry Now</button>` :
                                                ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });

                        listDiv.innerHTML = listHtml;
                    }
                } else {
                    throw new Error(result.error || 'Failed to load queue');
                }

            } catch (error) {
                console.error('Error loading queued transactions:', error);
                document.getElementById('queueStats').innerHTML =
                    '<div class="info-box" style="border-left-color: #dc3545;"><p style="color: #dc3545;">Error loading queue statistics</p></div>';
                document.getElementById('queuedTransactionsList').innerHTML =
                    '<div class="info-box" style="border-left-color: #dc3545;"><p style="color: #dc3545;">Error loading queued transactions</p></div>';
            }
        }

        // Retry a specific transaction
        async function retryTransaction(transactionId) {
            try {
                const response = await fetch(`/health/retry/${transactionId}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('Transaction retry initiated successfully!');
                    loadQueuedTransactions(); // Refresh the queue
                } else {
                    alert('Failed to retry transaction: ' + result.error);
                }

            } catch (error) {
                console.error('Error retrying transaction:', error);
                alert('Error retrying transaction: ' + error.message);
            }
        }

        // Process entire queue
        async function processQueue() {
            try {
                const response = await fetch('/health/process-queue', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert(`Queue processing completed!\nProcessed: ${result.processed}\nFailed: ${result.failed}\nTotal Ready: ${result.total_ready}`);
                    loadQueuedTransactions(); // Refresh the queue
                    loadServiceHealth(); // Refresh health status
                } else {
                    alert('Failed to process queue: ' + result.error);
                }

            } catch (error) {
                console.error('Error processing queue:', error);
                alert('Error processing queue: ' + error.message);
            }
        }

        // ========== SETTINGS FUNCTIONS ==========

        // Load service controls
        async function loadServiceControls() {
            try {
                const response = await fetch('/settings/services/status');
                const result = await response.json();

                if (result.success) {
                    const controlsDiv = document.getElementById('serviceControls');
                    const services = result.services;

                    let html = '';

                    Object.entries(services).forEach(([serviceName, serviceData]) => {
                        const isEnabled = serviceData.enabled;
                        const toggleColor = isEnabled ? '#28a745' : '#dc3545';
                        const toggleText = isEnabled ? 'ENABLED' : 'DISABLED';
                        const buttonText = isEnabled ? 'Disable' : 'Enable';
                        const buttonColor = isEnabled ? '#dc3545' : '#28a745';

                        html += `
                            <div class="user-item" style="margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <h4 style="color: ${toggleColor};">${serviceName.toUpperCase()} Service</h4>
                                        <p>${serviceData.description}</p>
                                        <p><strong>Status:</strong> <span class="status-badge" style="background: ${toggleColor}20; color: ${toggleColor};">${toggleText}</span></p>
                                    </div>
                                    <div>
                                        <button
                                            class="btn"
                                            onclick="toggleService('${serviceName}', ${!isEnabled})"
                                            style="background: ${buttonColor}; color: white; padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600;"
                                        >
                                            ${buttonText} ${serviceName.toUpperCase()}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    controlsDiv.innerHTML = html;
                } else {
                    throw new Error(result.error || 'Failed to load service controls');
                }

            } catch (error) {
                console.error('Error loading service controls:', error);
                document.getElementById('serviceControls').innerHTML =
                    '<div class="info-box" style="border-left-color: #dc3545;"><p style="color: #dc3545;">Error loading service controls</p></div>';
            }
        }

        // Toggle a service on/off
        async function toggleService(serviceName, enabled) {
            try {
                const response = await fetch(`/settings/toggle-service/${serviceName}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ enabled: enabled })
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    loadServiceControls(); // Refresh controls

                    // Also refresh health monitor if it's loaded
                    if (document.getElementById('serviceHealthStatus').innerHTML !== '') {
                        loadServiceHealth();
                    }
                } else {
                    alert('Failed to toggle service: ' + result.error);
                }

            } catch (error) {
                console.error('Error toggling service:', error);
                alert('Error toggling service: ' + error.message);
            }
        }

        // Load all settings
        async function loadAllSettings() {
            try {
                const response = await fetch('/settings');
                const result = await response.json();

                if (result.success) {
                    const listDiv = document.getElementById('allSettingsList');
                    const settings = result.settings;

                    if (Object.keys(settings).length === 0) {
                        listDiv.innerHTML = '<div class="info-box"><p>No settings configured</p></div>';
                        return;
                    }

                    let html = '';
                    let systemHtml = '';

                    Object.entries(settings).forEach(([key, setting]) => {
                        const value = setting.value;
                        const type = setting.type;
                        const description = setting.description || 'No description';

                        // Add to system settings if not a service toggle
                        if (key !== 'plaid_enabled' && key !== 'stripe_enabled') {
                            if (type === 'boolean') {
                                systemHtml += `
                                    <div class="form-group">
                                        <label style="display: flex; align-items: center; gap: 10px;">
                                            <input
                                                type="checkbox"
                                                id="setting_${key}"
                                                ${value ? 'checked' : ''}
                                                style="transform: scale(1.2);"
                                            />
                                            <span><strong>${key.replace(/_/g, ' ').toUpperCase()}</strong></span>
                                        </label>
                                        <p style="margin: 5px 0 0 32px; color: #666; font-size: 0.9rem;">${description}</p>
                                    </div>
                                `;
                            } else if (type === 'integer') {
                                systemHtml += `
                                    <div class="form-group">
                                        <label><strong>${key.replace(/_/g, ' ').toUpperCase()}:</strong></label>
                                        <input
                                            type="number"
                                            id="setting_${key}"
                                            value="${value}"
                                            style="width: 100px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                                        />
                                        <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9rem;">${description}</p>
                                    </div>
                                `;
                            } else {
                                systemHtml += `
                                    <div class="form-group">
                                        <label><strong>${key.replace(/_/g, ' ').toUpperCase()}:</strong></label>
                                        <input
                                            type="text"
                                            id="setting_${key}"
                                            value="${value}"
                                            style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                                        />
                                        <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9rem;">${description}</p>
                                    </div>
                                `;
                            }
                        }

                        // Add to full list
                        const displayValue = type === 'boolean' ? (value ? '✅ True' : '❌ False') : value;
                        html += `
                            <div class="transaction-item">
                                <h4>${key}</h4>
                                <p><strong>Value:</strong> ${displayValue}</p>
                                <p><strong>Type:</strong> ${type}</p>
                                <p><strong>Description:</strong> ${description}</p>
                            </div>
                        `;
                    });

                    // Update system settings
                    document.getElementById('systemSettings').innerHTML = systemHtml;

                    // Update full list
                    listDiv.innerHTML = html;
                } else {
                    throw new Error(result.error || 'Failed to load settings');
                }

            } catch (error) {
                console.error('Error loading settings:', error);
                document.getElementById('allSettingsList').innerHTML =
                    '<div class="info-box" style="border-left-color: #dc3545;"><p style="color: #dc3545;">Error loading settings</p></div>';
            }
        }

        // Save settings
        async function saveSettings() {
            try {
                const settingsToUpdate = [];

                // Get all setting inputs
                const settingInputs = document.querySelectorAll('[id^="setting_"]');

                settingInputs.forEach(input => {
                    const key = input.id.replace('setting_', '');
                    let value = input.value;
                    let type = 'string';

                    if (input.type === 'checkbox') {
                        value = input.checked;
                        type = 'boolean';
                    } else if (input.type === 'number') {
                        value = parseInt(value);
                        type = 'integer';
                    }

                    settingsToUpdate.push({
                        key: key,
                        value: value,
                        type: type
                    });
                });

                if (settingsToUpdate.length === 0) {
                    alert('No settings to save');
                    return;
                }

                const response = await fetch('/settings/bulk-update', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ settings: settingsToUpdate })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Settings saved successfully!');
                    loadAllSettings(); // Refresh the display
                } else {
                    alert('Failed to save settings: ' + result.error);
                }

            } catch (error) {
                console.error('Error saving settings:', error);
                alert('Error saving settings: ' + error.message);
            }
        }

        // Reset all settings
        async function resetAllSettings() {
            if (!confirm('Are you sure you want to reset all settings to their default values?')) {
                return;
            }

            try {
                const response = await fetch('/settings/reset-defaults', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('All settings reset to defaults!');
                    loadServiceControls();
                    loadAllSettings();

                    // Refresh health monitor if loaded
                    if (document.getElementById('serviceHealthStatus').innerHTML !== '') {
                        loadServiceHealth();
                    }
                } else {
                    alert('Failed to reset settings: ' + result.error);
                }

            } catch (error) {
                console.error('Error resetting settings:', error);
                alert('Error resetting settings: ' + error.message);
            }
        }

        // ========== SETTINGS FUNCTIONS ==========

        // Load service controls (toggle switches)
        async function loadServiceControls() {
            try {
                const response = await fetch('/settings/services/status');
                const result = await response.json();

                if (result.success) {
                    const controlsDiv = document.getElementById('serviceControls');
                    let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">';

                    // Plaid toggle
                    const plaidEnabled = result.services.plaid.enabled;
                    html += `
                        <div class="user-item" style="border-left-color: ${plaidEnabled ? '#28a745' : '#dc3545'};">
                            <div style="display: flex; justify-content: between; align-items: center;">
                                <div style="flex: 1;">
                                    <h4 style="color: ${plaidEnabled ? '#28a745' : '#dc3545'};">🔗 Plaid Service</h4>
                                    <p>${result.services.plaid.description}</p>
                                    <p><strong>Status:</strong> <span class="status-badge" style="background: ${plaidEnabled ? '#d4edda' : '#f8d7da'}; color: ${plaidEnabled ? '#155724' : '#721c24'};">${plaidEnabled ? 'ENABLED' : 'DISABLED'}</span></p>
                                </div>
                                <div style="margin-left: 20px;">
                                    <label class="toggle-switch">
                                        <input type="checkbox" ${plaidEnabled ? 'checked' : ''} onchange="toggleService('plaid', this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    `;

                    // Stripe toggle
                    const stripeEnabled = result.services.stripe.enabled;
                    html += `
                        <div class="user-item" style="border-left-color: ${stripeEnabled ? '#28a745' : '#dc3545'};">
                            <div style="display: flex; justify-content: between; align-items: center;">
                                <div style="flex: 1;">
                                    <h4 style="color: ${stripeEnabled ? '#28a745' : '#dc3545'};">💳 Stripe Service</h4>
                                    <p>${result.services.stripe.description}</p>
                                    <p><strong>Status:</strong> <span class="status-badge" style="background: ${stripeEnabled ? '#d4edda' : '#f8d7da'}; color: ${stripeEnabled ? '#155724' : '#721c24'};">${stripeEnabled ? 'ENABLED' : 'DISABLED'}</span></p>
                                </div>
                                <div style="margin-left: 20px;">
                                    <label class="toggle-switch">
                                        <input type="checkbox" ${stripeEnabled ? 'checked' : ''} onchange="toggleService('stripe', this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    `;

                    html += '</div>';
                    controlsDiv.innerHTML = html;
                } else {
                    throw new Error(result.error || 'Failed to load service controls');
                }

            } catch (error) {
                console.error('Error loading service controls:', error);
                document.getElementById('serviceControls').innerHTML =
                    '<div class="info-box" style="border-left-color: #dc3545;"><p style="color: #dc3545;">Error loading service controls</p></div>';
            }
        }

        // Toggle a service on/off
        async function toggleService(service, enabled) {
            try {
                const response = await fetch(`/settings/toggle-service/${service}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ enabled: enabled })
                });

                const result = await response.json();

                if (result.success) {
                    alert(`${service.charAt(0).toUpperCase() + service.slice(1)} service ${enabled ? 'enabled' : 'disabled'} successfully!`);
                    loadServiceControls(); // Refresh the controls
                    loadServiceHealth(); // Refresh health status if on health tab
                } else {
                    alert('Failed to toggle service: ' + result.error);
                    loadServiceControls(); // Refresh to revert toggle
                }

            } catch (error) {
                console.error('Error toggling service:', error);
                alert('Error toggling service: ' + error.message);
                loadServiceControls(); // Refresh to revert toggle
            }
        }

        // Load system settings
        async function loadSystemSettings() {
            try {
                const response = await fetch('/settings');
                const result = await response.json();

                if (result.success) {
                    const settingsDiv = document.getElementById('systemSettings');
                    let html = '<div style="display: grid; gap: 15px; margin-top: 15px;">';

                    // Queue auto retry setting
                    const queueAutoRetry = result.settings.queue_auto_retry ? result.settings.queue_auto_retry.value : true;
                    html += `
                        <div class="user-item">
                            <div style="display: flex; justify-content: between; align-items: center;">
                                <div style="flex: 1;">
                                    <h4>🔄 Auto Retry Queued Transactions</h4>
                                    <p>${result.settings.queue_auto_retry ? result.settings.queue_auto_retry.description : 'Automatically retry queued transactions when services become available'}</p>
                                </div>
                                <div style="margin-left: 20px;">
                                    <label class="toggle-switch">
                                        <input type="checkbox" ${queueAutoRetry ? 'checked' : ''} id="queueAutoRetry">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    `;

                    // Max retry attempts
                    const maxRetries = result.settings.max_retry_attempts ? result.settings.max_retry_attempts.value : 3;
                    html += `
                        <div class="user-item">
                            <div>
                                <h4>🔢 Maximum Retry Attempts</h4>
                                <p>${result.settings.max_retry_attempts ? result.settings.max_retry_attempts.description : 'Maximum number of retry attempts for failed transactions'}</p>
                                <div style="margin-top: 10px;">
                                    <input type="number" id="maxRetries" value="${maxRetries}" min="1" max="10" style="width: 80px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                            </div>
                        </div>
                    `;

                    html += '</div>';
                    settingsDiv.innerHTML = html;
                } else {
                    throw new Error(result.error || 'Failed to load system settings');
                }

            } catch (error) {
                console.error('Error loading system settings:', error);
                document.getElementById('systemSettings').innerHTML =
                    '<div class="info-box" style="border-left-color: #dc3545;"><p style="color: #dc3545;">Error loading system settings</p></div>';
            }
        }

        // Save settings
        async function saveSettings() {
            try {
                const queueAutoRetry = document.getElementById('queueAutoRetry').checked;
                const maxRetries = parseInt(document.getElementById('maxRetries').value);

                const settings = [
                    {
                        key: 'queue_auto_retry',
                        value: queueAutoRetry,
                        type: 'boolean',
                        description: 'Automatically retry queued transactions when services become available'
                    },
                    {
                        key: 'max_retry_attempts',
                        value: maxRetries,
                        type: 'integer',
                        description: 'Maximum number of retry attempts for failed transactions'
                    }
                ];

                const response = await fetch('/settings/bulk-update', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ settings: settings })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Settings saved successfully!');
                } else {
                    alert('Failed to save settings: ' + result.error);
                }

            } catch (error) {
                console.error('Error saving settings:', error);
                alert('Error saving settings: ' + error.message);
            }
        }

        // Load all settings
        async function loadAllSettings() {
            try {
                const response = await fetch('/settings');
                const result = await response.json();

                if (result.success) {
                    const listDiv = document.getElementById('allSettingsList');

                    if (Object.keys(result.settings).length === 0) {
                        listDiv.innerHTML = '<div class="info-box"><p>No settings found</p></div>';
                    } else {
                        let html = '<div style="display: grid; gap: 10px; margin-top: 15px;">';

                        Object.entries(result.settings).forEach(([key, setting]) => {
                            const valueDisplay = setting.type === 'boolean' ?
                                (setting.value ? 'TRUE' : 'FALSE') :
                                setting.value.toString();

                            html += `
                                <div class="user-item">
                                    <h4>${key}</h4>
                                    <p><strong>Value:</strong> <span class="status-badge" style="background: #e9ecef; color: #495057;">${valueDisplay}</span></p>
                                    <p><strong>Type:</strong> ${setting.type}</p>
                                    ${setting.description ? `<p><strong>Description:</strong> ${setting.description}</p>` : ''}
                                </div>
                            `;
                        });

                        html += '</div>';
                        listDiv.innerHTML = html;
                    }
                } else {
                    throw new Error(result.error || 'Failed to load all settings');
                }

            } catch (error) {
                console.error('Error loading all settings:', error);
                document.getElementById('allSettingsList').innerHTML =
                    '<div class="info-box" style="border-left-color: #dc3545;"><p style="color: #dc3545;">Error loading settings</p></div>';
            }
        }

        // Reset all settings to defaults
        async function resetAllSettings() {
            if (!confirm('Are you sure you want to reset all settings to their default values? This will enable all services and reset configuration.')) {
                return;
            }

            try {
                const response = await fetch('/settings/reset-defaults', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert('All settings reset to defaults successfully!');
                    loadServiceControls();
                    loadSystemSettings();
                    loadAllSettings();
                    loadServiceHealth(); // Refresh health status if on health tab
                } else {
                    alert('Failed to reset settings: ' + result.error);
                }

            } catch (error) {
                console.error('Error resetting settings:', error);
                alert('Error resetting settings: ' + error.message);
            }
        }

        // ========== CONFIGURATION FUNCTIONS ==========

        // Load current configuration status
        async function loadConfigurationStatus() {
            try {
                const response = await fetch('/plaid/configuration-status');
                const result = await response.json();

                if (result.success) {
                    let html = `
                        <div class="info-box">
                            <h3>🌍 Environment: ${result.use_production_apis ? 'Production' : 'Sandbox/Test'}</h3>
                            <p style="margin-bottom: 15px;">${result.message}</p>

                            <div style="margin-bottom: 20px;">
                                <h4>🔗 Plaid Configuration</h4>
                                <ul style="margin-left: 20px; margin-top: 10px;">
                                    <li><strong>Environment:</strong> ${result.plaid.environment}</li>
                                    <li><strong>Client ID:</strong> ${result.plaid.client_id}</li>
                                    <li><strong>Base URL:</strong> ${result.plaid.base_url}</li>
                                </ul>
                            </div>

                            <div>
                                <h4>💳 Stripe Configuration</h4>
                                <ul style="margin-left: 20px; margin-top: 10px;">
                                    <li><strong>Environment:</strong> ${result.stripe.environment}</li>
                                    <li><strong>Secret Key:</strong> ${result.stripe.secret_key}</li>
                                </ul>
                            </div>
                        </div>
                    `;

                    document.getElementById('configurationStatus').innerHTML = html;

                    // Update the toggle checkbox
                    document.getElementById('useProductionApisToggle').checked = result.use_production_apis;

                } else {
                    throw new Error(result.error || 'Failed to load configuration status');
                }

            } catch (error) {
                console.error('Error loading configuration status:', error);
                document.getElementById('configurationStatus').innerHTML =
                    '<div class="info-box" style="border-left-color: #dc3545;"><p style="color: #dc3545;">Error loading configuration status</p></div>';
            }
        }

        // Handle production toggle change
        function handleProductionToggle(checkbox) {
            if (checkbox.checked) {
                if (confirm('Switch to PRODUCTION environment? This will use live credentials and real money. Make sure you have set up your production credentials in the .env file.')) {
                    showProductionWarning();
                } else {
                    checkbox.checked = false;
                }
            } else {
                if (confirm('Switch to SANDBOX environment? This will use test credentials and mock data.')) {
                    showSandboxInfo();
                } else {
                    checkbox.checked = true;
                }
            }
        }

        // Show production environment warning
        function showProductionWarning() {
            alert(`
⚠️ PRODUCTION MODE ACTIVATED

To complete the switch to production:
1. Update your .env file with production credentials
2. Set USE_PRODUCTION_APIS=true in .env
3. Restart your application

Current status: Configuration shows intent to use production, but you must manually update .env and restart.

Production credentials needed:
- PLAID_PROD_CLIENT_ID
- PLAID_PROD_SECRET
- STRIPE_PROD_SECRET_KEY
- STRIPE_PROD_PUBLISHABLE_KEY
            `);
        }

        // Show sandbox environment info
        function showSandboxInfo() {
            alert(`
✅ SANDBOX MODE ACTIVATED

To complete the switch to sandbox:
1. Set USE_PRODUCTION_APIS=false in your .env file (or remove it entirely)
2. Restart your application

Current status: Configuration shows intent to use sandbox, but you must manually update .env and restart.

This will use your existing sandbox credentials:
- PLAID_CLIENT_ID (sandbox)
- PLAID_SECRET (sandbox)
- STRIPE_SECRET_KEY (test)
- STRIPE_PUBLISHABLE_KEY (test)
            `);
        }

        // Handle payment method selection interactions
        function handlePaymentMethodSelection() {
            const paymentMethodSelect = document.getElementById('paymentMethod');
            const verificationMethodSelect = document.getElementById('verificationMethod');

            if (!paymentMethodSelect || !verificationMethodSelect) {
                return;
            }

            paymentMethodSelect.addEventListener('change', function() {
                const selectedMethod = this.value;

                if (selectedMethod === 'payment_intents') {
                    // PaymentIntents API - show verification method options
                    verificationMethodSelect.innerHTML = `
                        <option value="instant">⚡ Instant Verification (Recommended)</option>
                        <option value="microdeposit">🏦 Microdeposit Verification (3-5 days)</option>
                    `;
                    verificationMethodSelect.value = 'instant'; // Default to instant

                } else if (selectedMethod === 'charges') {
                    // Charges API - only micro-deposit verification
                    verificationMethodSelect.innerHTML = `
                        <option value="microdeposit">🏦 Microdeposit Verification (Only option for Charges API)</option>
                    `;
                    verificationMethodSelect.value = 'microdeposit';

                } else {
                    // Reset to default options
                    verificationMethodSelect.innerHTML = `
                        <option value="instant">⚡ Instant Verification (Recommended)</option>
                        <option value="microdeposit">🏦 Microdeposit Verification (3-5 days)</option>
                    `;
                }
            });

            // Trigger initial setup
            if (paymentMethodSelect.value) {
                paymentMethodSelect.dispatchEvent(new Event('change'));
            }
        }

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', () => {
            loadUsers();
            loadTransactions();
            loadLinkedAccounts();
            handlePaymentMethodSelection();

            // Link account button handler
            document.getElementById('linkAccountButton').addEventListener('click', async () => {
                const selectedUser = promptUserSelection();
                if (selectedUser) {
                    await initializePlaidLink();
                    if (plaidLinkHandler) {
                        plaidLinkHandler.open();
                    }
                }
            });
        });
    </script>
</body>
</html>
