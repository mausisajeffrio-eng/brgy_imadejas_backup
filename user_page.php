<?php
session_start();
require_once 'config.php';

// Require user to be logged in
requireLogin();

// If admin somehow lands here, send them to admin page
if (isAdmin()) {
    header("Location: admin_page.php");
    exit();
}

// Get user data
$user_name = $_SESSION['user_name'] ?? ($_SESSION['first_name'] . ' ' . ($_SESSION['last_name'] ?? '') ?? ($_SESSION['name'] ?? 'User'));
$user_email = $_SESSION['email'] ?? '';

// Handle simple profile updates and avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['profile_update'])) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? $user_email);
        $oldPassword = trim($_POST['old_password'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        
        $updateSuccess = false;
        $errorMsg = '';
        
        // Validate password if provided
        if (!empty($newPassword)) {
            if (empty($oldPassword)) {
                $errorMsg = 'Please enter your current password to change it';
            } elseif ($newPassword !== $confirmPassword) {
                $errorMsg = 'New passwords do not match';
            } elseif (strlen($newPassword) < 6) {
                $errorMsg = 'New password must be at least 6 characters';
            } else {
                // Verify old password from database
                if ($userId > 0) {
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $row = $result->fetch_assoc()) {
                        if (!password_verify($oldPassword, $row['password'])) {
                            $errorMsg = 'Current password is incorrect';
                        }
                    } else {
                        $errorMsg = 'User not found';
                    }
                    $stmt->close();
                }
            }
        }
        
        if (empty($errorMsg) && $userId > 0) {
            // Update email, first name, last name, and optionally password
            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, password = ? WHERE id = ?");
                $stmt->bind_param('ssssi', $email, $firstName, $lastName, $hashedPassword, $userId);
            } else {
                $stmt = $conn->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ? WHERE id = ?");
                $stmt->bind_param('sssi', $email, $firstName, $lastName, $userId);
            }
            
            if ($stmt->execute()) {
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['email'] = $email;
                $user_name = trim($firstName . ' ' . $lastName) ?: 'User';
                $_SESSION['profile_msg'] = !empty($newPassword) ? 'Profile and password updated successfully' : 'Profile updated successfully';
                $updateSuccess = true;
            } else {
                $errorMsg = 'Failed to update profile';
            }
            $stmt->close();
        } else {
            // Just update session if no database update
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['email'] = $email;
            $user_name = trim($firstName . ' ' . $lastName) ?: 'User';
        }
        
        if (!empty($errorMsg)) {
            $_SESSION['profile_error'] = $errorMsg;
        }
        
        header('Location: user_page.php#profile');
        exit;
    }
    // Remove avatar
    if (isset($_POST['avatar_remove'])) {
        $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        $old = $_SESSION['profile_image'] ?? '';
        $removed = false;
        if ($old && strpos($old, 'uploads/avatars/') === 0) {
            $oldPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $old);
            if (is_file($oldPath)) { @unlink($oldPath); $removed = true; }
        }
        if ($userId > 0) {
            if ($stmt = $conn->prepare("UPDATE users SET profile_image = NULL WHERE id = ?")) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
            }
        }
        unset($_SESSION['profile_image']);
        $_SESSION['avatar_msg'] = $removed ? 'Avatar removed' : 'Avatar cleared';
        header('Location: user_page.php#profile');
        exit;
    }

    // Upload avatar
    if (isset($_POST['avatar_upload']) && isset($_FILES['avatar'])) {
        $msg = '';
        $file = $_FILES['avatar'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg','jpeg','png','gif','webp'];
            $maxBytes = 2 * 1024 * 1024; // 2MB
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                $msg = 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp';
            } elseif ($file['size'] > $maxBytes) {
                $msg = 'File too large. Max size is 2MB';
            } else {
                $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR;
                if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
                $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
                $filename = 'avatar_user_' . ($userId ?: session_id()) . '_' . time() . '.' . $ext;
                $dest = $uploadDir . $filename;
                if (@move_uploaded_file($file['tmp_name'], $dest)) {
                    // Remove previously stored avatar file if any
                    $old = $_SESSION['profile_image'] ?? '';
                    if ($old && strpos($old, 'uploads/avatars/') === 0) {
                        $oldPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $old);
                        if (is_file($oldPath)) { @unlink($oldPath); }
                    }
                    $relativePath = 'uploads/avatars/' . $filename;
                    // Persist to DB (profile_image column)
                    if ($userId > 0) {
                        if ($stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?")) {
                            $stmt->bind_param('si', $relativePath, $userId);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }
                    $_SESSION['profile_image'] = $relativePath;
                    $msg = 'Avatar updated successfully';
                } else {
                    $msg = 'Failed to move uploaded file';
                }
            }
        } else {
            $msg = 'No file selected or upload error';
        }
        $_SESSION['avatar_msg'] = $msg;
        header('Location: user_page.php#profile');
        exit;
    }
}

