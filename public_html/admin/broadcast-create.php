<?php
// Load session and database BEFORE any HTML output
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
requireAdminLogin();
$currentAdmin = getCurrentAdmin();

$pageTitle = 'Create Broadcast';
$message = '';
$messageType = '';
$broadcast = null;
$isEdit = false;

// Check if editing existing broadcast
if (isset($_GET['id'])) {
    $isEdit = true;
    $broadcast = dbFetchOne("SELECT * FROM broadcasts WHERE id = ?", [(int)$_GET['id']]);
    if ($broadcast) {
        $pageTitle = 'Edit Broadcast';
    } else {
        header("Location: /admin/broadcaster.php");
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $contentType = $_POST['content_type'] ?? 'text';
    $contentText = trim($_POST['content_text'] ?? '');
    $mediaUrl = trim($_POST['media_url'] ?? '');
    $mediaCaption = trim($_POST['media_caption'] ?? '');
    
    $pollQuestion = trim($_POST['poll_question'] ?? '');
    $pollOptions = isset($_POST['poll_options']) ? json_encode(array_filter($_POST['poll_options'])) : null;
    $pollType = $_POST['poll_type'] ?? 'regular';
    $pollCorrectOption = isset($_POST['poll_correct_option']) ? (int)$_POST['poll_correct_option'] : null;
    
    $buttons = [];
    if (isset($_POST['button_text']) && is_array($_POST['button_text'])) {
        for ($i = 0; $i < count($_POST['button_text']); $i++) {
            $buttonText = trim($_POST['button_text'][$i] ?? '');
            $buttonType = $_POST['button_type'][$i] ?? 'url';
            $buttonData = trim($_POST['button_data'][$i] ?? '');
            
            if ($buttonText && $buttonData) {
                $buttons[] = [
                    'text' => $buttonText,
                    'type' => $buttonType,
                    'data' => $buttonData
                ];
            }
        }
    }
    $buttonsJson = !empty($buttons) ? json_encode($buttons) : null;
    
    $sendToTelegram = isset($_POST['send_to_telegram']) ? 1 : 0;
    $sendToInapp = isset($_POST['send_to_inapp']) ? 1 : 0;
    $telegramChannelId = trim($_POST['telegram_channel_id'] ?? '');
    
    $scheduleType = $_POST['schedule_type'] ?? 'draft';
    $scheduledFor = null;
    $sendNow = 0;
    $status = 'draft';
    
    if ($scheduleType === 'schedule' && !empty($_POST['scheduled_for'])) {
        $scheduledFor = $_POST['scheduled_for'];
        $status = 'scheduled';
    } elseif ($scheduleType === 'send_now') {
        $sendNow = 1;
    }
    
    $pinMessage = isset($_POST['pin_message']) ? 1 : 0;
    
    $isGiveaway = isset($_POST['is_giveaway']) ? 1 : 0;
    $giveawayWinnersCount = $isGiveaway ? (int)($_POST['giveaway_winners_count'] ?? 1) : 0;
    $giveawayEndsAt = $isGiveaway && !empty($_POST['giveaway_ends_at']) ? $_POST['giveaway_ends_at'] : null;
    
    if ($isEdit && $broadcast) {
        dbQuery("UPDATE broadcasts SET 
            title = ?, content_type = ?, content_text = ?, media_url = ?, media_caption = ?,
            poll_question = ?, poll_options = ?, poll_type = ?, poll_correct_option = ?,
            buttons = ?, send_to_telegram = ?, send_to_inapp = ?, telegram_channel_id = ?,
            scheduled_for = ?, send_now = ?, pin_message = ?,
            is_giveaway = ?, giveaway_winners_count = ?, giveaway_ends_at = ?,
            status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?", [
            $title, $contentType, $contentText, $mediaUrl, $mediaCaption,
            $pollQuestion, $pollOptions, $pollType, $pollCorrectOption,
            $buttonsJson, $sendToTelegram, $sendToInapp, $telegramChannelId,
            $scheduledFor, $sendNow, $pinMessage,
            $isGiveaway, $giveawayWinnersCount, $giveawayEndsAt,
            $status, $broadcast['id']
        ]);
        
        $message = "✅ Broadcast updated successfully";
        $messageType = 'alert-success';
        
        if ($sendNow) {
            header("Location: /admin/broadcast-send.php?id=" . $broadcast['id']);
            exit;
        }
    } else {
        $result = dbQuery("INSERT INTO broadcasts (
            title, content_type, content_text, media_url, media_caption,
            poll_question, poll_options, poll_type, poll_correct_option,
            buttons, send_to_telegram, send_to_inapp, telegram_channel_id,
            scheduled_for, send_now, pin_message,
            is_giveaway, giveaway_winners_count, giveaway_ends_at,
            status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
            $title, $contentType, $contentText, $mediaUrl, $mediaCaption,
            $pollQuestion, $pollOptions, $pollType, $pollCorrectOption,
            $buttonsJson, $sendToTelegram, $sendToInapp, $telegramChannelId,
            $scheduledFor, $sendNow, $pinMessage,
            $isGiveaway, $giveawayWinnersCount, $giveawayEndsAt,
            $status, $currentAdmin['id']
        ]);
        
        $newId = dbFetchOne("SELECT currval(pg_get_serial_sequence('broadcasts', 'id')) as id")['id'];
        
        $message = "✅ Broadcast created successfully";
        $messageType = 'alert-success';
        
        if ($sendNow) {
            header("Location: /admin/broadcast-send.php?id=" . $newId);
            exit;
        }
    }
    
    header("Location: /admin/broadcaster.php");
    exit;
}

