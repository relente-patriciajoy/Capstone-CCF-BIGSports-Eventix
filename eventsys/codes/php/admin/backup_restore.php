<?php
require_once('../../includes/session.php');
require_once('../../includes/role_protection.php');
requireRole('admin');

/**
 * ADMIN - Database Backup & Restore System
 * Allows admins to backup and restore the database
 */

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Admin access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

include('../../includes/db.php');

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];

// Configuration
$backup_dir = '../../backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

$message = "";
$error = "";

// Database credentials
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'event_registration';

// ===== HANDLE BACKUP =====
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['create_backup'])) {
    $filename_option = $_POST['filename_option'];
    $custom_filename = trim($_POST['custom_filename'] ?? '');
    
    // Generate filename
    if ($filename_option === 'date') {
        $filename = 'eventix_backup_' . date('Y-m-d_H-i-s') . '.sql';
    } else {
        if (empty($custom_filename)) {
            $error = "Please provide a custom filename.";
        } else {
            // Sanitize filename
            $custom_filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $custom_filename);
            $filename = $custom_filename . '_' . date('Y-m-d_H-i-s') . '.sql';
        }
    }
    
    if (empty($error)) {
        $backup_file = $backup_dir . $filename;
        
        // Execute mysqldump - Windows XAMPP
        // Use environment variable for DB credentials (Railway) or fall back to local defaults
        $db_host_cmd = getenv('DB_HOST') ?: 'localhost';
        $db_user_cmd = getenv('DB_USER') ?: 'root';
        $db_pass_cmd = getenv('DB_PASS') ?: '';
        $db_name_cmd = getenv('DB_NAME') ?: 'event_registration';
        $db_port_cmd = getenv('DB_PORT') ?: '3306';

        $pass_arg = !empty($db_pass_cmd) ? '--password=' . escapeshellarg($db_pass_cmd) : '';

        // Linux (Railway): just 'mysqldump', Windows XAMPP: full path
        $mysqldump = PHP_OS_FAMILY === 'Windows'
            ? '"C:\\xampp\\mysql\\bin\\mysqldump"'
            : 'mysqldump';

        $command = $mysqldump . ' --user=' . escapeshellarg($db_user_cmd)
            . ' --host=' . escapeshellarg($db_host_cmd)
            . ' --port=' . escapeshellarg($db_port_cmd)
            . ' ' . $pass_arg
            . ' ' . escapeshellarg($db_name_cmd)
            . ' > ' . escapeshellarg($backup_file);
        
        exec($command, $output, $result);
        
        if ($result === 0 && file_exists($backup_file) && filesize($backup_file) > 0) {
            // Log backup activity
            $log_stmt = $conn->prepare("INSERT INTO backup_log (admin_id, action, filename, file_size, created_at) VALUES (?, 'backup', ?, ?, NOW())");
            $file_size = filesize($backup_file);
            $log_stmt->bind_param("isi", $user_id, $filename, $file_size);
            $log_stmt->execute();
            $log_stmt->close();
            
            $message = "Database backup created successfully: {$filename}";
        } else {
            $error = "Failed to create backup. Please check database credentials and permissions.";
        }
    }
}

// ===== HANDLE RESTORE =====
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['restore_backup'])) {
    $restore_file = $_POST['restore_file'];
    $backup_path = $backup_dir . $restore_file;
    
    if (!file_exists($backup_path)) {
        $error = "Backup file not found.";
    } else {
        $db_host_cmd = getenv('DB_HOST') ?: 'localhost';
        $db_user_cmd = getenv('DB_USER') ?: 'root';
        $db_pass_cmd = getenv('DB_PASS') ?: '';
        $db_name_cmd = getenv('DB_NAME') ?: 'event_registration';
        $db_port_cmd = getenv('DB_PORT') ?: '3306';

        $pass_arg = !empty($db_pass_cmd) ? '--password=' . escapeshellarg($db_pass_cmd) : '';

        $mysql = PHP_OS_FAMILY === 'Windows'
            ? '"C:\\xampp\\mysql\\bin\\mysql"'
            : 'mysql';

        $command = $mysql . ' --user=' . escapeshellarg($db_user_cmd)
            . ' --host=' . escapeshellarg($db_host_cmd)
            . ' --port=' . escapeshellarg($db_port_cmd)
            . ' ' . $pass_arg
            . ' ' . escapeshellarg($db_name_cmd)
            . ' < ' . escapeshellarg($backup_path);
        
        exec($command, $output, $result);
        
        if ($result === 0) {
            // Log restore activity
            $log_stmt = $conn->prepare("INSERT INTO backup_log (admin_id, action, filename, created_at) VALUES (?, 'restore', ?, NOW())");
            $log_stmt->bind_param("is", $user_id, $restore_file);
            $log_stmt->execute();
            $log_stmt->close();
            
            $message = "Database restored successfully from: {$restore_file}";
        } else {
            $error = "Failed to restore database. Please check the backup file integrity.";
        }
    }
}

