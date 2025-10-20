<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        $adminId = $currentAdmin['id'];
        
        try {
            if ($action === 'update_exchange_rate') {
                $rate = floatval($_POST['exchange_rate']);
                if ($rate <= 0) {
                    throw new Exception('Exchange rate must be greater than 0');
                }
                
                $value = json_encode(['rate' => $rate, 'last_updated' => date('Y-m-d H:i:s')]);
                dbQuery("UPDATE settings SET value = ?, updated_at = NOW(), updated_by = ? WHERE key = 'exchange_rate_usd_to_etb'", [$value, $adminId]);
                
                // Log action
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, action_description, payload) VALUES (?, 'update_settings', 'settings', 'Updated exchange rate', ?)",
                    [$adminId, json_encode(['key' => 'exchange_rate_usd_to_etb', 'rate' => $rate])]);
                
                $message = 'Exchange rate updated successfully!';
                $messageType = 'success';
                
            } elseif ($action === 'update_deposit_fee') {
                $percentage = floatval($_POST['deposit_fee_percentage']);
                $flat = floatval($_POST['deposit_fee_flat']);
                
                $value = json_encode(['percentage' => $percentage, 'flat' => $flat, 'currency' => 'ETB']);
                dbQuery("UPDATE settings SET value = ?, updated_at = NOW(), updated_by = ? WHERE key = 'deposit_fee'", [$value, $adminId]);
                
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, action_description, payload) VALUES (?, 'update_settings', 'settings', 'Updated deposit fee', ?)",
                    [$adminId, json_encode(['percentage' => $percentage, 'flat' => $flat])]);
                
                $message = 'Deposit fee updated successfully!';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Fetch current settings
$settings = [];
$settingsData = dbFetchAll("SELECT key, value FROM settings");
foreach ($settingsData as $row) {
    $settings[$row['key']] = json_decode($row['value'], true);
}

$exchangeRate = $settings['exchange_rate_usd_to_etb']['rate'] ?? 130.50;
$depositFee = $settings['deposit_fee'] ?? ['percentage' => 0.00, 'flat' => 0.00];
?>

