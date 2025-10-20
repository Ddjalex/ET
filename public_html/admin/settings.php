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
                
            } elseif ($action === 'update_card_creation_fee') {
                $percentage = floatval($_POST['creation_fee_percentage']);
                $flat = floatval($_POST['creation_fee_flat']);
                
                $value = json_encode(['percentage' => $percentage, 'flat' => $flat, 'currency' => 'USD']);
                dbQuery("UPDATE settings SET value = ?, updated_at = NOW(), updated_by = ? WHERE key = 'card_creation_fee'", [$value, $adminId]);
                
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, action_description, payload) VALUES (?, 'update_settings', 'settings', 'Updated card creation fee', ?)",
                    [$adminId, json_encode(['percentage' => $percentage, 'flat' => $flat])]);
                
                $message = 'Card creation fee updated successfully!';
                $messageType = 'success';
                
            } elseif ($action === 'update_card_topup_fee') {
                $percentage = floatval($_POST['topup_fee_percentage']);
                $flat = floatval($_POST['topup_fee_flat']);
                
                $value = json_encode(['percentage' => $percentage, 'flat' => $flat, 'currency' => 'USD']);
                dbQuery("UPDATE settings SET value = ?, updated_at = NOW(), updated_by = ? WHERE key = 'card_topup_fee'", [$value, $adminId]);
                
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, action_description, payload) VALUES (?, 'update_settings', 'settings', 'Updated card top-up fee', ?)",
                    [$adminId, json_encode(['percentage' => $percentage, 'flat' => $flat])]);
                
                $message = 'Card top-up fee updated successfully!';
                $messageType = 'success';
                
            } elseif ($action === 'update_limits') {
                $minTopup = floatval($_POST['min_topup']);
                $maxTopup = floatval($_POST['max_topup']);
                $dailyLimit = floatval($_POST['daily_limit']);
                
                $value = json_encode(['min_topup' => $minTopup, 'max_topup' => $maxTopup, 'daily_limit' => $dailyLimit]);
                dbQuery("UPDATE settings SET value = ?, updated_at = NOW(), updated_by = ? WHERE key = 'card_limits'", [$value, $adminId]);
                
                dbQuery("INSERT INTO admin_actions (admin_id, action_type, target_table, action_description, payload) VALUES (?, 'update_settings', 'settings', 'Updated card limits', ?)",
                    [$adminId, json_encode(['min_topup' => $minTopup, 'max_topup' => $maxTopup, 'daily_limit' => $dailyLimit])]);
                
                $message = 'Card limits updated successfully!';
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
$cardCreationFee = $settings['card_creation_fee'] ?? ['percentage' => 1.99, 'flat' => 1.99];
$cardTopupFee = $settings['card_topup_fee'] ?? ['percentage' => 1.99, 'flat' => 1.99];
$cardLimits = $settings['card_limits'] ?? ['min_topup' => 5, 'max_topup' => 10000, 'daily_limit' => 1000];
?>

<div class="page-header">
    <h2>⚙️ System Settings</h2>
    <p class="subtitle">Manage exchange rates, fees, and system configuration</p>
</div>

<?php if ($message): ?>
    <div class="alert <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3>💱 Exchange Rate (USD to ETB)</h3>
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
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <small style="color: #666; display: block; margin-top: 5px;">
                Current: 1 USD = <?php echo number_format($exchangeRate, 2); ?> ETB
            </small>
        </div>
        
        <button type="submit" class="btn btn-primary">Update Exchange Rate</button>
    </form>
</div>

<div class="card">
    <h3>💳 Card Creation Fee</h3>
    <form method="POST" style="max-width: 500px;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update_card_creation_fee">
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="creation_fee_percentage" style="display: block; margin-bottom: 8px; font-weight: 600;">
                Percentage Fee (%)
            </label>
            <input type="number" id="creation_fee_percentage" name="creation_fee_percentage" 
                   value="<?php echo $cardCreationFee['percentage']; ?>" 
                   step="0.01" min="0" required
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="creation_fee_flat" style="display: block; margin-bottom: 8px; font-weight: 600;">
                Flat Fee (USD)
            </label>
            <input type="number" id="creation_fee_flat" name="creation_fee_flat" 
                   value="<?php echo $cardCreationFee['flat']; ?>" 
                   step="0.01" min="0" required
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <small style="color: #666; display: block; margin-top: 5px;">
                Current: <?php echo $cardCreationFee['percentage']; ?>% + $<?php echo number_format($cardCreationFee['flat'], 2); ?>
            </small>
        </div>
        
        <button type="submit" class="btn btn-primary">Update Card Creation Fee</button>
    </form>
</div>

<div class="card">
    <h3>💰 Card Top-up Fee</h3>
    <form method="POST" style="max-width: 500px;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update_card_topup_fee">
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="topup_fee_percentage" style="display: block; margin-bottom: 8px; font-weight: 600;">
                Percentage Fee (%)
            </label>
            <input type="number" id="topup_fee_percentage" name="topup_fee_percentage" 
                   value="<?php echo $cardTopupFee['percentage']; ?>" 
                   step="0.01" min="0" required
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="topup_fee_flat" style="display: block; margin-bottom: 8px; font-weight: 600;">
                Flat Fee (USD)
            </label>
            <input type="number" id="topup_fee_flat" name="topup_fee_flat" 
                   value="<?php echo $cardTopupFee['flat']; ?>" 
                   step="0.01" min="0" required
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <small style="color: #666; display: block; margin-top: 5px;">
                Current: <?php echo $cardTopupFee['percentage']; ?>% + $<?php echo number_format($cardTopupFee['flat'], 2); ?>
            </small>
        </div>
        
        <button type="submit" class="btn btn-primary">Update Card Top-up Fee</button>
    </form>
</div>

<div class="card">
    <h3>⚡ Card Limits</h3>
    <form method="POST" style="max-width: 500px;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="update_limits">
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="min_topup" style="display: block; margin-bottom: 8px; font-weight: 600;">
                Minimum Top-up (USD)
            </label>
            <input type="number" id="min_topup" name="min_topup" 
                   value="<?php echo $cardLimits['min_topup']; ?>" 
                   step="0.01" min="0" required
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="max_topup" style="display: block; margin-bottom: 8px; font-weight: 600;">
                Maximum Top-up (USD)
            </label>
            <input type="number" id="max_topup" name="max_topup" 
                   value="<?php echo $cardLimits['max_topup']; ?>" 
                   step="0.01" min="0" required
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label for="daily_limit" style="display: block; margin-bottom: 8px; font-weight: 600;">
                Daily Spending Limit (USD)
            </label>
            <input type="number" id="daily_limit" name="daily_limit" 
                   value="<?php echo $cardLimits['daily_limit']; ?>" 
                   step="0.01" min="0" required
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <button type="submit" class="btn btn-primary">Update Card Limits</button>
    </form>
</div>

<style>
    .form-group {
        margin-bottom: 20px;
    }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
