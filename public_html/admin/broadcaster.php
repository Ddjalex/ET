<?php
$pageTitle = 'Broadcaster';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

$message = '';
$messageType = '';

// Handle delete action
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $broadcastId = (int)$_GET['id'];
    dbQuery("DELETE FROM broadcasts WHERE id = ?", [$broadcastId]);
    $message = "✅ Broadcast deleted successfully";
    $messageType = 'alert-success';
    
    header("Location: /admin/broadcaster.php");
    exit;
}

// Fetch broadcasts
$filter = $_GET['filter'] ?? 'all';
$whereClause = match($filter) {
    'draft' => "status = 'draft'",
    'scheduled' => "status = 'scheduled'",
    'sent' => "status = 'sent'",
    'failed' => "status = 'failed'",
    default => "1=1"
};

$broadcasts = dbFetchAll("
    SELECT 
        b.*,
        a.username as created_by_username,
        (SELECT COUNT(*) FROM giveaway_entries WHERE broadcast_id = b.id) as total_entries
    FROM broadcasts b
    LEFT JOIN admin_users a ON b.created_by = a.id
    WHERE $whereClause
    ORDER BY b.created_at DESC
    LIMIT 100
");

$statusCounts = dbFetchOne("
    SELECT 
        COUNT(*) FILTER (WHERE status = 'draft') as draft,
        COUNT(*) FILTER (WHERE status = 'scheduled') as scheduled,
        COUNT(*) FILTER (WHERE status = 'sent') as sent,
        COUNT(*) FILTER (WHERE status = 'failed') as failed,
        COUNT(*) as total
    FROM broadcasts
");
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2>📢 Broadcaster</h2>
            <p class="subtitle">Create and manage broadcasts for Telegram channel and in-app feed</p>
        </div>
        <a href="/admin/broadcast-create.php" class="btn btn-primary">
            ✚ Create New Broadcast
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : ''; ?> btn-sm">
                All (<?php echo $statusCounts['total'] ?? 0; ?>)
            </a>
            <a href="?filter=draft" class="btn <?php echo $filter === 'draft' ? 'btn-primary' : ''; ?> btn-sm">
                Drafts (<?php echo $statusCounts['draft'] ?? 0; ?>)
            </a>
            <a href="?filter=scheduled" class="btn <?php echo $filter === 'scheduled' ? 'btn-primary' : ''; ?> btn-sm">
                Scheduled (<?php echo $statusCounts['scheduled'] ?? 0; ?>)
            </a>
            <a href="?filter=sent" class="btn <?php echo $filter === 'sent' ? 'btn-primary' : ''; ?> btn-sm">
                Sent (<?php echo $statusCounts['sent'] ?? 0; ?>)
            </a>
            <a href="?filter=failed" class="btn <?php echo $filter === 'failed' ? 'btn-primary' : ''; ?> btn-sm">
                Failed (<?php echo $statusCounts['failed'] ?? 0; ?>)
            </a>
        </div>
    </div>

    <?php if (count($broadcasts) === 0): ?>
        <div style="text-align: center; padding: 60px 20px; color: #94a3b8;">
            <div style="font-size: 48px; margin-bottom: 16px;">📢</div>
            <h3 style="color: #cbd5e1; margin-bottom: 8px;">No broadcasts yet</h3>
            <p style="margin-bottom: 24px;">Create your first broadcast to get started</p>
            <a href="/admin/broadcast-create.php" class="btn btn-primary">
                ✚ Create New Broadcast
            </a>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Destinations</th>
                    <th>Status</th>
                    <th>Scheduled/Sent</th>
                    <th>Giveaway</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($broadcasts as $broadcast): ?>
                <tr>
                    <td><strong>#<?php echo $broadcast['id']; ?></strong></td>
                    <td>
                        <strong><?php echo htmlspecialchars($broadcast['title']); ?></strong>
                        <?php if ($broadcast['is_giveaway']): ?>
                            <span class="badge warning" style="margin-left: 5px;">🎁 Giveaway</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $typeIcons = [
                            'text' => '📝',
                            'photo' => '📷',
                            'video' => '🎥',
                            'poll' => '📊'
                        ];
                        echo ($typeIcons[$broadcast['content_type']] ?? '📄') . ' ' . ucfirst($broadcast['content_type']);
                        ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php if ($broadcast['send_to_telegram']): ?>
                                <span class="badge success">📱 Telegram</span>
                            <?php endif; ?>
                            <?php if ($broadcast['send_to_inapp']): ?>
                                <span class="badge primary">📰 In-App</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($broadcast['status'] === 'draft'): ?>
                            <span class="badge">Draft</span>
                        <?php elseif ($broadcast['status'] === 'scheduled'): ?>
                            <span class="badge warning">Scheduled</span>
                        <?php elseif ($broadcast['status'] === 'sent'): ?>
                            <span class="badge success">Sent</span>
                        <?php elseif ($broadcast['status'] === 'failed'): ?>
                            <span class="badge danger">Failed</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($broadcast['sent_at']): ?>
                            <?php echo date('M d, Y H:i', strtotime($broadcast['sent_at'])); ?>
                        <?php elseif ($broadcast['scheduled_for']): ?>
                            <?php echo date('M d, Y H:i', strtotime($broadcast['scheduled_for'])); ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($broadcast['is_giveaway']): ?>
                            <strong><?php echo $broadcast['total_entries']; ?></strong> entries
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="/admin/broadcast-view.php?id=<?php echo $broadcast['id']; ?>" 
                               class="btn btn-sm" 
                               title="View Details">
                                👁️
                            </a>
                            <?php if ($broadcast['status'] === 'draft'): ?>
                                <a href="/admin/broadcast-create.php?id=<?php echo $broadcast['id']; ?>" 
                                   class="btn btn-sm btn-primary" 
                                   title="Edit">
                                    ✏️
                                </a>
                            <?php endif; ?>
                            <?php if (in_array($broadcast['status'], ['draft', 'scheduled'])): ?>
                                <a href="/admin/broadcast-send.php?id=<?php echo $broadcast['id']; ?>" 
                                   class="btn btn-sm btn-success" 
                                   title="Send Now">
                                    📤
                                </a>
                            <?php endif; ?>
                            <?php if ($broadcast['is_giveaway'] && $broadcast['status'] === 'sent'): ?>
                                <a href="/admin/broadcast-giveaway.php?id=<?php echo $broadcast['id']; ?>" 
                                   class="btn btn-sm" 
                                   style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);"
                                   title="Select Winners">
                                    🎁
                                </a>
                            <?php endif; ?>
                            <a href="?delete=1&id=<?php echo $broadcast['id']; ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this broadcast?')"
                               title="Delete">
                                🗑️
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div style="margin-top: 30px;">
    <div class="card">
        <h3 style="margin-bottom: 15px;">📊 Quick Stats</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div style="text-align: center; padding: 20px; background: rgba(59, 130, 246, 0.1); border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.3);">
                <div style="font-size: 32px; font-weight: 700; color: #60a5fa; margin-bottom: 5px;">
                    <?php echo $statusCounts['total'] ?? 0; ?>
                </div>
                <div style="color: #94a3b8; font-size: 14px;">Total Broadcasts</div>
            </div>
            <div style="text-align: center; padding: 20px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.3);">
                <div style="font-size: 32px; font-weight: 700; color: #34d399; margin-bottom: 5px;">
                    <?php echo $statusCounts['sent'] ?? 0; ?>
                </div>
                <div style="color: #94a3b8; font-size: 14px;">Sent</div>
            </div>
            <div style="text-align: center; padding: 20px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3);">
                <div style="font-size: 32px; font-weight: 700; color: #fbbf24; margin-bottom: 5px;">
                    <?php echo $statusCounts['scheduled'] ?? 0; ?>
                </div>
                <div style="color: #94a3b8; font-size: 14px;">Scheduled</div>
            </div>
            <div style="text-align: center; padding: 20px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.3);">
                <div style="font-size: 32px; font-weight: 700; color: #f87171; margin-bottom: 5px;">
                    <?php echo $statusCounts['failed'] ?? 0; ?>
                </div>
                <div style="color: #94a3b8; font-size: 14px;">Failed</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