<style>
    .workflow-info {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    .workflow-info h3 {
        margin: 0 0 15px 0;
        font-size: 20px;
    }
    .workflow-steps {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    .workflow-step {
        background: rgba(255, 255, 255, 0.15);
        padding: 15px;
        border-radius: 8px;
        border-left: 3px solid rgba(255, 255, 255, 0.5);
    }
    .workflow-step .number {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    .workflow-step .text {
        font-size: 14px;
        opacity: 0.95;
    }
    .settings-grid {
        display: grid;
        gap: 20px;
        margin-bottom: 30px;
    }
</style>

<div class="page-header">
    <h2>⚙️ System Settings</h2>
    <p class="subtitle">Manage deposit exchange rates and fees</p>
</div>


<?php if ($message): ?>
    <div class="alert <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="settings-grid">
    <div class="card">
        <h3>💱 Exchange Rate (USD to ETB)</h3>
        <p style="color: #94a3b8; margin-bottom: 20px; font-size: 14px;">
            Set the exchange rate for converting customer deposits from ETB to USD wallet balance.
        </p>
        
        <!-- Live Rate Fetcher -->
        <div style="background: rgba(59, 130, 246, 0.1); border: 2px solid #3b82f6; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <div style="margin-bottom: 15px;">
                <button type="button" onclick="fetchLiveRate()" class="btn btn-secondary" style="background: #3b82f6; color: white; width: 100%; padding: 12px 20px; font-size: 15px;">
                    🌐 Fetch Current Market Rate
                </button>
            </div>
            <div style="margin-bottom: 10px;">
                <span id="fetchStatus" style="font-size: 14px; color: #60a5fa; display: block; text-align: center;"></span>
            </div>
            <div id="liveRateDisplay" style="display: none; padding: 15px; background: rgba(255, 255, 255, 0.05); border-radius: 6px; margin-top: 10px; text-align: center;">
                <div style="margin-bottom: 12px;">
                    <strong style="color: #60a5fa;">Live Rate:</strong> 
                    <span id="liveRateValue" style="font-size: 20px; font-weight: 700; color: #10b981; display: block; margin-top: 8px;"></span>
                </div>
                <button type="button" onclick="useLiveRate()" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; width: 100%;">
                    ✓ Use This Rate
                </button>
            </div>
        </div>
        
        <!-- Exchange Rate Calculator - Compact Version -->
        <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 15px;">
            <h4 style="margin: 0 0 10px 0; color: #fbbf24; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">📊 Calculator</h4>
            <div style="display: flex; gap: 8px; align-items: center;">
                <input type="number" id="calc_usd" value="50" step="0.01" min="0" 
                       oninput="calculateETB()"
                       placeholder="USD"
                       style="flex: 1; padding: 6px 8px; border: 1px solid #f59e0b; border-radius: 4px; font-size: 13px; font-weight: 600; background: rgba(30, 41, 59, 0.95); color: #fbbf24; text-align: center;">
                <span style="color: #94a3b8; font-size: 12px; font-weight: 700;">=</span>
                <input type="text" id="calc_etb" readonly 
                       placeholder="ETB"
                       style="flex: 1; padding: 6px 8px; border: 1px solid #10b981; border-radius: 4px; font-size: 13px; font-weight: 600; background: rgba(16, 185, 129, 0.1); color: #6ee7b7; text-align: center;">
            </div>
            <p style="margin: 8px 0 0 0; font-size: 10px; color: #94a3b8; text-align: center;">
                Rate: <strong id="calc_rate" style="color: #fbbf24;"><?php echo number_format($exchangeRate, 2); ?></strong> ETB
            </p>
        </div>
        
        <form method="POST" style="max-width: 500px;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="update_exchange_rate">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="exchange_rate" style="display: block; margin-bottom: 8px; font-weight: 600; color: #f1f5f9;">
                    Exchange Rate (1 USD = ? ETB)
                </label>
                <input type="number" id="exchange_rate" name="exchange_rate" 
                       placeholder="Enter rate (e.g., 130.50)" 
                       step="0.01" min="0.01" required
                       oninput="updateCalculatorRate()"
                       class="form-control">
                <small style="color: #94a3b8; display: block; margin-top: 8px;">
                    💡 <strong style="color: #cbd5e1;">Saved Rate:</strong> 1 USD = <?php echo number_format($exchangeRate, 2); ?> ETB
                    <span style="color: #3b82f6; cursor: pointer; margin-left: 10px;" onclick="document.getElementById('exchange_rate').value = <?php echo $exchangeRate; ?>; updateCalculatorRate();">
                        [Click to use this rate]
                    </span>
                </small>
            </div>
            
            <button type="submit" class="btn btn-primary">💾 Update Exchange Rate</button>
        </form>
    </div>
    
    <script>
        // Calculate ETB amount in real-time
        function calculateETB() {
            const usd = parseFloat(document.getElementById('calc_usd').value) || 0;
            const rate = parseFloat(document.getElementById('exchange_rate').value) || 0;
            const etb = usd * rate;
            document.getElementById('calc_etb').value = etb.toFixed(2) + ' ETB';
        }
        
        // Update calculator when exchange rate changes
        function updateCalculatorRate() {
            const rate = parseFloat(document.getElementById('exchange_rate').value) || 0;
            document.getElementById('calc_rate').textContent = rate.toFixed(2);
            calculateETB();
        }
        
        // Fetch live exchange rate from API
        async function fetchLiveRate() {
            const statusEl = document.getElementById('fetchStatus');
            const displayEl = document.getElementById('liveRateDisplay');
            const valueEl = document.getElementById('liveRateValue');
            
            statusEl.textContent = '⏳ Fetching current rate...';
            statusEl.style.color = '#1e40af';
            displayEl.style.display = 'none';
            
            try {
                // Using exchangerate-api.com free tier (no API key needed)
                const response = await fetch('https://api.exchangerate-api.com/v4/latest/USD');
                const data = await response.json();
                
                if (data.rates && data.rates.ETB) {
                    const rate = data.rates.ETB;
                    valueEl.textContent = '1 USD = ' + rate.toFixed(2) + ' ETB';
                    displayEl.style.display = 'block';
                    statusEl.textContent = '✅ Rate fetched successfully!';
                    statusEl.style.color = '#10b981';
                    
                    // Store the fetched rate
                    window.fetchedRate = rate;
                } else {
                    throw new Error('ETB rate not found');
                }
            } catch (error) {
                statusEl.textContent = '❌ Failed to fetch rate. Please enter manually.';
                statusEl.style.color = '#ef4444';
                console.error('Error fetching exchange rate:', error);
            }
        }
        
        // Use the fetched live rate
        function useLiveRate() {
            if (window.fetchedRate) {
                document.getElementById('exchange_rate').value = window.fetchedRate.toFixed(2);
                updateCalculatorRate();
                document.getElementById('fetchStatus').textContent = '✓ Live rate applied! Click "Update Exchange Rate" to save.';
                document.getElementById('fetchStatus').style.color = '#10b981';
            }
        }
        
        // Initialize calculator on page load
        calculateETB();
    </script>

    <div class="card">
        <h3>💸 Deposit Fee (Optional)</h3>
        <p style="color: #94a3b8; margin-bottom: 20px; font-size: 14px;">
            Set fees charged to customers when they deposit ETB to their USD wallet.
        </p>
        <form method="POST" style="max-width: 500px;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="update_deposit_fee">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="deposit_fee_percentage" style="display: block; margin-bottom: 8px; font-weight: 600; color: #f1f5f9;">
                    Percentage Fee (%)
                </label>
                <input type="number" id="deposit_fee_percentage" name="deposit_fee_percentage" 
                       value="<?php echo $depositFee['percentage']; ?>" 
                       step="0.01" min="0" required
                       class="form-control">
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="deposit_fee_flat" style="display: block; margin-bottom: 8px; font-weight: 600; color: #f1f5f9;">
                    Flat Fee (ETB)
                </label>
                <input type="number" id="deposit_fee_flat" name="deposit_fee_flat" 
                       value="<?php echo $depositFee['flat']; ?>" 
                       step="0.01" min="0" required
                       class="form-control">
                <small style="color: #94a3b8; display: block; margin-top: 8px;">
                    <strong style="color: #cbd5e1;">Current:</strong> <?php echo $depositFee['percentage']; ?>% + <?php echo number_format($depositFee['flat'], 2); ?> ETB
                </small>
            </div>
            
            <button type="submit" class="btn btn-primary">💾 Update Deposit Fee</button>
        </form>
    </div>
</div>

<div class="card" style="background: rgba(59, 130, 246, 0.05); border: 2px solid rgba(59, 130, 246, 0.3);">
    <h3 style="color: #60a5fa;">ℹ️ Important Information</h3>
    <div style="color: #cbd5e1; line-height: 1.6;">
        <p style="margin-bottom: 12px;">
            <strong style="color: #f1f5f9;">Card Management:</strong> Card creation, card limits, and card fees are all managed by StroWallet API automatically through the Telegram bot. You don't need to configure these settings.
        </p>
        <p style="margin-bottom: 12px;">
            <strong style="color: #f1f5f9;">Your Responsibilities:</strong>
        </p>
        <ul style="margin-left: 20px; margin-bottom: 12px;">
            <li>View customer KYC status (verified by StroWallet)</li>
            <li>Review and approve customer deposit requests</li>
            <li>Manage exchange rates for deposit calculations</li>
            <li>Set deposit fees (if applicable)</li>
        </ul>
        <p>
            <strong style="color: #f1f5f9;">After Deposit Approval:</strong> Once you add money to a customer's wallet, they can use the Telegram bot to create and manage cards via StroWallet API.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
