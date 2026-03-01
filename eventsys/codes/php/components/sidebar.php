<?php
/**
 * Dynamic Sidebar Component with Hamburger Menu
 * Place in: components/sidebar.php
 *
 * IMPORTANT: The parent file MUST define $role before including this file
 * Example in parent file:
 *   $role = 'user'; // or 'event_head'
 *   include('../../components/sidebar.php');
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Determine sidebar class based on role
$sidebar_class = ($role === 'event_head') ? 'eventhead-sidebar' : 'participant-sidebar';
$sidebar_id = ($role === 'event_head') ? 'eventheadSidebar' : 'participantSidebar';
?>

<!-- Mobile Header - MINIMALIST BLACK HAMBURGER ONLY -->
<div class="mobile-header">
    <button class="hamburger-menu" id="hamburgerBtn" aria-label="Toggle menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
</div>

<!-- Overlay for mobile menu -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar <?= $sidebar_class ?>" id="<?= $sidebar_id ?>">
  <h2 class="logo">Eventix</h2>

  <button class="sidebar-close" id="closeSidebarBtn" aria-label="Close menu">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
  </button>

  <nav>
    <!-- Common Links (All Users) -->
    <a href="../dashboard/home.php" class="<?= $current_page === 'home.php' ? 'active' : '' ?>">
      <i data-lucide="home"></i>
      Home
    </a>

    <a href="../dashboard/events.php" class="<?= $current_page === 'events.php' ? 'active' : '' ?>">
      <i data-lucide="calendar"></i>
      Browse Events
    </a>

    <a href="../dashboard/my_events.php" class="<?= $current_page === 'my_events.php' ? 'active' : '' ?>">
      <i data-lucide="user-check"></i>
      My Events
    </a>

    <a href="../dashboard/attendance.php" class="<?= $current_page === 'attendance.php' ? 'active' : '' ?>">
      <i data-lucide="clipboard-check"></i>
      Attendance
    </a>

    <a href="../calendar/calendar.php" class="<?= $current_page === 'calendar.php' ? 'active' : '' ?>">
      <i data-lucide="calendar-days"></i>
      Event Calendar
    </a>

    <?php if (isset($role) && $role === 'event_head'): ?>
    <!-- Event Head Management Hub -->
    <a href="../event/manage_events.php" class="<?= in_array($current_page, [
        'manage_events.php',
        'scan_qr.php',
        'view_attendance.php',
        'reports.php',
        'participant_engagement.php',
        'inactive_tracking.php'
    ]) ? 'active' : '' ?>">
        <i data-lucide="layout-dashboard"></i>
        Event Management
    </a>
    <?php endif; ?>

    <!-- Add back to hub link on sub-pages -->
    <?php if (isset($role) && $role === 'event_head' && in_array($current_page, ['scan_qr.php', 'view_attendance.php', 'reports.php', 'participant_engagement.php', 'inactive_tracking.php'])): ?>
    <div style="padding: 0 16px; margin-top: 8px;">
      <a href="../event/manage_events.php" class="back-to-hub-link">
        <i data-lucide="arrow-left"></i>
        Back to Hub
      </a>
    </div>
    <?php endif; ?>

    <!-- User Info - Minimal Text Only -->
    <div class="user-info-minimal">
      <p class="user-name-text"><?= htmlspecialchars($_SESSION['first_name'] ?? 'User') ?> <?= htmlspecialchars($_SESSION['last_name'] ?? '') ?></p>
      <p class="user-role-text"><?= $role === 'event_head' ? 'Event Head' : 'Participant' ?></p>
    </div>

    <a href="../auth/logout.php?return=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
      <i data-lucide="log-out"></i>
      Logout
    </a>
  </nav>
</aside>

<script>
// Hamburger Menu Functionality
(function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const closeSidebarBtn = document.getElementById('closeSidebarBtn');
    const sidebar = document.getElementById('<?= $sidebar_id ?>');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;

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
        if (sidebar.classList.contains('mobile-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', toggleSidebar);
    }

    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', closeSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar when clicking any navigation link (mobile only)
    const navLinks = sidebar.querySelectorAll('nav a');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            closeSidebar();
        }
    });

    // Close sidebar when window is resized to desktop
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        }, 250);
    });
})();

// Initialize Lucide icons
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>