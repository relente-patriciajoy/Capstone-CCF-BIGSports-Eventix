<?php
/*
 * sidebar_profile_card.php
 * Replaces <h2 class="logo">Eventix</h2> in ALL sidebars.
 *
 * Usage inside sidebar:
 *   <?php include('sidebar_profile_card.php'); ?>
 *
 * Assumes $conn and session are already active.
 */

$_sp_uid  = $_SESSION['user_id'] ?? 0;
$_sp_role = $_SESSION['role']    ?? $_SESSION['user_role'] ?? 'user';

// Fetch fresh name + satellite
$_sp_first = $_sp_last = $_sp_satellite = '';
if (!empty($conn) && $_sp_uid) {
    $_sp_q = $conn->prepare("SELECT first_name, last_name, ccf_satellite FROM user WHERE user_id = ?");
    $_sp_q->bind_param('i', $_sp_uid);
    $_sp_q->execute();
    $_sp_row = $_sp_q->get_result()->fetch_assoc();
    $_sp_q->close();
    $_sp_first     = $_sp_row['first_name']    ?? '';
    $_sp_last      = $_sp_row['last_name']     ?? '';
    $_sp_satellite = $_sp_row['ccf_satellite'] ?? '';
} else {
    $_sp_first = $_SESSION['first_name'] ?? 'U';
    $_sp_last  = $_SESSION['last_name']  ?? '';
}

$_sp_name     = trim("$_sp_first $_sp_last") ?: 'User';
$_sp_initials = strtoupper(substr($_sp_first, 0, 1) . substr($_sp_last, 0, 1)) ?: 'U';

// Role badge styling
$_sp_badges = [
    'admin'      => ['bg'=>'#fef3c7','color'=>'#92400e','icon'=>'shield'],
    'event_head' => ['bg'=>'#ede9fe','color'=>'#5b21b6','icon'=>'calendar'],
    'user'       => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'user'],
    'attendee'   => ['bg'=>'#dbeafe','color'=>'#1e40af','icon'=>'user'],
];
$_sp_badge    = $_sp_badges[$_sp_role] ?? ['bg'=>'#f3f4f6','color'=>'#374151','icon'=>'user'];
$_sp_role_lbl = ['admin'=>'Admin','event_head'=>'Event Head','user'=>'Member','attendee'=>'Member'][$_sp_role] ?? ucfirst($_sp_role);

// Profile page URL — relative, works on all environments
$_sp_url = '../components/user_profile.php';
?>
<style>
.sp-card {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; margin: 0 0 6px 0;
    border-radius: 14px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    text-decoration: none; cursor: pointer;
    transition: background 0.2s, transform 0.15s;
}
.sp-card:hover { background: rgba(255,255,255,0.13); transform: translateY(-1px); }

.sp-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #c9a84c 0%, #e8c86a 100%);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; font-weight: 800; color: #1a1a1a;
    flex-shrink: 0; border: 2px solid rgba(255,255,255,0.2);
}
.sp-info { flex: 1; min-width: 0; }
.sp-name {
    font-size: 0.88rem; font-weight: 700; color: #ffffff;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    line-height: 1.2; margin-bottom: 4px;
}
.sp-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.66rem; font-weight: 600; padding: 2px 8px;
    border-radius: 20px; text-transform: capitalize; letter-spacing: 0.3px;
}
.sp-satellite {
    font-size: 0.68rem; color: rgba(255,255,255,0.45);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px;
}
.sp-chevron { color: rgba(255,255,255,0.35); flex-shrink: 0; transition: color 0.2s; }
.sp-card:hover .sp-chevron { color: rgba(255,255,255,0.75); }

.sp-wordmark {
    font-size: 0.68rem; font-weight: 700; color: rgba(255,255,255,0.28);
    text-transform: uppercase; letter-spacing: 2px;
    display: flex; align-items: center; gap: 6px; margin-bottom: 14px;
}
.sp-wordmark::before, .sp-wordmark::after {
    content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.09);
}
</style>

<a href="<?= $_sp_url ?>" class="sp-card">
    <div class="sp-avatar"><?= htmlspecialchars($_sp_initials) ?></div>
    <div class="sp-info">
        <div class="sp-name"><?= htmlspecialchars($_sp_name) ?></div>
        <span class="sp-badge"
              style="background:<?= $_sp_badge['bg'] ?>;color:<?= $_sp_badge['color'] ?>;">
            <?= htmlspecialchars($_sp_role_lbl) ?>
        </span>
        <?php if ($_sp_satellite): ?>
        <div class="sp-satellite">
            <i data-lucide="map-pin" style="width:9px;height:9px;vertical-align:middle;"></i>
            <?= htmlspecialchars($_sp_satellite) ?>
        </div>
        <?php endif; ?>
    </div>
    <i data-lucide="chevron-right" class="sp-chevron" style="width:15px;height:15px;"></i>
</a>
<div class="sp-wordmark">Eventix</div>