// Now include header AFTER all POST processing and redirects
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2><?php echo $isEdit ? '✏️ Edit Broadcast' : '✚ Create New Broadcast'; ?></h2>
    <p class="subtitle">Create engaging content for your audience</p>
</div>

<?php if ($message): ?>
    <div class="alert <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<form method="POST" id="broadcastForm">
    <div class="card">
        <h3 style="margin-bottom: 20px;">📝 Basic Information</h3>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                Broadcast Title <span style="color: #ef4444;">*</span>
            </label>
            <input type="text" name="title" required 
                   value="<?php echo htmlspecialchars($broadcast['title'] ?? ''); ?>"
                   placeholder="e.g., New Feature Announcement"
                   style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                Content Type <span style="color: #ef4444;">*</span>
            </label>
            <select name="content_type" id="contentType" required
                    style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;"
                    onchange="toggleContentFields()">
                <option value="text" <?php echo ($broadcast['content_type'] ?? 'text') === 'text' ? 'selected' : ''; ?>>📝 Text Message</option>
                <option value="photo" <?php echo ($broadcast['content_type'] ?? '') === 'photo' ? 'selected' : ''; ?>>📷 Photo</option>
                <option value="video" <?php echo ($broadcast['content_type'] ?? '') === 'video' ? 'selected' : ''; ?>>🎥 Video</option>
                <option value="poll" <?php echo ($broadcast['content_type'] ?? '') === 'poll' ? 'selected' : ''; ?>>📊 Poll</option>
            </select>
        </div>
    </div>
    
    <div class="card" id="textContent">
        <h3 style="margin-bottom: 20px;">✍️ Content</h3>
        
        <div style="margin-bottom: 20px;" id="textField">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                Message Text <span style="color: #ef4444;">*</span>
            </label>
            <textarea name="content_text" rows="6"
                      placeholder="Enter your message here. HTML formatting supported."
                      style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc; font-family: monospace;"><?php echo htmlspecialchars($broadcast['content_text'] ?? ''); ?></textarea>
            <small style="color: #94a3b8;">HTML tags supported: &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;a&gt;, &lt;code&gt;</small>
        </div>
        
        <div style="margin-bottom: 20px; display: none;" id="mediaUrlField">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                Media URL <span style="color: #ef4444;">*</span>
            </label>
            <input type="url" name="media_url"
                   value="<?php echo htmlspecialchars($broadcast['media_url'] ?? ''); ?>"
                   placeholder="https://example.com/image.jpg or https://example.com/video.mp4"
                   style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
        </div>
        
        <div style="margin-bottom: 20px; display: none;" id="captionField">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                Caption
            </label>
            <textarea name="media_caption" rows="3"
                      placeholder="Optional caption for your photo/video"
                      style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;"><?php echo htmlspecialchars($broadcast['media_caption'] ?? ''); ?></textarea>
        </div>
        
        <div style="display: none;" id="pollFields">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                    Poll Question <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="poll_question"
                       value="<?php echo htmlspecialchars($broadcast['poll_question'] ?? ''); ?>"
                       placeholder="What is your favorite feature?"
                       style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                    Poll Options (minimum 2)
                </label>
                <?php 
                $pollOptions = !empty($broadcast['poll_options']) ? json_decode($broadcast['poll_options'], true) : ['', ''];
                for ($i = 0; $i < max(2, count($pollOptions)); $i++): 
                ?>
                <input type="text" name="poll_options[]"
                       value="<?php echo htmlspecialchars($pollOptions[$i] ?? ''); ?>"
                       placeholder="Option <?php echo $i + 1; ?>"
                       style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc; margin-bottom: 10px;">
                <?php endfor; ?>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                    Poll Type
                </label>
                <select name="poll_type"
                        style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
                    <option value="regular" <?php echo ($broadcast['poll_type'] ?? 'regular') === 'regular' ? 'selected' : ''; ?>>Regular Poll</option>
                    <option value="quiz" <?php echo ($broadcast['poll_type'] ?? '') === 'quiz' ? 'selected' : ''; ?>>Quiz (with correct answer)</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h3 style="margin-bottom: 20px;">🔘 Inline Buttons</h3>
        
        <div id="buttonsContainer">
            <?php 
            $buttons = !empty($broadcast['buttons']) ? json_decode($broadcast['buttons'], true) : [];
            if (empty($buttons)) {
                $buttons = [['text' => '', 'type' => 'url', 'data' => '']];
            }
            foreach ($buttons as $index => $button): 
            ?>
            <div style="display: grid; grid-template-columns: 2fr 1fr 2fr auto; gap: 10px; margin-bottom: 10px;">
                <input type="text" name="button_text[]"
                       value="<?php echo htmlspecialchars($button['text'] ?? ''); ?>"
                       placeholder="Button Text"
                       style="padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
                <select name="button_type[]"
                        style="padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
                    <option value="url" <?php echo ($button['type'] ?? 'url') === 'url' ? 'selected' : ''; ?>>URL</option>
                    <option value="giveaway" <?php echo ($button['type'] ?? '') === 'giveaway' ? 'selected' : ''; ?>>Giveaway Entry</option>
                </select>
                <input type="text" name="button_data[]"
                       value="<?php echo htmlspecialchars($button['data'] ?? ''); ?>"
                       placeholder="URL or tracking data"
                       style="padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
                <button type="button" onclick="removeButton(this)" class="btn btn-sm btn-danger">✖</button>
            </div>
            <?php endforeach; ?>
        </div>
        
        <button type="button" onclick="addButton()" class="btn btn-sm" style="margin-top: 10px;">
            ➕ Add Button
        </button>
    </div>
    
    <div class="card">
        <h3 style="margin-bottom: 20px;">📍 Destinations</h3>
        
        <div style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="send_to_telegram" value="1"
                       <?php echo ($broadcast['send_to_telegram'] ?? false) ? 'checked' : ''; ?>
                       style="width: 20px; height: 20px;">
                <span style="color: #f8fafc; font-weight: 600;">📱 Send to Telegram Channel</span>
            </label>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                Telegram Channel ID
            </label>
            <input type="text" name="telegram_channel_id"
                   value="<?php echo htmlspecialchars($broadcast['telegram_channel_id'] ?? ''); ?>"
                   placeholder="@yourchannel or -1001234567890"
                   style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
            <small style="color: #94a3b8;">Format: @channel_username or channel ID</small>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="send_to_inapp" value="1"
                       <?php echo ($broadcast['send_to_inapp'] ?? true) ? 'checked' : ''; ?>
                       style="width: 20px; height: 20px;">
                <span style="color: #f8fafc; font-weight: 600;">📰 Save to In-App Feed</span>
            </label>
        </div>
    </div>
    
    <div class="card">
        <h3 style="margin-bottom: 20px;">⏰ Schedule & Options</h3>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                Delivery Time
            </label>
            <select name="schedule_type" id="scheduleType" onchange="toggleScheduleFields()"
                    style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc; margin-bottom: 10px;">
                <option value="draft">Save as Draft</option>
                <option value="schedule">Schedule for Later</option>
                <option value="send_now">Send Now</option>
            </select>
            
            <div id="scheduleField" style="display: none;">
                <input type="datetime-local" name="scheduled_for"
                       value="<?php echo $broadcast['scheduled_for'] ? date('Y-m-d\TH:i', strtotime($broadcast['scheduled_for'])) : ''; ?>"
                       style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="pin_message" value="1"
                       <?php echo ($broadcast['pin_message'] ?? false) ? 'checked' : ''; ?>
                       style="width: 20px; height: 20px;">
                <span style="color: #f8fafc; font-weight: 600;">📌 Pin Message (Telegram only)</span>
            </label>
        </div>
    </div>
    
    <div class="card">
        <h3 style="margin-bottom: 20px;">🎁 Giveaway Settings</h3>
        
        <div style="margin-bottom: 15px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="checkbox" name="is_giveaway" value="1" id="isGiveaway" onchange="toggleGiveawayFields()"
                       <?php echo ($broadcast['is_giveaway'] ?? false) ? 'checked' : ''; ?>
                       style="width: 20px; height: 20px;">
                <span style="color: #f8fafc; font-weight: 600;">🎁 This is a Giveaway</span>
            </label>
        </div>
        
        <div id="giveawayFields" style="display: <?php echo ($broadcast['is_giveaway'] ?? false) ? 'block' : 'none'; ?>;">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                    Number of Winners
                </label>
                <input type="number" name="giveaway_winners_count" min="1"
                       value="<?php echo $broadcast['giveaway_winners_count'] ?? 1; ?>"
                       style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #f8fafc;">
                    Giveaway Ends At
                </label>
                <input type="datetime-local" name="giveaway_ends_at"
                       value="<?php echo $broadcast['giveaway_ends_at'] ? date('Y-m-d\TH:i', strtotime($broadcast['giveaway_ends_at'])) : ''; ?>"
                       style="width: 100%; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
            </div>
            
            <div style="padding: 12px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px;">
                <small style="color: #93c5fd;">
                    💡 <strong>Tip:</strong> Add a button with type "Giveaway Entry" to track participants. 
                    Users who click this button will be automatically entered into the giveaway.
                </small>
            </div>
        </div>
    </div>
    
    <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button type="submit" class="btn btn-primary">
            <?php echo $isEdit ? '💾 Update Broadcast' : '✨ Create Broadcast'; ?>
        </button>
        <a href="/admin/broadcaster.php" class="btn">
            ← Back to List
        </a>
    </div>
