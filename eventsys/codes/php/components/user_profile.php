<?php
session_start();
include('../../includes/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$errors  = [];

// ── Fetch current user ──
$stmt = $conn->prepare("
    SELECT first_name, middle_name, last_name, gender, email, phone,
           ccf_satellite, is_dgroup, dgroup_leader
    FROM user WHERE user_id = ?
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name       = trim($_POST['first_name']       ?? '');
    $middle_name      = trim($_POST['middle_name']       ?? '');
    $last_name        = trim($_POST['last_name']         ?? '');
    $gender           = trim($_POST['gender']            ?? '');
    $email            = trim($_POST['email']             ?? '');
    $phone            = trim($_POST['phone']             ?? '');
    $ccf_satellite    = trim($_POST['ccf_satellite']     ?? '');
    $is_dgroup        = isset($_POST['is_dgroup']) ? 1 : 0;
    $dgroup_leader    = $is_dgroup ? trim($_POST['dgroup_leader'] ?? '') : '';
    $new_password     = trim($_POST['new_password']      ?? '');
    $confirm_password = trim($_POST['confirm_password']  ?? '');

    if (!$first_name) $errors[] = 'First name is required.';
    if (!$last_name)  $errors[] = 'Last name is required.';
    if (!in_array($gender, ['male', 'female'])) $errors[] = 'Please select a gender.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'A valid email address is required.';
    if ($phone && !preg_match('/^09\d{9}$/', $phone))
        $errors[] = 'Phone must be 11 digits starting with 09.';
    if ($is_dgroup && !$dgroup_leader)
        $errors[] = 'Please enter your D-Group leader\'s name.';
    if ($new_password && strlen($new_password) < 8)
        $errors[] = 'Password must be at least 8 characters.';
    if ($new_password && $new_password !== $confirm_password)
        $errors[] = 'Passwords do not match.';

    // Check if email is already used by another account
    if ($email && empty($errors)) {
        $chk = $conn->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
        $chk->bind_param('si', $email, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0)
            $errors[] = 'That email is already used by another account.';
        $chk->close();
    }

    if (empty($errors)) {
        if ($new_password) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                UPDATE user SET first_name=?, middle_name=?, last_name=?, gender=?,
                email=?, phone=?, ccf_satellite=?, is_dgroup=?, dgroup_leader=?, password=?
                WHERE user_id=?
            ");
            $stmt->bind_param('ssssssssissi',
                $first_name, $middle_name, $last_name, $gender,
                $email, $phone, $ccf_satellite, $is_dgroup, $dgroup_leader, $hashed, $user_id
            );
        } else {
            $stmt = $conn->prepare("
                UPDATE user SET first_name=?, middle_name=?, last_name=?, gender=?,
                email=?, phone=?, ccf_satellite=?, is_dgroup=?, dgroup_leader=?
                WHERE user_id=?
            ");
            $stmt->bind_param('ssssssissi',
                $first_name, $middle_name, $last_name, $gender,
                $email, $phone, $ccf_satellite, $is_dgroup, $dgroup_leader, $user_id
            );
        }

        if ($stmt->execute()) {
            $success = 'Profile updated successfully!';
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name']  = $last_name;
            $user = array_merge($user, compact(
                'first_name','middle_name','last_name','gender','email',
                'phone','ccf_satellite','is_dgroup','dgroup_leader'
            ));
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }
}

$initials = strtoupper(
    substr($user['first_name'] ?? 'U', 0, 1) .
    substr($user['last_name']  ?? '',  0, 1)
);

$role_raw    = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'user';
$role_labels = [
    'admin'      => 'Admin',
    'event_head' => 'Event Head',
    'user'       => 'Member',
    'attendee'   => 'Member',
];
$role_label = $role_labels[$role_raw] ?? ucfirst($role_raw);

$ccf_satellites = [
    'CCF Alabang', 'CCF Alicia', 'CCF Angeles', 'CCF Angono',
    'CCF Antipolo', 'CCF Bacolod', 'CCF Bacoor', 'CCF Baguio',
    'CCF Baliuag', 'CCF Bataan', 'CCF Batangas City', 'CCF Bay Area',
    'CCF BGC', 'CCF Binangonan', 'CCF Butuan', 'CCF Cabanatuan',
    'CCF Calamba', 'CCF Cauayan', 'CCF CDO Downtown', 'CCF CDO Uptown',
    'CCF Cebu', 'CCF Commonwealth', 'CCF Dagupan', 'CCF Davao',
    'CCF Dipolog', 'CCF Dumaguete', 'CCF East Ortigas', 'CCF Eastwood',
    'CCF Fairview', 'CCF Feliz', 'CCF Gateway', 'CCF Gen. Trias',
    'CCF General Santos', 'CCF Grace Park', 'CCF Iligan', 'CCF Iloilo',
    'CCF Imus', 'CCF Katipunan', 'CCF Kawit', 'CCF La Trinidad',
    'CCF La Union', 'CCF Las Pinas', 'CCF Legazpi', 'CCF Lipa',
    'CCF Lower Antipolo', 'CCF Lucena', 'CCF Makati', 'CCF Malaybalay',
    'CCF Malolos', 'CCF Manila', 'CCF Manolo Fortich', 'CCF Marikina',
    'CCF Marilao', 'CCF Matina', 'CCF Molino', 'CCF Muntinlupa',
    'CCF Naga', 'CCF North Edsa', 'CCF Ozamiz', 'CCF Paranaque',
    'CCF Puerto Princesa', 'CCF Robinsons Place Antipolo', 'CCF Roxas',
    'CCF Salitran', 'CCF San Fernando', 'CCF San Jose Del Monte',
    'CCF San Pedro', 'CCF San Simon', 'CCF Sandoval', 'CCF Santa Maria',
    'CCF Santa Rosa', 'CCF Santiago', 'CCF Silang', 'CCF Subic Bay',
    'CCF Tagaytay', 'CCF Tagum', 'CCF Tanay', 'CCF Tandang Sora',
    'CCF Tarlac', 'CCF Taytay', 'CCF Tuguegarao', 'CCF Urdaneta',
    'CCF Valencia', 'CCF Valenzuela',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Eventix</title>
    <link rel="icon" type="image/png" href="../../assets/ccf-b1g-favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Shared layout + sidebar styles -->
    <link rel="stylesheet" href="../../css/management.css">
    <!-- Profile-specific styles -->
    <link rel="stylesheet" href="../../css/user_profile.css">
</head>
<body>

<?php
// Include correct sidebar based on role
$role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'user';
if ($role === 'admin') {
    include('../admin/admin_sidebar.php');
} else {
    include('sidebar.php');
}
?>

<!-- Uses same layout wrapper as all other pages -->
<div class="management-layout">
    <div class="management-content">
        <div class="profile-page">

            <!-- Hero banner -->
            <div class="profile-hero">
                <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                <div class="profile-hero-info">
                    <div class="profile-hero-name">
                        <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                    </div>
                    <div class="profile-hero-email">
                        <?= htmlspecialchars($user['email'] ?? '') ?>
                    </div>
                    <span class="profile-hero-badge">
                        <i data-lucide="shield-check"></i>
                        <?= htmlspecialchars($role_label) ?>
                    </span>
                </div>
            </div>

            <?php if ($success): ?>
            <div class="profile-alert profile-alert-success">
                <i data-lucide="check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="profile-alert profile-alert-error">
                <i data-lucide="alert-circle"></i>
                <div>
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">

                <!-- ── Personal Information ── -->
                <div class="profile-card">
                    <div class="profile-card-title">
                        <i data-lucide="user"></i>
                        Personal Information
                    </div>

                    <div class="profile-form-row three">
                        <div class="profile-form-group">
                            <label>First Name <span class="req">*</span></label>
                            <input type="text" name="first_name"
                                   value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                                   placeholder="Juan" required>
                        </div>
                        <div class="profile-form-group">
                            <label>Middle Name</label>
                            <input type="text" name="middle_name"
                                   value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>"
                                   placeholder="Dela">
                        </div>
                        <div class="profile-form-group">
                            <label>Last Name <span class="req">*</span></label>
                            <input type="text" name="last_name"
                                   value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                                   placeholder="Cruz" required>
                        </div>
                    </div>

                    <div class="profile-form-row full">
                        <div class="profile-form-group">
                            <label>Gender <span class="req">*</span></label>
                            <div class="gender-toggle">
                                <div class="gender-option">
                                    <input type="radio" name="gender" id="g_male" value="male"
                                        <?= ($user['gender'] ?? '') === 'male' ? 'checked' : '' ?>>
                                    <label for="g_male">
                                        <i data-lucide="user"></i> Male
                                    </label>
                                </div>
                                <div class="gender-option">
                                    <input type="radio" name="gender" id="g_female" value="female"
                                        <?= ($user['gender'] ?? '') === 'female' ? 'checked' : '' ?>>
                                    <label for="g_female">
                                        <i data-lucide="user"></i> Female
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-form-row">
                        <div class="profile-form-group">
                            <label>Email Address <span class="req">*</span></label>
                            <input type="email" name="email"
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                   placeholder="juan@example.com" required>
                        </div>
                        <div class="profile-form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone"
                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                   placeholder="09123456789" maxlength="11">
                            <span class="field-hint">11 digits starting with 09</span>
                        </div>
                    </div>
                </div>

                <!-- ── Church & Community ── -->
                <div class="profile-card">
                    <div class="profile-card-title">
                        <i data-lucide="map-pin"></i>
                        Church &amp; Community
                    </div>

                    <div class="profile-form-row full">
                        <div class="profile-form-group">
                            <label>CCF Satellite <span class="req">*</span></label>
                            <select name="ccf_satellite" required>
                                <option value="" disabled
                                    <?= empty($user['ccf_satellite']) ? 'selected' : '' ?>>
                                    — Select your satellite —
                                </option>
                                <?php foreach ($ccf_satellites as $sat): ?>
                                <option value="<?= $sat ?>"
                                    <?= ($user['ccf_satellite'] ?? '') === $sat ? 'selected' : '' ?>>
                                    <?= $sat ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <label class="dgroup-toggle <?= !empty($user['is_dgroup']) ? 'checked' : '' ?>"
                           for="is_dgroup" id="dgroupToggleLabel">
                        <input type="checkbox" id="is_dgroup" name="is_dgroup"
                               <?= !empty($user['is_dgroup']) ? 'checked' : '' ?>
                               onchange="toggleDgroup(this)">
                        <div>
                            <div class="dgroup-label">I am part of a D-Group</div>
                            <div class="dgroup-sublabel">Discipleship Group within CCF</div>
                        </div>
                    </label>

                    <div class="profile-form-row full" id="dgroupLeaderField"
                         <?= empty($user['is_dgroup']) ? 'hidden' : '' ?>>
                        <div class="profile-form-group">
                            <label>D-Group Leader Name <span class="req">*</span></label>
                            <input type="text" name="dgroup_leader" id="dgroup_leader"
                                   value="<?= htmlspecialchars($user['dgroup_leader'] ?? '') ?>"
                                   placeholder="Full name of your D-Group leader"
                                   <?= empty($user['is_dgroup']) ? 'disabled' : '' ?>>
                        </div>
                    </div>
                </div>

                <!-- ── Change Password ── -->
                <div class="profile-card">
                    <div class="profile-card-title">
                        <i data-lucide="lock"></i>
                        Change Password
                        <span class="title-hint">Leave blank to keep current</span>
                    </div>

                    <div class="profile-form-row">
                        <div class="profile-form-group">
                            <label>New Password</label>
                            <div class="input-eye">
                                <input type="password" name="new_password" id="newPass"
                                       placeholder="Min. 8 characters">
                                <button type="button" class="eye-btn"
                                        onclick="toggleEye('newPass', this)">
                                    <i data-lucide="eye"></i>
                                </button>
                            </div>
                            <span class="field-hint">Min. 8 chars, 1 uppercase, 1 number</span>
                        </div>
                        <div class="profile-form-group">
                            <label>Confirm New Password</label>
                            <div class="input-eye">
                                <input type="password" name="confirm_password" id="confirmPass"
                                       placeholder="Re-enter new password">
                                <button type="button" class="eye-btn"
                                        onclick="toggleEye('confirmPass', this)">
                                    <i data-lucide="eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="profile-save-btn">
                    <i data-lucide="save"></i>
                    Save Changes
                </button>

            </form>

        </div><!-- end profile-page -->
    </div><!-- end management-content -->
</div><!-- end management-layout -->

<script>
lucide.createIcons();

function toggleDgroup(cb) {
    const field = document.getElementById('dgroupLeaderField');
    const input = document.getElementById('dgroup_leader');
    const label = document.getElementById('dgroupToggleLabel');
    if (cb.checked) {
        field.removeAttribute('hidden');
        input.disabled = false;
        label.classList.add('checked');
        input.focus();
    } else {
        field.setAttribute('hidden', '');
        input.disabled = true;
        input.value = '';
        label.classList.remove('checked');
    }
}

function toggleEye(id, btn) {
    const el     = document.getElementById(id);
    const isPass = el.type === 'password';
    el.type = isPass ? 'text' : 'password';
    btn.innerHTML = isPass
        ? '<i data-lucide="eye-off"></i>'
        : '<i data-lucide="eye"></i>';
    lucide.createIcons();
}
</script>
</body>
</html>