// Basic stats (protect queries against failure)
$totEmployees = 0;
$presentToday = 0;
$absentToday = 0;
if ($conn) {
    $res = $conn->query("SELECT COUNT(*) AS c FROM emp_db");
    if ($res) { $totEmployees = (int)$res->fetch_assoc()['c']; }

    $res = $conn->query("SELECT COUNT(DISTINCT employee_id) AS c FROM attendance_db WHERE DATE(clock_in) = CURDATE()");
    if ($res) { $presentToday = (int)$res->fetch_assoc()['c']; }

    $absentToday = max(0, $totEmployees - $presentToday);
}

// profile image fallback
$profileImage = $_SESSION['profile_image'] ?? ('https://ui-avatars.com/api/?name=' . urlencode($user_name) . '&background=0D8ABC&color=fff');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="user_page.css">
    <!-- Line Awesome Icons -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/line-awesome@1.3.0/dist/line-awesome/css/line-awesome.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="user_page.js"></script>
</head>
<body>
    <div class="sidebar">
        <div class="side-header">
            <img src="imadejas_logo_no_bg2.png" alt="Logo" style="max-width: 80%; height: auto; max-height: 100px;">
        </div>
        <div class="side-menu">
            <ul>
                <li><a href="#dashboard" data-target="dashboard" class="active"><span class="las la-home"></span> Dashboard</a></li>
                <li><a href="#profile" data-target="profile"><span class="las la-user"></span> Profile</a></li>
                <li><a href="#settings" data-target="settings"><span class="las la-cog"></span> Settings</a></li>
                <li><a href="logout.php"><span class="las la-sign-out-alt"></span> Log Out</a></li>
            </ul>
        </div>
    </div>

    <div class="main">
        <header>
            <div>Welcome, <?php echo htmlspecialchars($user_name); ?></div>
            <div class="actions">
                <div class="user-menu" id="userMenu">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Avatar" class="user-avatar" />
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="caret">▾</span>
                    <div class="dropdown" id="userDropdown">
                        <a href="#profile" data-target="profile" id="headerGoProfile"><span class="las la-user" style="margin-right:6px;"></span> Profile</a>
                        <button type="button" id="headerThemeToggle"><span class="las la-adjust" style="margin-right:6px;"></span> Toggle Theme</button>
                        <a href="logout.php"><span class="las la-sign-out-alt" style="margin-right:6px;"></span> Log Out</a>
                    </div>
                </div>
            </div>
        </header>
        <div class="content">
            <div id="dashboard" class="section active">
                <?php
                $dashType = $_GET['dash_type'] ?? 'all';
                ?>
                <div class="dashboard">
                    <h2 class="dashboard-title">Dashboard Overview</h2>
                    <div class="stats-cards">
                        <div class="stat-card stat-card-success">
                            <div class="stat-icon">
                                <i class="las la-user-check"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Present Today</div>
                                <div class="stat-value"><?php echo $presentToday; ?></div>
                                <div class="stat-trend"><i class="las la-arrow-up"></i> Active</div>
                            </div>
                        </div>
                        <div class="stat-card stat-card-danger">
                            <div class="stat-icon">
                                <i class="las la-user-times"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Absent Today</div>
                                <div class="stat-value"><?php echo $absentToday; ?></div>
                                <div class="stat-trend"><i class="las la-minus"></i> Not Checked In</div>
                            </div>
                        </div>
                        <div class="stat-card stat-card-primary">
                            <div class="stat-icon">
                                <i class="las la-users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Total Employees</div>
                                <div class="stat-value"><?php echo $totEmployees; ?></div>
                                <div class="stat-trend"><i class="las la-database"></i> Registered</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dashboard-section">
                    <div class="section-header">
                        <div>
                            <h3 class="section-title">Today's Attendance</h3>
                            <p class="section-subtitle"><?php echo date('F j, Y'); ?></p>
                        </div>
                        <form method="get" class="filter-form">
                            <label class="filter-label">
                                <i class="las la-filter"></i> Filter by Type:
                            </label>
                            <select name="dash_type" class="filter-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $dashType==='all'?'selected':''; ?>>All Types</option>
                                <option value="daily" <?php echo $dashType==='daily'?'selected':''; ?>>Daily</option>
                                <option value="event" <?php echo $dashType==='event'?'selected':''; ?>>Events</option>
                                <option value="disaster" <?php echo $dashType==='disaster'?'selected':''; ?>>Disaster Response</option>
                            </select>
                        </form>
                    </div>
                </div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th><th>Employee ID</th><th>Name</th><th>Position</th><th>Clock In</th><th>Clock Out</th><th>Type</th><th>Details</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $typeWhere = '';$typeParam=null;
                        if ($dashType==='daily'){ $typeWhere=' AND type = ?'; $typeParam='daily'; }
                        elseif ($dashType==='event'){ $typeWhere=' AND type = ?'; $typeParam='event'; }
                        elseif ($dashType==='disaster'){ $typeWhere=' AND type = ?'; $typeParam='disaster_response'; }
                        $sql = 'SELECT id, employee_id, first_name, last_name, position, clock_in, clock_out, type, details, status FROM attendance_db WHERE DATE(clock_in)=CURDATE()' . $typeWhere . ' ORDER BY clock_in DESC';
                        if ($typeParam){ $stmt=$conn->prepare($sql); $stmt->bind_param('s',$typeParam); $stmt->execute(); $res=$stmt->get_result(); }
                        else { $res=$conn->query($sql); }
                        if ($res && $res->num_rows){
                            while($r=$res->fetch_assoc()){
                                $name = trim(($r['first_name']??'') . ' ' . ($r['last_name']??'')); if ($name===''){ $name=$r['employee_id']; }
                                $typeLabel = ($r['type']==='daily')?'Daily':(($r['type']==='disaster_response')?'Disaster Response':ucfirst($r['type']));
                                echo '<tr>'
                                    .'<td>'.htmlspecialchars($r['id']).'</td>'
                                    .'<td>'.htmlspecialchars($r['employee_id']).'</td>'
                                    .'<td>'.htmlspecialchars($name).'</td>'
                                    .'<td>'.htmlspecialchars($r['position']).'</td>'
                                    .'<td>'.($r['clock_in']?date('M d, Y h:i A', strtotime($r['clock_in'])):'').'</td>'
                                    .'<td>'.($r['clock_out']?date('M d, Y h:i A', strtotime($r['clock_out'])):'').'</td>'
                                    .'<td>'.htmlspecialchars($typeLabel).'</td>'
                                    .'<td>'.htmlspecialchars($r['details']).'</td>'
                                    .'<td>'.htmlspecialchars($r['status']).'</td>'
                                .'</tr>';
                            }
                        } else { echo '<tr><td colspan="9">No records for today.</td></tr>'; }
                        ?>
                    </tbody>
                </table>
            </div>

            <div id="profile" class="section">
                <div class="profile-header">
                    <h2 class="profile-title"><i class="las la-user-circle"></i> My Profile</h2>
                    <p class="profile-subtitle">Manage your account information and security settings</p>
                </div>
                
                <div class="profile-container">
                    <div class="profile-sidebar-card">
                        <div class="profile-avatar-section">
                            <div class="profile-avatar-wrapper">
                                <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Avatar" class="profile-avatar-img" />
                                <div class="profile-avatar-overlay">
                                    <i class="las la-camera"></i>
                                </div>
                            </div>
                            <div class="profile-user-info">
                                <h3 class="profile-user-name"><?php echo htmlspecialchars($user_name); ?></h3>
                                <p class="profile-user-role"><i class="las la-user"></i> User</p>
                                <p class="profile-user-email"><i class="las la-envelope"></i> <?php echo htmlspecialchars($user_email); ?></p>
                            </div>
                        </div>
                        
                        <form method="post" enctype="multipart/form-data" class="profile-avatar-form">
                            <div class="profile-file-input-wrapper">
                                <input type="file" name="avatar" accept="image/*" id="avatarInput" class="profile-file-input" />
                                <label for="avatarInput" class="profile-file-label">
                                    <i class="las la-cloud-upload-alt"></i> Choose Photo
                                </label>
                            </div>
                            <div class="profile-avatar-actions">
                                <button class="btn-profile btn-profile-primary" type="submit" name="avatar_upload" value="1">
                                    <i class="las la-check"></i> Upload
                                </button>
                                <button type="submit" name="avatar_remove" value="1" class="btn-profile btn-profile-danger">
                                    <i class="las la-trash"></i> Remove
                                </button>
                            </div>
                            <?php if (!empty($_SESSION['avatar_msg'])) { ?>
                                <div class="profile-avatar-msg">
                                    <i class="las la-check-circle"></i> <?php echo htmlspecialchars($_SESSION['avatar_msg']); ?>
                                </div>
                                <?php unset($_SESSION['avatar_msg']); ?>
                            <?php } ?>
                        </form>
                    </div>
                    
                    <div class="profile-main-card">
                        <div class="profile-card-header">
                            <h3 class="profile-card-title"><i class="las la-user-edit"></i> Profile Information</h3>
                        </div>
                        <div class="profile-card-body">
                                <?php if (!empty($_SESSION['profile_msg'])) { ?>
                                    <div style="padding:12px;background:#d1fae5;color:#065f46;border-radius:8px;margin-bottom:16px;border-left:4px solid #10b981;">
                                        <strong>✓</strong> <?php echo htmlspecialchars($_SESSION['profile_msg']); ?>
                                    </div>
                                    <?php unset($_SESSION['profile_msg']); ?>
                                <?php } ?>
                                <?php if (!empty($_SESSION['profile_error'])) { ?>
                                    <div style="padding:12px;background:#fee2e2;color:#991b1b;border-radius:8px;margin-bottom:16px;border-left:4px solid #ef4444;">
                                        <strong>✗</strong> <?php echo htmlspecialchars($_SESSION['profile_error']); ?>
                                    </div>
                                    <?php unset($_SESSION['profile_error']); ?>
                                <?php } ?>
                                <form method="post" class="profile-form">
                                    <input type="hidden" name="profile_update" value="1" />
                                    
                                    <div class="profile-form-section">
                                        <h4 class="profile-section-title"><i class="las la-id-card"></i> Basic Information</h4>
                                        <div class="profile-form-grid">
                                            <div class="profile-form-group">
                                                <label class="profile-label">
                                                    <i class="las la-user"></i> First Name
                                                </label>
                                                <input class="profile-input" type="text" name="first_name" value="<?php echo htmlspecialchars($_SESSION['first_name'] ?? ''); ?>" placeholder="Enter first name" />
                                            </div>
                                            <div class="profile-form-group">
                                                <label class="profile-label">
                                                    <i class="las la-user"></i> Last Name
                                                </label>
                                                <input class="profile-input" type="text" name="last_name" value="<?php echo htmlspecialchars($_SESSION['last_name'] ?? ''); ?>" placeholder="Enter last name" />
                                            </div>
                                            <div class="profile-form-group profile-form-group-full">
                                                <label class="profile-label">
                                                    <i class="las la-envelope"></i> Email Address
                                                </label>
                                                <input class="profile-input" type="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" placeholder="Enter email" required />
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="profile-form-section profile-password-section">
                                        <h4 class="profile-section-title"><i class="las la-lock"></i> Security Settings</h4>
                                        <div class="profile-form-grid">
                                            <div class="profile-form-group profile-form-group-full">
                                                <label class="profile-label">
                                                    <i class="las la-key"></i> Current Password
                                                </label>
                                                <input class="profile-input" type="password" name="old_password" placeholder="Enter current password" />
                                            </div>
                                            <div class="profile-form-group">
                                                <label class="profile-label">
                                                    <i class="las la-lock"></i> New Password
                                                </label>
                                                <input class="profile-input" type="password" name="new_password" placeholder="Enter new password" />
                                            </div>
                                            <div class="profile-form-group">
                                                <label class="profile-label">
                                                    <i class="las la-check-circle"></i> Confirm Password
                                                </label>
                                                <input class="profile-input" type="password" name="confirm_password" placeholder="Confirm new password" />
                                            </div>
                                        </div>
                                        <div class="profile-password-hint">
                                            <i class="las la-info-circle"></i> Leave password fields blank to keep your current password. Minimum 6 characters required.
                                        </div>
                                    </div>
                                    
                                    <div class="profile-form-actions">
                                        <button class="btn-profile-save" type="submit">
                                            <i class="las la-save"></i> Save Changes
                                        </button>
                                    </div>
                                </form>
                        </div>
                    </div>
                </div>
            </div>

            <div id="settings" class="section">
                <div class="card">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:600;">Theme</div>
                            <div style="color:#6b7280;">Switch between Light and Dark mode</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <label for="themeSwitch" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="themeSwitch" />
                                <span>Dark Mode</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
