<?php
$pageTitle = 'Giveaway Winners';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

if (!isset($_GET['id'])) {
    header("Location: /admin/broadcaster.php");
    exit;
}

$broadcast = dbFetchOne("SELECT * FROM broadcasts WHERE id = ? AND is_giveaway = true", [(int)$_GET['id']]);

if (!$broadcast) {
    header("Location: /admin/broadcaster.php");
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_winners'])) {
    $winnersCount = $broadcast['giveaway_winners_count'] ?? 1;
    
    dbQuery("UPDATE giveaway_entries SET is_winner = false WHERE broadcast_id = ?", [$broadcast['id']]);
    
    $allEntries = dbFetchAll("SELECT id FROM giveaway_entries WHERE broadcast_id = ? ORDER BY RANDOM() LIMIT ?", [
        $broadcast['id'],
        $winnersCount
    ]);
    
    foreach ($allEntries as $entry) {
        dbQuery("UPDATE giveaway_entries SET is_winner = true WHERE id = ?", [$entry['id']]);
    }
    
    $message = "✅ Successfully selected " . count($allEntries) . " winner(s)!";
    $messageType = 'alert-success';
}

$entries = dbFetchAll("
    SELECT ge.*, u.first_name, u.last_name, u.email, u.telegram_id
    FROM giveaway_entries ge
    LEFT JOIN users u ON ge.user_id = u.id
    WHERE ge.broadcast_id = ?
    ORDER BY ge.is_winner DESC, ge.entered_at DESC
", [$broadcast['id']]);

$winners = array_filter($entries, fn($e) => $e['is_winner']);
?>

<div class="page-header">
    <h2>🎁 Giveaway Winners</h2>
    <p class="subtitle"><?php echo htmlspecialchars($broadcast['title']); ?></p>
</div>

<?php if ($message): ?>
    <div class="alert <?php echo $messageType; ?>">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3>📊 Giveaway Stats</h3>
            <div style="margin-top: 10px; color: #94a3b8;">
                Total Entries: <strong style="color: #f8fafc;"><?php echo count($entries); ?></strong> | 
                Winners to Select: <strong style="color: #fbbf24;"><?php echo $broadcast['giveaway_winners_count']; ?></strong> | 
                Winners Selected: <strong style="color: #34d399;"><?php echo count($winners); ?></strong>
            </div>
        </div>
        
        <form method="POST">
            <button type="submit" name="select_winners" class="btn btn-primary"
                    onclick="return confirm('This will randomly select <?php echo $broadcast['giveaway_winners_count']; ?> winner(s). Continue?')">
                🎲 Select Random Winners
            </button>
        </form>
    </div>
    
    <?php if ($broadcast['giveaway_ends_at']): ?>
        <div class="alert" style="background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3);">
            ⏰ Giveaway ends: <?php echo date('M d, Y H:i', strtotime($broadcast['giveaway_ends_at'])); ?>
        </div>
    <?php endif; ?>
</div>

<?php if (count($winners) > 0): ?>
<div class="card">
    <h3 style="margin-bottom: 20px;">🏆 Winners</h3>
    
    <table>
        <thead>
            <tr>
                <th>Rank</th>
                <th>User</th>
                <th>Telegram ID</th>
                <th>Email</th>
                <th>Entered At</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $rank = 1;
            foreach ($winners as $winner): 
            ?>
            <tr style="background: rgba(251, 191, 36, 0.1);">
                <td><strong style="color: #fbbf24;">🏆 #<?php echo $rank++; ?></strong></td>
                <td>
                    <?php if ($winner['first_name']): ?>
                        <strong><?php echo htmlspecialchars($winner['first_name'] . ' ' . ($winner['last_name'] ?? '')); ?></strong>
                    <?php else: ?>
                        User #<?php echo $winner['user_id'] ?? 'Unknown'; ?>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($winner['telegram_user_id']); ?></td>
                <td><?php echo htmlspecialchars($winner['email'] ?? 'N/A'); ?></td>
                <td><?php echo date('M d, Y H:i', strtotime($winner['entered_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="card">
    <h3 style="margin-bottom: 20px;">👥 All Entries (<?php echo count($entries); ?>)</h3>
    
    <?php if (count($entries) === 0): ?>
        <div style="text-align: center; padding: 40px; color: #94a3b8;">
            No entries yet
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Telegram ID</th>
                    <th>Email</th>
                    <th>Entered At</th>
                    <th>Status</th>
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
                    <td><?php echo htmlspecialchars($entry['email'] ?? 'N/A'); ?></td>
                    <td><?php echo date('M d, Y H:i', strtotime($entry['entered_at'])); ?></td>
                    <td>
                        <?php if ($entry['is_winner']): ?>
                            <span class="badge success">🏆 Winner</span>
                        <?php else: ?>
                            <span class="badge">Participant</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div style="margin-top: 20px;">
    <a href="/admin/broadcast-view.php?id=<?php echo $broadcast['id']; ?>" class="btn">
        ← Back to Broadcast Details
    </a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
