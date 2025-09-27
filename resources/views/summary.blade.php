<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plaid & Stripe Secure Flow</title>
    <!-- Inter Font (Google's clean sans-serif, similar to Apple's San Francisco) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        xintegrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Custom Liquid Glass Styling -->
    <style>
        body {
            /* Soft, light gradient background to make the glass effect pop */
            background: linear-gradient(135deg, #f0f0f5 0%, #e0e0eb 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            color: #333;
        }

        /* Glass Card Effect - Increased Transparency */
        .flow-card {
            background-color: rgba(255, 255, 255, 0.2);
            /* Reduced alpha from 0.5 to 0.2 */
            backdrop-filter: blur(15px);
            /* The key "liquid glass" effect */
            -webkit-backdrop-filter: blur(15px);
            /* For Safari support */
            border: 1px solid rgba(255, 255, 255, 0.3);
            /* Subtle light border */
            border-radius: 20px;
            /* High radius for softness */
            box-shadow: 0 6px 20px 0 rgba(0, 0, 0, 0.08);
            /* Soft, diffused shadow */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .flow-card:hover {
            transform: translateY(-5px);
            /* Slight lift on hover */
            box-shadow: 0 10px 30px 0 rgba(0, 0, 0, 0.12);
        }

        /* Header and Alert Style - Increased Transparency */
        .actor-header,
        .alert-info-glass {
            background-color: rgba(255, 255, 255, 0.4);
            /* Reduced alpha from 0.9 to 0.4 */
            backdrop-filter: blur(8px);
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        /* Step Number Styling (Soft Blue) */
        .flow-step-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #4A90E2;
            /* Apple-like vibrant blue */
            margin-right: 15px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .card-title {
            /* color: #4A90E2; */
        }

        /* General Typography */
        h1,
        h2,
        h5 {
            font-weight: 600;
        }

        .display-4 {
            font-weight: 700;
        }

        .lead {
            color: #6c757d;
        }

        /* Custom Badge Colors for Clean Look */
        .badge-plaid {
            background-color: rgba(0, 255, 175, 0.9);
            color: #004d33;
            border-radius: 6px;
        }

        .badge-stripe {
            background-color: rgba(100, 100, 255, 0.7);
            color: white;
            border-radius: 6px;
        }

        .badge-user {
            background-color: rgba(255, 200, 0, 0.8);
            color: #333;
            border-radius: 6px;
        }

        .badge-backend {
            background-color: rgba(100, 100, 100, 0.5);
            color: #fff;
            border-radius: 6px;
        }

        /* Table Styling */
        .table-glass th,
        .table-glass td {
            background-color: transparent !important;
            border-color: rgba(0, 0, 0, 0.1);
        }

        .table-glass thead th {
            font-weight: 700;
            color: #4A90E2;
        }

        /* Shimmer Effect */
        .shimmer {
            position: relative;
            overflow: hidden;
        }

        .shimmer::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg,
                    transparent,
                    rgba(255, 255, 255, 0.5),
                    transparent);
            animation: shimmer 2s infinite;
            border-radius: inherit;
            z-index: 1;
        }

        .shimmer>* {
            position: relative;
            z-index: 2;
        }

        @keyframes shimmer {
            0% {
                left: -100%;
            }

            100% {
                left: 100%;
            }
        }

        /* Enhanced shimmer for glass cards */
        .flow-card.shimmer::before {
            background: linear-gradient(90deg,
                    transparent,
                    rgba(255, 255, 255, 0.5),
                    transparent);
            animation: shimmer 2.5s infinite;
        }

        /* Pause shimmer on hover for better UX */
        .shimmer:hover::before {
            animation-play-state: paused;
        }

        /* Border Shimmer Effect - Only on Hover */
        .shimmer-border-pulse {
            border: 2px solid rgba(255, 255, 255, 0.4) !important;
            transition: all 0.3s ease;
        }

        .shimmer-border-pulse:hover {
            animation: borderPulse 2s ease-in-out infinite;
        }

        @keyframes borderPulse {

            0%,
            100% {
                border-color: rgba(255, 255, 255, 0.4);
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.5);
            }

            50% {
                border-color: rgba(255, 255, 255, 0.9);
                box-shadow: 0 0 20px 5px rgba(255, 255, 255, 0.3);
            }
        }

        /* Alternative hover-only border shimmers */
        .shimmer-border {
            position: relative;
            border: 2px solid rgba(255, 255, 255, 0.3) !important;
            background: linear-gradient(white, white) padding-box;
            transition: all 0.3s ease;
        }

        .shimmer-border:hover {
            border: 2px solid transparent !important;
            background: linear-gradient(white, white) padding-box,
                linear-gradient(90deg,
                    rgba(255, 255, 255, 0.3),
                    rgba(255, 255, 255, 0.8),
                    rgba(255, 255, 255, 1.0),
                    rgba(255, 255, 255, 0.8),
                    rgba(255, 255, 255, 0.3)) border-box;
            background-size: 300% 100%;
            animation: borderShimmer 3s linear infinite;
        }

        @keyframes borderShimmer {
            0% {
                background-position: 300% 0;
            }

            100% {
                background-position: -300% 0;
            }
        }

        .shimmer-border-rainbow {
            position: relative;
            border: 2px solid rgba(255, 255, 255, 0.3) !important;
            transition: all 0.3s ease;
        }

        .shimmer-border-rainbow:hover {
            border: 2px solid transparent !important;
            background: linear-gradient(white, white) padding-box,
                conic-gradient(from 0deg,
                    rgba(255, 255, 255, 0.3),
                    rgba(255, 255, 255, 0.6),
                    rgba(255, 255, 255, 0.9),
                    rgba(255, 255, 255, 1.0),
                    rgba(255, 255, 255, 0.8),
                    rgba(255, 255, 255, 0.4)) border-box;
            animation: rotateBorder 4s linear infinite;
        }

        @keyframes rotateBorder {
            0% {
                filter: hue-rotate(0deg);
            }

            100% {
                filter: hue-rotate(360deg);
            }
        }
    </style>