</form>

<script>
function toggleContentFields() {
    const type = document.getElementById('contentType').value;
    const textField = document.getElementById('textField');
    const mediaUrlField = document.getElementById('mediaUrlField');
    const captionField = document.getElementById('captionField');
    const pollFields = document.getElementById('pollFields');
    
    textField.style.display = 'block';
    mediaUrlField.style.display = 'none';
    captionField.style.display = 'none';
    pollFields.style.display = 'none';
    
    if (type === 'photo' || type === 'video') {
        mediaUrlField.style.display = 'block';
        captionField.style.display = 'block';
        textField.style.display = 'none';
    } else if (type === 'poll') {
        pollFields.style.display = 'block';
        textField.style.display = 'none';
    }
}

function toggleScheduleFields() {
    const type = document.getElementById('scheduleType').value;
    const scheduleField = document.getElementById('scheduleField');
    scheduleField.style.display = type === 'schedule' ? 'block' : 'none';
}

function toggleGiveawayFields() {
    const isGiveaway = document.getElementById('isGiveaway').checked;
    const giveawayFields = document.getElementById('giveawayFields');
    giveawayFields.style.display = isGiveaway ? 'block' : 'none';
}

function addButton() {
    const container = document.getElementById('buttonsContainer');
    const div = document.createElement('div');
    div.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 2fr auto; gap: 10px; margin-bottom: 10px;';
    div.innerHTML = `
        <input type="text" name="button_text[]" placeholder="Button Text" style="padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
        <select name="button_type[]" style="padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
            <option value="url">URL</option>
            <option value="giveaway">Giveaway Entry</option>
        </select>
        <input type="text" name="button_data[]" placeholder="URL or tracking data" style="padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; color: #f8fafc;">
        <button type="button" onclick="removeButton(this)" class="btn btn-sm btn-danger">✖</button>
    `;
    container.appendChild(div);
}

function removeButton(button) {
    button.parentElement.remove();
}

toggleContentFields();
toggleScheduleFields();
toggleGiveawayFields();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