// ===== HANDLE DELETE BACKUP =====
if (isset($_GET['delete'])) {
    $delete_file = $_GET['delete'];
    $delete_path = $backup_dir . $delete_file;
    
    if (file_exists($delete_path)) {
        if (unlink($delete_path)) {
            // Log deletion
            $log_stmt = $conn->prepare("INSERT INTO backup_log (admin_id, action, filename, created_at) VALUES (?, 'delete', ?, NOW())");
            $log_stmt->bind_param("is", $user_id, $delete_file);
            $log_stmt->execute();
            $log_stmt->close();
            
            header("Location: backup_restore.php?status=deleted");
            exit();
        }
    }
}

// ===== HANDLE DOWNLOAD BACKUP =====
if (isset($_GET['download'])) {
    $download_file = $_GET['download'];
    $download_path = $backup_dir . $download_file;
    
    if (file_exists($download_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $download_file . '"');
        header('Content-Length: ' . filesize($download_path));
        readfile($download_path);
        exit();
    }
}

// Get list of existing backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'filename' => $file,
                'size' => filesize($backup_dir . $file),
                'date' => filemtime($backup_dir . $file)
            ];
        }
    }
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Get recent backup logs
$logs_query = $conn->query("
    SELECT bl.*, CONCAT(u.first_name, ' ', u.last_name) as admin_name 
    FROM backup_log bl 
    LEFT JOIN user u ON bl.admin_id = u.user_id 
    ORDER BY bl.created_at DESC 
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - Admin Panel</title>
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
            <h1>Backup & Restore</h1>
            <p>Create backups and restore database</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="management-alert success">
                <i data-lucide="check-circle"></i>
                <?= htmlspecialchars($message) ?>
                <span class="close-btn" onclick="this.parentElement.style.display='none';">×</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="management-alert error">
                <i data-lucide="alert-circle"></i>
                <?= htmlspecialchars($error) ?>
                <span class="close-btn" onclick="this.parentElement.style.display='none';">×</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'deleted'): ?>
            <div class="management-alert success">
                <i data-lucide="trash-2"></i>
                Backup file deleted successfully.
                <span class="close-btn" onclick="this.parentElement.style.display='none';">×</span>
            </div>
        <?php endif; ?>

        <div class="backup-grid">
            <!-- Create Backup -->
            <div class="backup-card">
                <h3>
                    <i data-lucide="database" style="width:24px;height:24px;"></i>
                    Create Backup
                </h3>
                <form method="POST" id="backupForm">
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="filename_option" value="date" checked onchange="toggleCustomFilename()">
                            <span>Use current date & time</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="filename_option" value="custom" onchange="toggleCustomFilename()">
                            <span>Custom filename</span>
                        </label>
                    </div>
                    <div class="form-group" id="customFilenameGroup" style="display:none;margin-top:12px;">
                        <label for="custom_filename">Custom Filename</label>
                        <input type="text" id="custom_filename" name="custom_filename"
                               placeholder="e.g., before_update, weekly_backup"
                               pattern="[a-zA-Z0-9_-]+">
                        <small style="color:#6b6b6b;font-size:0.85rem;margin-top:4px;display:block;">
                            Only letters, numbers, hyphens, and underscores allowed
                        </small>
                    </div>
                    <button type="submit" name="create_backup" class="btn btn-primary" style="width:100%;margin-top:16px;">
                        <i data-lucide="download"></i> Create Backup
                    </button>
                </form>
            </div>

            <!-- Restore from Backup -->
            <div class="backup-card">
                <h3>
                    <i data-lucide="upload" style="width:24px;height:24px;"></i>
                    Restore Database
                </h3>
                <div class="warning-box">
                    <i data-lucide="alert-triangle" style="width:20px;height:20px;color:#ffc107;flex-shrink:0;"></i>
                    <div>
                        <strong>Warning!</strong><br>
                        Restoring will replace all current data. Create a backup first!
                    </div>
                </div>
                <form method="POST" id="restoreForm" onsubmit="return confirmRestore()">
                    <div class="form-group">
                        <label for="restore_file">Select Backup File</label>
                        <select name="restore_file" id="restore_file" required>
                            <option value="">-- Choose a backup --</option>
                            <?php foreach ($backups as $backup): ?>
                                <option value="<?= htmlspecialchars($backup['filename']) ?>">
                                    <?= htmlspecialchars($backup['filename']) ?>
                                    (<?= date('M j, Y g:i A', $backup['date']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="restore_backup" class="btn btn-primary" style="width:100%;margin-top:16px;background:#f59e0b;">
                        <i data-lucide="rotate-ccw"></i> Restore Database
                    </button>
                </form>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Total Backups</h3>
                    <div class="stat-card-icon"><i data-lucide="archive" size="24"></i></div>
                </div>
                <div class="stat-card-value"><?= count($backups) ?></div>
                <div class="stat-card-change">Backup files available</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Total Size</h3>
                    <div class="stat-card-icon"><i data-lucide="hard-drive" size="24"></i></div>
                </div>
                <div class="stat-card-value">
                    <?php
                        $total_size = array_sum(array_column($backups, 'size'));
                        echo $total_size > 1048576 ? round($total_size/1048576,2).' MB' : round($total_size/1024,2).' KB';
                    ?>
                </div>
                <div class="stat-card-change">Disk space used</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-header">
                    <h3>Latest Backup</h3>
                    <div class="stat-card-icon"><i data-lucide="clock" size="24"></i></div>
                </div>
                <div class="stat-card-value" style="font-size:1.2rem;">
                    <?= !empty($backups) ? date('M j', $backups[0]['date']) : 'None' ?>
                </div>
                <div class="stat-card-change">
                    <?= !empty($backups) ? date('g:i A', $backups[0]['date']) : 'No backups yet' ?>
                </div>
            </div>
        </div>

        <!-- Available Backups -->
        <div class="management-card">
            <h2>Available Backups (<?= count($backups) ?>)</h2>
            <?php if (!empty($backups)): ?>
                <div style="margin-top:20px;">
                    <?php foreach ($backups as $backup): ?>
                        <div class="backup-file-item">
                            <div class="file-info">
                                <div class="file-name">
                                    <i data-lucide="file-text" style="width:16px;height:16px;vertical-align:middle;"></i>
                                    <?= htmlspecialchars($backup['filename']) ?>
                                </div>
                                <div class="file-meta">
                                    <?= date('F j, Y - g:i A', $backup['date']) ?> •
                                    <?= $backup['size'] > 1048576 ? round($backup['size']/1048576,2).' MB' : round($backup['size']/1024,2).' KB' ?>
                                </div>
                            </div>
                            <div class="file-actions">
                                <a href="?download=<?= urlencode($backup['filename']) ?>"
                                   class="btn btn-primary btn-sm" title="Download">
                                    <i data-lucide="download"></i>
                                    <span class="btn-label">Download</span>
                                </a>
                                <a href="?delete=<?= urlencode($backup['filename']) ?>"
                                   class="btn btn-delete btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this backup file?')"
                                   title="Delete">
                                    <i data-lucide="trash-2"></i>
                                    <span class="btn-label">Delete</span>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i data-lucide="inbox"></i>
                    <h3>No Backups Found</h3>
                    <p>Create your first backup to get started.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <?php if ($logs_query && $logs_query->num_rows > 0): ?>
            <div class="management-card">
                <h2>Recent Activity</h2>
                <div class="table-wrapper">
                    <table class="management-table">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Filename</th>
                                <th>Admin</th>
                                <th>Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($log = $logs_query->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-<?= $log['action']==='backup'?'success':($log['action']==='restore'?'warning':'danger') ?>">
                                            <?= ucfirst($log['action']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($log['filename']) ?></td>
                                    <td><?= htmlspecialchars($log['admin_name'] ?? 'Unknown') ?></td>
                                    <td><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        lucide.createIcons();

        function toggleCustomFilename() {
            const customOption = document.querySelector('input[name="filename_option"][value="custom"]');
            const customGroup  = document.getElementById('customFilenameGroup');
            const customInput  = document.getElementById('custom_filename');
            if (customOption.checked) { customGroup.style.display = 'block'; customInput.required = true; }
            else { customGroup.style.display = 'none'; customInput.required = false; }
        }

        function confirmRestore() {
            return confirm('⚠️ WARNING!\n\nThis will replace ALL current data with the backup.\n\nAre you absolutely sure you want to continue?');
        }

        setTimeout(() => {
            document.querySelectorAll('.management-alert').forEach(a => {
                a.style.opacity = '0';
                a.style.transform = 'translateY(-10px)';
                setTimeout(() => a.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>