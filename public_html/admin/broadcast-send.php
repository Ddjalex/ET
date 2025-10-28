<?php
$pageTitle = 'Send Broadcast';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/../../secrets/load_env.php';

if (!isset($_GET['id'])) {
    header("Location: /admin/broadcaster.php");
    exit;
}

$broadcast = dbFetchOne("SELECT * FROM broadcasts WHERE id = ?", [(int)$_GET['id']]);

if (!$broadcast || $broadcast['status'] === 'sent') {
    header("Location: /admin/broadcaster.php");
    exit;
}

$telegram_bot_token = getenv('TELEGRAM_BOT_TOKEN');

function sendTelegramMessage($chatId, $broadcast, $botToken) {
    $keyboard = null;
    
    if (!empty($broadcast['buttons'])) {
        $buttons = json_decode($broadcast['buttons'], true);
        $inlineKeyboard = [];
        foreach ($buttons as $button) {
            if ($button['type'] === 'url') {
                $inlineKeyboard[] = [['text' => $button['text'], 'url' => $button['data']]];
            } elseif ($button['type'] === 'giveaway') {
                $callbackData = 'giveaway_' . $broadcast['id'] . '_' . base64_encode($button['data']);
                $inlineKeyboard[] = [['text' => $button['text'], 'callback_data' => $callbackData]];
            }
        }
        if (!empty($inlineKeyboard)) {
            $keyboard = json_encode(['inline_keyboard' => $inlineKeyboard]);
        }
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/";
    $result = null;
    
    switch ($broadcast['content_type']) {
        case 'photo':
            $result = sendTelegramRequest($url . 'sendPhoto', [
                'chat_id' => $chatId,
                'photo' => $broadcast['media_url'],
                'caption' => $broadcast['media_caption'],
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard
            ]);
            break;
            
        case 'video':
            $result = sendTelegramRequest($url . 'sendVideo', [
                'chat_id' => $chatId,
                'video' => $broadcast['media_url'],
                'caption' => $broadcast['media_caption'],
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard
            ]);
            break;
            
        case 'poll':
            $pollOptions = json_decode($broadcast['poll_options'], true);
            $result = sendTelegramRequest($url . 'sendPoll', [
                'chat_id' => $chatId,
                'question' => $broadcast['poll_question'],
                'options' => json_encode($pollOptions),
                'is_anonymous' => false,
                'type' => $broadcast['poll_type'] ?? 'regular',
                'correct_option_id' => $broadcast['poll_correct_option']
            ]);
            break;
            
        default:
            $result = sendTelegramRequest($url . 'sendMessage', [
                'chat_id' => $chatId,
                'text' => $broadcast['content_text'],
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard
            ]);
    }
    
    return $result;
}

function sendTelegramRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'success' => $httpCode === 200,
        'http_code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

function pinTelegramMessage($chatId, $messageId, $botToken) {
    $url = "https://api.telegram.org/bot{$botToken}/pinChatMessage";
    return sendTelegramRequest($url, [
        'chat_id' => $chatId,
        'message_id' => $messageId
    ]);
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_send'])) {
    $success = false;
    $errorMsg = '';
    $telegramMessageId = null;
    
    if ($broadcast['send_to_telegram'] && !empty($broadcast['telegram_channel_id'])) {
        if (empty($telegram_bot_token)) {
            $errorMsg = 'TELEGRAM_BOT_TOKEN not configured';
        } else {
            $result = sendTelegramMessage($broadcast['telegram_channel_id'], $broadcast, $telegram_bot_token);
            
            dbQuery("INSERT INTO broadcast_logs (broadcast_id, event_type, status, response_data) VALUES (?, ?, ?, ?)", [
                $broadcast['id'],
                'telegram_send',
                $result['success'] ? 'success' : 'failed',
                json_encode($result['response'])
            ]);
            
            if ($result['success']) {
                $success = true;
                $telegramMessageId = $result['response']['result']['message_id'] ?? null;
                
                if ($broadcast['pin_message'] && $telegramMessageId) {
                    $pinResult = pinTelegramMessage($broadcast['telegram_channel_id'], $telegramMessageId, $telegram_bot_token);
                    
                    dbQuery("INSERT INTO broadcast_logs (broadcast_id, event_type, status, telegram_message_id, response_data) VALUES (?, ?, ?, ?, ?)", [
                        $broadcast['id'],
                        'pin_message',
                        $pinResult['success'] ? 'success' : 'failed',
                        $telegramMessageId,
                        json_encode($pinResult['response'])
                    ]);
                }
            } else {
                $errorMsg = $result['response']['description'] ?? 'Unknown error';
            }
        }
    } else {
        $success = true;
    }
    
    if ($success) {
        dbQuery("UPDATE broadcasts SET status = 'sent', sent_at = CURRENT_TIMESTAMP, telegram_message_id = ? WHERE id = ?", [
            $telegramMessageId,
            $broadcast['id']
        ]);
        
        $message = "✅ Broadcast sent successfully!";
        $messageType = 'alert-success';
        
        header("Location: /admin/broadcast-view.php?id=" . $broadcast['id']);
        exit;
    } else {
        dbQuery("UPDATE broadcasts SET status = 'failed', error_message = ? WHERE id = ?", [
            $errorMsg,
            $broadcast['id']
        ]);
        
        $message = "❌ Failed to send broadcast: " . htmlspecialchars($errorMsg);
        $messageType = 'alert-danger';
    }
}
?>

<div class="page-header">
    <h2>📤 Send Broadcast</h2>
    <p class="subtitle">Review and confirm sending</p>
</div>

<?php if ($message): ?>
    <div class="alert <?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-bottom: 20px;">📝 Broadcast Preview</h3>
    
    <div style="background: rgba(255, 255, 255, 0.02); padding: 20px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 20px;">
        <div style="margin-bottom: 15px;">
            <strong style="color: #94a3b8;">Title:</strong>
            <div style="color: #f8fafc; font-size: 18px; margin-top: 5px;"><?php echo htmlspecialchars($broadcast['title']); ?></div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <strong style="color: #94a3b8;">Type:</strong>
            <div style="color: #f8fafc; margin-top: 5px;"><?php echo ucfirst($broadcast['content_type']); ?></div>
        </div>
        
        <?php if ($broadcast['content_type'] === 'text'): ?>
            <div style="margin-bottom: 15px;">
                <strong style="color: #94a3b8;">Message:</strong>
                <div style="color: #f8fafc; margin-top: 10px; padding: 15px; background: rgba(0, 0, 0, 0.3); border-radius: 8px; white-space: pre-wrap;">
                    <?php echo htmlspecialchars($broadcast['content_text']); ?>
                </div>
            </div>
        <?php elseif ($broadcast['content_type'] === 'photo' || $broadcast['content_type'] === 'video'): ?>
            <div style="margin-bottom: 15px;">
                <strong style="color: #94a3b8;">Media URL:</strong>
                <div style="color: #60a5fa; margin-top: 5px; word-break: break-all;"><?php echo htmlspecialchars($broadcast['media_url']); ?></div>
            </div>
            <?php if ($broadcast['media_caption']): ?>
                <div style="margin-bottom: 15px;">
                    <strong style="color: #94a3b8;">Caption:</strong>
                    <div style="color: #f8fafc; margin-top: 5px;"><?php echo htmlspecialchars($broadcast['media_caption']); ?></div>
                </div>
            <?php endif; ?>
        <?php elseif ($broadcast['content_type'] === 'poll'): ?>
            <div style="margin-bottom: 15px;">
                <strong style="color: #94a3b8;">Question:</strong>
                <div style="color: #f8fafc; margin-top: 5px;"><?php echo htmlspecialchars($broadcast['poll_question']); ?></div>
            </div>
            <div style="margin-bottom: 15px;">
                <strong style="color: #94a3b8;">Options:</strong>
                <div style="color: #f8fafc; margin-top: 5px;">
                    <?php 
                    $pollOptions = json_decode($broadcast['poll_options'], true);
                    foreach ($pollOptions as $index => $option): 
                    ?>
                        <div><?php echo ($index + 1) . '. ' . htmlspecialchars($option); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($broadcast['buttons'])): ?>
            <div style="margin-bottom: 15px;">
                <strong style="color: #94a3b8;">Buttons:</strong>
                <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php 
                    $buttons = json_decode($broadcast['buttons'], true);
                    foreach ($buttons as $button): 
                    ?>
                        <div style="padding: 8px 16px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.4); border-radius: 6px; color: #60a5fa;">
                            <?php echo htmlspecialchars($button['text']); ?>
                            <small style="opacity: 0.7;">(<?php echo $button['type']; ?>)</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 15px;">
            <strong style="color: #94a3b8;">Destinations:</strong>
            <div style="margin-top: 5px; display: flex; gap: 10px;">
                <?php if ($broadcast['send_to_telegram']): ?>
                    <span class="badge success">📱 Telegram: <?php echo htmlspecialchars($broadcast['telegram_channel_id']); ?></span>
                <?php endif; ?>
                <?php if ($broadcast['send_to_inapp']): ?>
                    <span class="badge primary">📰 In-App Feed</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($broadcast['pin_message']): ?>
            <div style="margin-bottom: 15px;">
                <span class="badge warning">📌 Message will be pinned</span>
            </div>
        <?php endif; ?>
        
        <?php if ($broadcast['is_giveaway']): ?>
            <div style="margin-bottom: 15px;">
                <span class="badge warning">🎁 Giveaway: <?php echo $broadcast['giveaway_winners_count']; ?> winner(s)</span>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (empty($telegram_bot_token) && $broadcast['send_to_telegram']): ?>
        <div class="alert alert-warning">
            ⚠️ <strong>Warning:</strong> TELEGRAM_BOT_TOKEN is not configured. Telegram delivery will fail.
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div style="display: flex; gap: 10px;">
            <button type="submit" name="confirm_send" class="btn btn-success">
                📤 Confirm & Send Now
            </button>
            <a href="/admin/broadcast-create.php?id=<?php echo $broadcast['id']; ?>" class="btn">
                ← Back to Edit
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
