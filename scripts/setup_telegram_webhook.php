<?php
/**
 * Script to set up Telegram webhook
 * Run this script to configure your bot to receive messages
 */

require_once __DIR__ . '/../secrets/load_env.php';
loadEnvFile();

// Load configuration constants
define('BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: getenv('BOT_TOKEN') ?: '');
define('TELEGRAM_SECRET_TOKEN', getenv('TELEGRAM_SECRET_TOKEN') ?: '');

// Get the Replit domain
$domain = getenv('REPLIT_DEV_DOMAIN');
if (empty($domain)) {
    die("❌ Error: REPLIT_DEV_DOMAIN not found in environment\n");
}

// Build webhook URL
$webhookUrl = "https://{$domain}/bot/webhook.php";

echo "🔧 Setting up Telegram webhook...\n\n";
echo "📍 Webhook URL: {$webhookUrl}\n\n";

// Set webhook
$url = "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook";
$params = [
    'url' => $webhookUrl,
    'drop_pending_updates' => true,
    'allowed_updates' => ['message', 'callback_query']
];

// Add secret token if configured
if (!empty(TELEGRAM_SECRET_TOKEN)) {
    $params['secret_token'] = TELEGRAM_SECRET_TOKEN;
    echo "🔐 Using secret token for webhook security\n\n";
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Error setting webhook (HTTP {$httpCode})\n";
    echo "Response: {$response}\n";
    exit(1);
}

$result = json_decode($response, true);

if (isset($result['ok']) && $result['ok']) {
    echo "✅ Webhook set successfully!\n\n";
    echo "📋 Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    
    // Get webhook info to verify
    echo "🔍 Verifying webhook...\n\n";
    
    $infoUrl = "https://api.telegram.org/bot" . BOT_TOKEN . "/getWebhookInfo";
    $ch = curl_init($infoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $infoResponse = curl_exec($ch);
    curl_close($ch);
    
    $info = json_decode($infoResponse, true);
    if (isset($info['result'])) {
        echo "📊 Webhook Info:\n";
        echo "   URL: " . ($info['result']['url'] ?? 'Not set') . "\n";
        echo "   Pending updates: " . ($info['result']['pending_update_count'] ?? 0) . "\n";
        echo "   Last error: " . ($info['result']['last_error_message'] ?? 'None') . "\n";
        echo "\n";
    }
    
    echo "🎉 Your bot is now ready to receive messages!\n";
    echo "💬 Try sending /start to your bot on Telegram\n";
} else {
    echo "❌ Failed to set webhook\n";
    echo "Response: {$response}\n";
    exit(1);
}
