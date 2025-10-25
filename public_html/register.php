<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Crypto Card Bot</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 32px;
            text-align: center;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-top: 20px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .helper-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-info {
            background: #e3f2fd;
            color: #1976d2;
            border-left: 4px solid #1976d2;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .container {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📝 User Registration</h1>
        <p class="subtitle">Create your account to access virtual crypto cards</p>
        
        <div class="alert alert-info">
            ℹ️ Please use the <strong>Telegram Bot</strong> for registration. This ensures secure KYC verification and real-time updates. Search for <strong>@ETH_Card_BOT</strong> on Telegram and send <code>/register</code> to begin.
        </div>

        <form id="registrationForm" action="/bot/webhook.php" method="POST">
            <div class="form-section">
                <h3 class="section-title">👤 Personal Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="dob">Date of Birth *</label>
                    <input type="text" id="dob" name="date_of_birth" placeholder="MM/DD/YYYY" required>
                    <div class="helper-text">Format: MM/DD/YYYY (e.g., 01/15/1990)</div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" placeholder="2348012345678" required>
                        <div class="helper-text">International format without '+'</div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">🏠 Address Information</h3>
                <div class="form-group">
                    <label for="house_number">House Number *</label>
                    <input type="text" id="house_number" name="house_number" required>
                </div>
                <div class="form-group">
                    <label for="address_line1">Street Address *</label>
                    <input type="text" id="address_line1" name="address_line1" required>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="address_city">City *</label>
                        <input type="text" id="address_city" name="address_city" required>
                    </div>
                    <div class="form-group">
                        <label for="address_state">State/Province *</label>
                        <input type="text" id="address_state" name="address_state" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="address_zip">ZIP/Postal Code *</label>
                        <input type="text" id="address_zip" name="address_zip" required>
                    </div>
                    <div class="form-group">
                        <label for="address_country">Country *</label>
                        <select id="address_country" name="address_country" required>
                            <option value="">Select Country</option>
                            <option value="ET">Ethiopia (ET)</option>
                            <option value="NG">Nigeria (NG)</option>
                            <option value="US">United States (US)</option>
                            <option value="GB">United Kingdom (GB)</option>
                            <option value="CA">Canada (CA)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="section-title">🆔 Identification</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="id_type">ID Type *</label>
                        <select id="id_type" name="id_type" required>
                            <option value="">Select ID Type</option>
                            <option value="GOVERNMENT_ID">Government ID</option>
                            <option value="NATIONAL_ID">National ID</option>
                            <option value="PASSPORT">Passport</option>
                            <option value="DRIVERS_LICENSE">Driver's License</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="id_number">ID Number *</label>
                        <input type="text" id="id_number" name="id_number" required>
                    </div>
                </div>
            </div>

            <div class="alert alert-info">
                ⚠️ <strong>Note:</strong> For ID photo and selfie verification, please use the Telegram bot at <strong>@ETH_Card_BOT</strong>. Web-based photo uploads are not supported for security reasons.
            </div>

            <button type="submit" class="btn" disabled>
                🚀 Register via Telegram Bot
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px; color: #666;">
            <p>Already have an account? <a href="/admin/" style="color: #667eea; text-decoration: none; font-weight: 600;">Admin Login</a></p>
        </div>
    </div>

    <script>
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Please use the Telegram bot (@ETH_Card_BOT) to complete registration. Send /register to the bot to begin.');
        });
    </script>
</body>
</html>