</head>

<body>

    <div class="container py-5">
        <header class="text-center mb-5">
            <h1 class="display-4 text-dark">Secure Payout Onboarding</h1>
            <p class="lead text-muted">Plaid & Stripe Processor Token Flow</p>
        </header>

        <!-- ACTORS SECTION -->
        <div class="row mb-5">
            <div class="col-12">
                <h2 class="h3 mb-4 text-secondary">1. The Actors</h2>
                <div class="table-responsive actor-header card flow-card shimmer shimmer-border-pulse">
                    <table class="table table-borderless table-glass align-middle">
                        <thead>
                            <tr>
                                <th>Actor</th>
                                <th>Role</th>
                                <th>Key Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-bold"><span class="badge badge-user">User/Seller</span></td>
                                <td>End-user linking their bank account.</td>
                                <td>Authentication, Inputting Credentials.</td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Your App Frontend</td>
                                <td>Client-side layer (Plaid Link Modal).</td>
                                <td>Loads Plaid Link, Sends temporary token.</td>
                            </tr>
                            <tr>
                                <td class="fw-bold"><span class="badge badge-backend">Your App Backend (Server)</span>
                                </td>
                                <td>Secure server (Laravel). **Handles all API Keys.**</td>
                                <td>Exchanges tokens, Attaches account to Stripe.</td>
                            </tr>
                            <tr>
                                <td class="fw-bold"><span class="badge badge-plaid">Plaid API</span></td>
                                <td>Bank authentication and data verification.</td>
                                <td>Issues `link_token`, Creates Stripe `processor_token`.</td>
                            </tr>
                            <tr>
                                <td class="fw-bold"><span class="badge badge-stripe">Stripe API</span></td>
                                <td>Payment processing and external account management.</td>
                                <td>Attaches the bank account to the Connect Account.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- FLOW STEPS SECTION -->
        <h2 class="h3 mb-4 text-secondary">2. The Secure Flow Steps</h2>

        <div class="row g-4">
            <!-- Step 1: Initiate Connection & Load Modal -->
            <div class="col-md-6 col-lg-4">
                <div class="card flow-card shimmer shimmer-border-pulse h-100 p-2">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="flow-step-number">1.</span>
                            <h5 class="card-title mb-0">Request Link Token</h5>
                        </div>
                        <p class="card-text text-muted">Backend requests a temporary <code>link_token</code> from Plaid.
                            Frontend uses this token to display the secure Plaid Link modal.</p>
                        <span class="badge badge-plaid shadow-sm">Backend ⇌ Plaid ⇌ Frontend</span>
                    </div>
                </div>
            </div>

            <!-- Step 2: User Authenticates -->
            <div class="col-md-6 col-lg-4">
                <div class="card flow-card shimmer shimmer-border-pulse h-100 p-2">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="flow-step-number">2.</span>
                            <h5 class="card-title mb-0">User Links Bank</h5>
                        </div>
                        <p class="card-text text-muted">User securely connects their bank via Plaid's UI. Plaid returns
                            a one-time use <code>access_token</code>, <code>plaid_item_id</code> and the
                            <code>account_id</code>.
                        </p>
                        <span class="badge badge-plaid shadow-sm">User ↔ Plaid</span>
                    </div>
                </div>
            </div>

            <!-- Step 3: Token Exchange Request -->
            <div class="col-md-6 col-lg-4">
                <div class="card flow-card shimmer shimmer-border-pulse h-100 p-2">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="flow-step-number">3.</span>
                            <h5 class="card-title mb-0">Relay to Backend</h5>
                        </div>
                        <p class="card-text text-muted">Frontend passes the temporary <code>access_token</code>,
                            <code>plaid_item_id</code> and <code>account_id</code> to your secure Backend API endpoint.
                        </p>
                        <span class="badge badge-plaid shadow-sm">Frontend → Backend</span>
                    </div>
                </div>
            </div>

            <!-- Step 4: Get Routing No and Account No Start -->
            <div class="col-md-6 col-lg-4">
                <div class="card flow-card shimmer shimmer-border-pulse h-100 p-2">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="flow-step-number">4.</span>
                            <h5 class="card-title mb-0">Get Routing No and Account No</h5>
                        </div>
                        <p class="card-text text-muted">We use the <code>access_token</code> and
                            <code>plaid_item_id</code> to get the <code>routing_no</code> and <code>account_no</code>
                            from Plaid.
                        </p>
                        <span class="badge badge-plaid shadow-sm">Backend ↔ Plaid API</span>
                    </div>
                </div>
            </div>

            <!-- Step 5: Get  -->
            <div class="col-md-6 col-lg-4">
                <div class="card flow-card shimmer shimmer-border-pulse h-100 p-2">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="flow-step-number">5.</span>
                            <h5 class="card-title mb-0">Initiate Transaction</h5>
                        </div>
                        <p class="card-text text-muted">With the <code>routing_no</code> and <code>account_no</code>, we
                            can send a pay intent to Stripe with the <code>amount</code> and <code>description</code>
                        </p>
                        <span class="badge badge-plaid shadow-sm">Backend → Stripe API</span>
                    </div>
                </div>
            </div>

            <!-- Step 6: Stripe Responds -->
            <div class="col-md-6 col-lg-4">
                <div class="card flow-card shimmer shimmer-border-pulse h-100 p-2">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="flow-step-number">6.</span>
                            <h5 class="card-title mb-0">Stripe Responds</h5>
                        </div>
                        <p class="card-text text-muted">
                            Stripe confirms success and sends back a <code>payment_intent</code>. Backend saves it
                            and updates the user's Payout Status in the local database.
                        </p>
                        <span class="badge badge-plaid shadow-sm">Stripe Api → Backend</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info-glass shimmer shimmer-border-pulse mt-5 text-center flow-card">
            <h6 class="fw-bold mb-2" style="color: #4A90E2;">Key Security Principle</h6>
            <p class="mb-0 text-muted">The core security feature is the <code>access_token</code> (Steps 3 & 4): our
                application server *never* handles or stores sensitive bank account/routing numbers, maintaining the
                highest level of security.</p>
        </div>

    </div>

    <!-- Bootstrap 5 JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
</body>

</html>
