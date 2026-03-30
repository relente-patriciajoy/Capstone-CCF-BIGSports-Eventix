<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole('admin');

include('../../includes/db.php');

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$message   = "";
$error     = "";

// Handle resolve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $action     = $_POST['action'] ?? '';
    $admin_note = trim($_POST['admin_note'] ?? '');

    if ($request_id && in_array($action, ['resolved', 'rejected'])) {
        $stmt = $conn->prepare("
            UPDATE account_recovery_request
            SET status = ?, admin_note = ?, resolved_at = NOW(), resolved_by = ?
            WHERE request_id = ?
        ");
        $stmt->bind_param("ssii", $action, $admin_note, $user_id, $request_id);
        if ($stmt->execute()) {
            $message = "Request #$request_id marked as " . ucfirst($action) . ".";
        } else {
            $error = "Failed to update request.";
        }
        $stmt->close();
    }
}

// Fetch requests — pending first, then by date
$filter = $_GET['filter'] ?? 'pending';
$allowed_filters = ['pending', 'resolved', 'rejected', 'all'];
if (!in_array($filter, $allowed_filters)) $filter = 'pending';

if ($filter === 'all') {
    $requests = $conn->query("SELECT * FROM account_recovery_request ORDER BY FIELD(status,'pending','rejected','resolved'), submitted_at DESC");
} else {
    $stmt = $conn->prepare("SELECT * FROM account_recovery_request WHERE status = ? ORDER BY submitted_at DESC");
    $stmt->bind_param("s", $filter);
    $stmt->execute();
    $requests = $stmt->get_result();
}

// Count badges
$counts = [];
foreach (['pending','resolved','rejected'] as $s) {
    $r = $conn->query("SELECT COUNT(*) as c FROM account_recovery_request WHERE status = '$s'");
    $counts[$s] = $r->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Account Recovery Requests — Admin</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/sidebar.css">
    <link rel="stylesheet" href="../../css/management.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="dashboard-layout">
    <?php include('admin_sidebar.php'); ?>

    <main class="management-content">
        <div class="admin-header">
            <div class="admin-badge">
                <i data-lucide="shield" style="width:14px;height:14px;"></i>
                Administrator
            </div>
            <h1>Account Recovery Requests</h1>
            <p>Review and resolve requests from users who forgot their email address</p>
        </div>

        <?php if ($message): ?>
            <div class="management-alert success">
                <i data-lucide="check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="management-alert error">
                <i data-lucide="alert-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Filter tabs -->
        <div class="management-card">
            <div class="card-toolbar">
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <?php foreach (['pending'=>'warning','resolved'=>'success','rejected'=>'danger','all'=>'info'] as $f => $color): ?>
                        <a href="?filter=<?= $f ?>"
                           class="btn btn-sm <?= $filter === $f ? 'btn-primary' : 'btn-secondary' ?>">
                            <?= ucfirst($f) ?>
                            <?php if ($f !== 'all' && isset($counts[$f]) && $counts[$f] > 0): ?>
                                <span class="badge badge-<?= $color ?>"><?= $counts[$f] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($counts['pending'] > 0): ?>
                    <span class="badge badge-warning" style="font-size:0.85rem;padding:6px 12px;">
                        <?= $counts['pending'] ?> pending review
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Requests list -->
        <?php if ($requests->num_rows > 0): ?>
            <?php while ($req = $requests->fetch_assoc()): ?>
            <div class="management-card">
                <div class="card-toolbar">
                    <div>
                        <h2 style="margin-bottom:4px;">
                            <?= htmlspecialchars($req['full_name']) ?>
                        </h2>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                            <span class="badge badge-<?= $req['status']==='pending'?'warning':($req['status']==='resolved'?'success':'danger') ?>">
                                <?= ucfirst($req['status']) ?>
                            </span>
                            <span style="font-size:0.82rem;color:#6b7280;">
                                Submitted: <?= date('M j, Y g:i A', strtotime($req['submitted_at'])) ?>
                            </span>
                            <?php if ($req['resolved_at']): ?>
                                <span style="font-size:0.82rem;color:#6b7280;">
                                    Resolved: <?= date('M j, Y g:i A', strtotime($req['resolved_at'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span style="font-size:0.8rem;color:#6b7280;font-weight:600;">#<?= $req['request_id'] ?></span>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:1rem;">
                    <div>
                        <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#9ca3af;font-weight:600;margin-bottom:4px;">Phone Number</div>
                        <div style="font-weight:600;color:#1a1a1a;"><?= htmlspecialchars($req['phone']) ?></div>
                    </div>
                </div>

                <div style="margin-bottom:1rem;">
                    <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#9ca3af;font-weight:600;margin-bottom:6px;">Message from User</div>
                    <div style="background:#f9fafb;border-radius:8px;padding:12px 14px;font-size:0.9rem;color:#374151;line-height:1.6;border-left:3px solid #e5e7eb;">
                        <?= nl2br(htmlspecialchars($req['message'])) ?>
                    </div>
                </div>

                <?php if ($req['admin_note']): ?>
                    <div style="margin-bottom:1rem;">
                        <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;color:#9ca3af;font-weight:600;margin-bottom:6px;">Admin Note</div>
                        <div style="background:#f0fdf4;border-radius:8px;padding:12px 14px;font-size:0.9rem;color:#166534;line-height:1.6;border-left:3px solid #10b981;">
                            <?= nl2br(htmlspecialchars($req['admin_note'])) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($req['status'] === 'pending'): ?>
                    <form method="POST" class="admin-form-col" style="margin-top:8px;">
                        <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                        <div class="form-group">
                            <label for="note_<?= $req['request_id'] ?>">Admin Note (optional)</label>
                            <textarea name="admin_note" id="note_<?= $req['request_id'] ?>"
                                      rows="2"
                                      placeholder="e.g. Account found — email is juan@example.com. Will contact via phone."></textarea>
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button type="submit" name="action" value="resolved" class="btn btn-primary btn-sm">
                                <i data-lucide="check-circle" style="width:15px;height:15px;"></i>
                                Mark Resolved
                            </button>
                            <button type="submit" name="action" value="rejected" class="btn btn-sm"
                                    style="background:#fee2e2;color:#b91c1c;border:none;"
                                    onclick="return confirm('Reject this request?')">
                                <i data-lucide="x-circle" style="width:15px;height:15px;"></i>
                                Reject
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>

        <?php else: ?>
            <div class="management-card">
                <div class="empty-state">
                    <i data-lucide="inbox"></i>
                    <h3>No <?= $filter === 'all' ? '' : ucfirst($filter) ?> Requests</h3>
                    <p>
                        <?php if ($filter === 'pending'): ?>
                            No pending recovery requests at this time.
                        <?php else: ?>
                            No <?= $filter ?> requests found.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        lucide.createIcons();
        document.querySelectorAll('.management-alert').forEach(a => {
            setTimeout(() => {
                a.style.transition = 'opacity 0.3s'; a.style.opacity = '0';
                setTimeout(() => a.remove(), 300);
            }, 5000);
        });
    </script>
</body>
</html>