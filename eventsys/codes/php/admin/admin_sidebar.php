<!-- Floating hamburger button — same pattern as participant/event_head sidebar -->
<div class="mobile-header">
    <button class="hamburger-menu" id="hamburgerBtn" aria-label="Toggle menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
</div>

<!-- Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar — keeps its own logo + close button, starts at top:0 -->
<aside class="sidebar" id="adminSidebar">
    <h2 class="logo">Eventix</h2>

    <button class="sidebar-close" id="closeSidebarBtn" aria-label="Close menu">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>

    <nav>
        <a href="/Registration-System/eventsys/codes/php/admin/admin_dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'admin_dashboard.php' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard"></i> Dashboard
        </a>

        <div class="dropdown-nav <?= in_array(basename($_SERVER['PHP_SELF']), ['manage_user.php','manage_venue.php','manage_organizer.php','manage_categories.php']) ? 'open' : '' ?>">
            <div class="dropdown-toggle" onclick="toggleDropdown(this)">
                <i data-lucide="database"></i>
                <span>Maintenance</span>
                <span>▾</span>
            </div>
            <div class="dropdown-menu">
                <a href="/Registration-System/eventsys/codes/php/admin/manage_user.php"       class="<?= basename($_SERVER['PHP_SELF']) === 'manage_user.php'       ? 'active' : '' ?>">Users</a>
                <a href="/Registration-System/eventsys/codes/php/admin/manage_venue.php"      class="<?= basename($_SERVER['PHP_SELF']) === 'manage_venue.php'      ? 'active' : '' ?>">Venues</a>
                <a href="/Registration-System/eventsys/codes/php/admin/manage_organizer.php"  class="<?= basename($_SERVER['PHP_SELF']) === 'manage_organizer.php'  ? 'active' : '' ?>">Organizers</a>
                <a href="/Registration-System/eventsys/codes/php/admin/manage_categories.php" class="<?= basename($_SERVER['PHP_SELF']) === 'manage_categories.php' ? 'active' : '' ?>">Categories</a>
            </div>
        </div>

        <a href="/Registration-System/eventsys/codes/php/admin/admin_all_events.php"     class="<?= basename($_SERVER['PHP_SELF']) === 'admin_all_events.php'     ? 'active' : '' ?>">
            <i data-lucide="calendar"></i> All Events
        </a>
        <a href="/Registration-System/eventsys/codes/php/admin/admin_view_attendance.php" class="<?= basename($_SERVER['PHP_SELF']) === 'admin_view_attendance.php' ? 'active' : '' ?>">
            <i data-lucide="users"></i> Attendance
        </a>
        <a href="/Registration-System/eventsys/codes/php/admin/user_promotions.php"       class="<?= basename($_SERVER['PHP_SELF']) === 'user_promotions.php'       ? 'active' : '' ?>">
            <i data-lucide="user-plus"></i> Promote Users
        </a>
        <a href="/Registration-System/eventsys/codes/php/admin/admin_recovery_requests.php" class="<?= basename($_SERVER['PHP_SELF']) === 'admin_recovery_requests.php' ? 'active' : '' ?>">
            <i data-lucide="life-buoy"></i> Recovery Requests
        </a>
        <a href="/Registration-System/eventsys/codes/php/admin/volunteer/index.php" class="<?= strpos($_SERVER['REQUEST_URI'], '/volunteer/') !== false ? 'active' : '' ?>">
            <i data-lucide="users"></i> Volunteer Management
        </a>
        <a href="/Registration-System/eventsys/codes/php/admin/backup_restore.php">
            <i data-lucide="database"></i> Backup &amp; Restore
        </a>
        <a href="/Registration-System/eventsys/codes/php/auth/logout.php?return=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
            <i data-lucide="log-out"></i> Logout
        </a>
    </nav>
</aside>

<script>
(function() {
    const hamburgerBtn    = document.getElementById('hamburgerBtn');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');
    const sidebar         = document.getElementById('adminSidebar');
    const overlay         = document.getElementById('sidebarOverlay');
    const body            = document.body;

    function openSidebar() {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('active');
        body.style.overflow = 'hidden';
        hamburgerBtn.classList.add('active');
    }

    function closeSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
        body.style.overflow = '';
        hamburgerBtn.classList.remove('active');
    }

    function toggleSidebar() {
        sidebar.classList.contains('mobile-open') ? closeSidebar() : openSidebar();
    }

    if (hamburgerBtn)    hamburgerBtn.addEventListener('click', toggleSidebar);
    if (closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
    if (overlay)         overlay.addEventListener('click', closeSidebar);

    sidebar.querySelectorAll('nav a').forEach(link => {
        link.addEventListener('click', () => { if (window.innerWidth <= 1279) closeSidebar(); });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) closeSidebar();
    });

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { if (window.innerWidth >= 1280) closeSidebar(); }, 250);
    });
})();

function toggleDropdown(toggle) {
    const dropdownNav = toggle.closest('.dropdown-nav');
    dropdownNav.classList.toggle('open');
    // Add/remove class on nav so sibling links shrink when dropdown is open
    const nav = dropdownNav.closest('nav');
    if (nav) {
        nav.classList.toggle('dropdown-expanded', dropdownNav.classList.contains('open'));
    }
}

if (typeof lucide !== 'undefined') lucide.createIcons();
</script>