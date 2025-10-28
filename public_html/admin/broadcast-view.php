<?php
$pageTitle = 'Broadcast Details';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_GET['id'])) {
    header("Location: /admin/broadcaster.php");
    exit;
}

$broadcast = dbFetchOne("
    SELECT b.*, a.username as created_by_username 
    FROM broadcasts b
    LEFT JOIN admin_users a ON b.created_by = a.id
    WHERE b.id = ?
", [(int)$_GET['id']]);

if (!$broadcast) {
    header("Location: /admin/broadcaster.php");
    exit;
}

$logs = dbFetchAll("
    SELECT * FROM broadcast_logs
    WHERE broadcast_id = ?
    ORDER BY created_at DESC
", [$broadcast['id']]);

$entries = dbFetchAll("
    SELECT ge.*, u.first_name, u.last_name, u.email, u.telegram_id
    FROM giveaway_entries ge
    LEFT JOIN users u ON ge.user_id = u.id
    WHERE ge.broadcast_id = ?
    ORDER BY ge.entered_at DESC
", [$broadcast['id']]);
?>

<div class="page-header">
    <h2>📄 Broadcast Details</h2>
    <p class="subtitle">View broadcast information and logs</p>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
        <div>
            <h3 style="margin-bottom: 10px;"><?php echo htmlspecialchars($broadcast['title']); ?></h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if ($broadcast['status'] === 'draft'): ?>
                    <span class="badge">Draft</span>
                <?php elseif ($broadcast['status'] === 'scheduled'): ?>
                    <span class="badge warning">Scheduled</span>
                <?php elseif ($broadcast['status'] === 'sent'): ?>
                    <span class="badge success">Sent</span>
                <?php elseif ($broadcast['status'] === 'failed'): ?>
                    <span class="badge danger">Failed</span>
                <?php endif; ?>
                
                <?php if ($broadcast['is_giveaway']): ?>
                    <span class="badge warning">🎁 Giveaway</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <?php if ($broadcast['status'] === 'draft'): ?>
                <a href="/admin/broadcast-create.php?id=<?php echo $broadcast['id']; ?>" class="btn btn-sm">
                    ✏️ Edit
                </a>
            <?php endif; ?>
            <a href="/admin/broadcaster.php" class="btn btn-sm">
                ← Back to List
            </a>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div>
            <strong style="color: #94a3b8; display: block; margin-bottom: 5px;">Content Type</strong>
            <div style="color: #f8fafc;"><?php echo ucfirst($broadcast['content_type']); ?></div>
        </div>
        
        <div>
            <strong style="color: #94a3b8; display: block; margin-bottom: 5px;">Created By</strong>
            <div style="color: #f8fafc;"><?php echo htmlspecialchars($broadcast['created_by_username'] ?? 'Unknown'); ?></div>
        </div>
        
        <div>
            <strong style="color: #94a3b8; display: block; margin-bottom: 5px;">Created At</strong>
            <div style="color: #f8fafc;"><?php echo date('M d, Y H:i', strtotime($broadcast['created_at'])); ?></div>
        </div>
        
        <?php if ($broadcast['sent_at']): ?>
        <div>
            <strong style="color: #94a3b8; display: block; margin-bottom: 5px;">Sent At</strong>
            <div style="color: #f8fafc;"><?php echo date('M d, Y H:i', strtotime($broadcast['sent_at'])); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($broadcast['telegram_message_id']): ?>
        <div>
            <strong style="color: #94a3b8; display: block; margin-bottom: 5px;">Telegram Message ID</strong>
            <div style="color: #f8fafc;"><?php echo $broadcast['telegram_message_id']; ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <div style="background: rgba(255, 255, 255, 0.02); padding: 20px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
        <h4 style="margin-bottom: 15px; color: #f8fafc;">Content Preview</h4>
        
        <?php if ($broadcast['content_type'] === 'text'): ?>
            <div style="padding: 15px; background: rgba(0, 0, 0, 0.3); border-radius: 8px; color: #f8fafc; white-space: pre-wrap;">
                <?php echo htmlspecialchars($broadcast['content_text']); ?>
            </div>
        <?php elseif ($broadcast['content_type'] === 'photo' || $broadcast['content_type'] === 'video'): ?>
            <div style="margin-bottom: 10px;">
                <strong style="color: #94a3b8;">Media URL:</strong>
                <div style="color: #60a5fa; word-break: break-all;"><?php echo htmlspecialchars($broadcast['media_url']); ?></div>
            </div>
            <?php if ($broadcast['media_caption']): ?>
                <div>
                    <strong style="color: #94a3b8;">Caption:</strong>
                    <div style="color: #f8fafc;"><?php echo htmlspecialchars($broadcast['media_caption']); ?></div>
                </div>
            <?php endif; ?>
        <?php elseif ($broadcast['content_type'] === 'poll'): ?>
            <div style="margin-bottom: 10px;">
                <strong style="color: #94a3b8;">Question:</strong>
                <div style="color: #f8fafc;"><?php echo htmlspecialchars($broadcast['poll_question']); ?></div>
            </div>
            <div>
                <strong style="color: #94a3b8;">Options:</strong>
                <ul style="margin-top: 10px; color: #f8fafc;">
                    <?php 
                    $pollOptions = json_decode($broadcast['poll_options'], true);
                    foreach ($pollOptions as $index => $option): 
                    ?>
                        <li><?php echo htmlspecialchars($option); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($broadcast['buttons'])): ?>
            <div style="margin-top: 20px;">
                <strong style="color: #94a3b8;">Buttons:</strong>
                <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php 
                    $buttons = json_decode($broadcast['buttons'], true);
                    foreach ($buttons as $button): 
                    ?>
                        <div style="padding: 8px 16px; background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.4); border-radius: 6px; color: #60a5fa;">
                            <?php echo htmlspecialchars($button['text']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($broadcast['is_giveaway'] && count($entries) > 0): ?>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3>🎁 Giveaway Entries (<?php echo count($entries); ?>)</h3>
        <?php if ($broadcast['status'] === 'sent'): ?>
            <a href="/admin/broadcast-giveaway.php?id=<?php echo $broadcast['id']; ?>" class="btn btn-primary">
                🎲 Select Winners
            </a>
        <?php endif; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>Telegram ID</th>
                <th>Entered At</th>
                <th>Winner</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entries as $entry): ?>
            <tr>
                <td>
                    <?php if ($entry['first_name']): ?>
                        <?php echo htmlspecialchars($entry['first_name'] . ' ' . ($entry['last_name'] ?? '')); ?>
                    <?php else: ?>
                        User #<?php echo $entry['user_id'] ?? 'Unknown'; ?>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($entry['telegram_user_id']); ?></td>
                <td><?php echo date('M d, Y H:i', strtotime($entry['entered_at'])); ?></td>
                <td>
                    <?php if ($entry['is_winner']): ?>
                        <span class="badge success">🏆 Winner</span>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-bottom: 20px;">📋 Activity Logs</h3>
    
    <?php if (count($logs) === 0): ?>
        <div style="text-align: center; padding: 40px; color: #94a3b8;">
            No activity logs yet
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($log['event_type']))); ?></td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            <span class="badge success">Success</span>
                        <?php else: ?>
                            <span class="badge danger">Failed</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($log['error_message']): ?>
                            <span style="color: #f87171;"><?php echo htmlspecialchars($log['error_message']); ?></span>
                        <?php elseif ($log['telegram_message_id']): ?>
                            Message ID: <?php echo $log['telegram_message_id']; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
