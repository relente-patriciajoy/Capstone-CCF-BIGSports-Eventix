<?php
$current_page = basename($_SERVER['PHP_SELF']);
$sidebar_class = ($role === 'event_head') ? 'eventhead-sidebar' : 'participant-sidebar';
$sidebar_id    = ($role === 'event_head') ? 'eventheadSidebar' : 'participantSidebar';
?>

<div class="mobile-header">
    <button class="hamburger-menu" id="hamburgerBtn" aria-label="Toggle menu">
        <span></span><span></span><span></span>
    </button>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar <?= $sidebar_class ?>" id="<?= $sidebar_id ?>">

    <!-- Profile card replaces the old Eventix logo -->
    <?php include('sidebar_profile_card.php'); ?>

    <button class="sidebar-close" id="closeSidebarBtn" aria-label="Close menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>

    <nav>
        <a href="../dashboard/home.php" class="<?= $current_page === 'home.php' ? 'active' : '' ?>">
            <i data-lucide="home"></i> Home
        </a>
        <a href="../dashboard/events.php" class="<?= $current_page === 'events.php' ? 'active' : '' ?>">
            <i data-lucide="calendar"></i> Browse Events
        </a>
        <a href="../dashboard/my_events.php" class="<?= $current_page === 'my_events.php' ? 'active' : '' ?>">
            <i data-lucide="user-check"></i> My Events
        </a>
        <?php
        if (isset($conn) && isset($_SESSION['user_id'])) {
            $vol_check = $conn->prepare("
                SELECT COUNT(*) as c FROM volunteer_member vm
                JOIN volunteer_role_type vrt ON vm.role_type_id = vrt.role_type_id
                WHERE vm.user_id = ?
            ");
            $vol_check->bind_param("i", $_SESSION['user_id']);
            $vol_check->execute();
            $vol_count = $vol_check->get_result()->fetch_assoc()['c'];
            $vol_check->close();
            if ($vol_count > 0):
        ?>
        <a href="../dashboard/my_volunteer_events.php"
           class="<?= $current_page === 'my_volunteer_events.php' ? 'active' : '' ?>">
            <i data-lucide="users"></i> My Volunteer Events
        </a>
        <?php endif; } ?>

        <a href="../dashboard/attendance.php" class="<?= $current_page === 'attendance.php' ? 'active' : '' ?>">
            <i data-lucide="clipboard-check"></i> Attendance
        </a>
        <a href="../calendar/calendar.php" class="<?= $current_page === 'calendar.php' ? 'active' : '' ?>">
            <i data-lucide="calendar-days"></i> Event Calendar
        </a>

        <?php if (isset($role) && $role === 'event_head'): ?>
        <a href="../event/manage_events.php" class="<?= in_array($current_page, [
            'manage_events.php','scan_qr.php','view_attendance.php',
            'reports.php','participant_engagement.php','inactive_tracking.php','announcement.php'
        ]) ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i> Event Management
        </a>
        <?php endif; ?>

        <a href="../auth/logout.php?return=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
            <i data-lucide="log-out"></i> Logout
        </a>
    </nav>
</aside>

<script>
(function() {
    const hamburgerBtn    = document.getElementById('hamburgerBtn');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');
    const sidebar         = document.getElementById('<?= $sidebar_id ?>');
    const overlay         = document.getElementById('sidebarOverlay');
    const body            = document.body;

    function openSidebar()  { sidebar.classList.add('mobile-open'); overlay.classList.add('active'); body.style.overflow='hidden'; hamburgerBtn.classList.add('active'); }
    function closeSidebar() { sidebar.classList.remove('mobile-open'); overlay.classList.remove('active'); body.style.overflow=''; hamburgerBtn.classList.remove('active'); }
    function toggleSidebar(){ sidebar.classList.contains('mobile-open') ? closeSidebar() : openSidebar(); }

    if (hamburgerBtn)    hamburgerBtn.addEventListener('click', toggleSidebar);
    if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
    if (overlay)         overlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('nav a').forEach(link => {
        link.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) closeSidebar();
    });

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { if (window.innerWidth > 768) closeSidebar(); }, 250);
    });
})();

if (typeof lucide !== 'undefined') lucide.createIcons();
</script>