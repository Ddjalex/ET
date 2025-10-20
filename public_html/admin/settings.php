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
        <p style="color: #666; margin-bottom: 20px; font-size: 14px;">
            Set the exchange rate for converting customer deposits from ETB to USD wallet balance.
        </p>
        <form method="POST" style="max-width: 500px;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="update_exchange_rate">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="exchange_rate" style="display: block; margin-bottom: 8px; font-weight: 600;">
                    Exchange Rate (1 USD = ? ETB)
                </label>
                <input type="number" id="exchange_rate" name="exchange_rate" 
                       value="<?php echo $exchangeRate; ?>" 
                       step="0.01" min="0.01" required
                       style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px;">
                <small style="color: #666; display: block; margin-top: 8px;">
                    <strong>Current:</strong> 1 USD = <?php echo number_format($exchangeRate, 2); ?> ETB
                </small>
            </div>
            
            <button type="submit" class="btn btn-primary">💾 Update Exchange Rate</button>
        </form>
    </div>

    <div class="card">
        <h3>💸 Deposit Fee (Optional)</h3>
        <p style="color: #666; margin-bottom: 20px; font-size: 14px;">
            Set fees charged to customers when they deposit ETB to their USD wallet.
        </p>
        <form method="POST" style="max-width: 500px;">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="update_deposit_fee">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="deposit_fee_percentage" style="display: block; margin-bottom: 8px; font-weight: 600;">
                    Percentage Fee (%)
                </label>
                <input type="number" id="deposit_fee_percentage" name="deposit_fee_percentage" 
                       value="<?php echo $depositFee['percentage']; ?>" 
                       step="0.01" min="0" required
                       style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px;">
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="deposit_fee_flat" style="display: block; margin-bottom: 8px; font-weight: 600;">
                    Flat Fee (ETB)
                </label>
                <input type="number" id="deposit_fee_flat" name="deposit_fee_flat" 
                       value="<?php echo $depositFee['flat']; ?>" 
                       step="0.01" min="0" required
                       style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px;">
                <small style="color: #666; display: block; margin-top: 8px;">
                    <strong>Current:</strong> <?php echo $depositFee['percentage']; ?>% + <?php echo number_format($depositFee['flat'], 2); ?> ETB
                </small>
            </div>
            
            <button type="submit" class="btn btn-primary">💾 Update Deposit Fee</button>
        </form>
    </div>
</div>

<div class="card" style="background: #f7fafc; border: 2px solid #e2e8f0;">
    <h3 style="color: #2d3748;">ℹ️ Important Information</h3>
    <div style="color: #4a5568; line-height: 1.6;">
        <p style="margin-bottom: 12px;">
            <strong>Card Management:</strong> Card creation, card limits, and card fees are all managed by StroWallet API automatically through the Telegram bot. You don't need to configure these settings.
        </p>
        <p style="margin-bottom: 12px;">
            <strong>Your Responsibilities:</strong>
        </p>
        <ul style="margin-left: 20px; margin-bottom: 12px;">
            <li>View customer KYC status (verified by StroWallet)</li>
            <li>Review and approve customer deposit requests</li>
            <li>Manage exchange rates for deposit calculations</li>
            <li>Set deposit fees (if applicable)</li>
        </ul>
        <p>
            <strong>After Deposit Approval:</strong> Once you add money to a customer's wallet, they can use the Telegram bot to create and manage cards via StroWallet API.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
