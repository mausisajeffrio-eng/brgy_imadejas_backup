<?php
session_start();
require_once 'config.php';

// Handle AJAX request for attendance records
if (isset($_GET['action']) && $_GET['action'] === 'get_attendance_records') {
    header('Content-Type: application/json');
    $records = [];
    $resAtt = $conn->query("SELECT id, employee_id, first_name, last_name, position, type, details, status, clock_in, clock_out, created_at 
                          FROM attendance_db 
                          ORDER BY created_at DESC");
    if ($resAtt && $resAtt->num_rows) {
        while ($row = $resAtt->fetch_assoc()) {
            $typeLower = strtolower($row['type']);
            $records[] = [
                'id' => $row['id'],
                'employee_id' => $row['employee_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'position' => $row['position'],
                'clock_in' => $row['clock_in'] ? date('M d, Y h:i A', strtotime($row['clock_in'])) : '',
                'clock_out' => $row['clock_out'] ? date('M d, Y h:i A', strtotime($row['clock_out'])) : '',
                'type' => ($typeLower === 'daily') ? 'Daily' : (($typeLower === 'disaster_response') ? 'Disaster Response' : ucfirst(str_replace('_', ' ', $typeLower))),
                'details' => ($typeLower === 'daily') ? 'Regular Check-in' : $row['details'],
                'status' => $row['status']
            ];
        }
    }
    echo json_encode(['success' => true, 'data' => $records]);
    exit;
}

// Handle AJAX request for dashboard stats
if (isset($_GET['action']) && $_GET['action'] === 'get_dashboard_stats') {
    header('Content-Type: application/json');
    
    // Get present today count
    $presentStmt = $conn->prepare("SELECT COUNT(DISTINCT employee_id) AS cnt FROM attendance_db WHERE DATE(clock_in) = CURDATE()");
    $presentStmt->execute();
    $presentToday = (int)$presentStmt->get_result()->fetch_assoc()['cnt'];
    
    // Get total employees
    $totStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM emp_db");
    $totStmt->execute();
    $totEmployees = (int)$totStmt->get_result()->fetch_assoc()['cnt'];
    
    // Calculate absent today
    $absentToday = $totEmployees - $presentToday;
    
    echo json_encode([
        'success' => true,
        'present' => $presentToday,
        'absent' => $absentToday,
        'total' => $totEmployees
    ]);
    exit;
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    // If not admin, redirect to login page
    $_SESSION['login_error'] = 'You must be an admin to access this page';
    header("Location: index.php");
    exit();
}

// Get user data
$user_name = $_SESSION['user_name'] ?? ($_SESSION['name'] ?? 'User');
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
                $user_name = trim($firstName . ' ' . $lastName) ?: 'Admin';
                $_SESSION['profile_msg'] = !empty($newPassword) ? 'Profile and password updated successfully' : 'Profile updated successfully';
            } else {
                $errorMsg = 'Failed to update profile';
            }
            $stmt->close();
        } else {
            // Just update session if no database update
            $_SESSION['first_name'] = $firstName;
            $_SESSION['last_name'] = $lastName;
            $_SESSION['email'] = $email;
            $user_name = trim($firstName . ' ' . $lastName) ?: 'Admin';
        }
        
        if (!empty($errorMsg)) {
            $_SESSION['profile_error'] = $errorMsg;
        }
        
        header('Location: admin_page.php#profile');
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
        header('Location: admin_page.php#profile');
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
        header('Location: admin_page.php#profile');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance System - Admin</title>
    <link rel="stylesheet" href="admin_page.css">
    <link rel="stylesheet" href="admin_page_refinements.css?v=10">
    <!-- Line Awesome Icons -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/line-awesome@1.3.0/dist/line-awesome/css/line-awesome.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- jQuery and DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Admin Page JavaScript -->
    <script defer src="admin_page.js?v=6"></script>
</head>
<body>
    <div class="sidebar">
        <div class="side-header">
            <img src="imadejas_logo_no_bg2.png" alt="Logo" class="logo-static" style="max-width: 90%; height: auto; max-height: 150px; animation: none !important; transform: none !important; transition: none !important;">
        </div>
        <div class="side-menu">
            <ul>
                <li><a href="#dashboard" data-target="dashboard" class="active"><span class="las la-home"></span> Dashboard</a></li>
                <li><a href="#attendance" data-target="attendance"><span class="las la-qrcode"></span> Attendance</a></li>
                <li><a href="#employees" data-target="employees"><span class="las la-users"></span> Employees</a></li>
                <li><a href="#reports" data-target="reports"><span class="las la-chart-bar"></span> Reports</a></li>
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
                    <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? 'https://ui-avatars.com/api/?name='.urlencode($user_name)); ?>" alt="Avatar" class="user-avatar" />
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
                $totEmployees = (int)$conn->query("SELECT COUNT(*) AS c FROM emp_db")->fetch_assoc()['c'];
                $presentToday = (int)$conn->query("SELECT COUNT(DISTINCT employee_id) AS c FROM attendance_db WHERE DATE(clock_in) = CURDATE()")->fetch_assoc()['c'];
                $absentToday = max(0, $totEmployees - $presentToday);
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

            <div id="attendance" class="section">
                <div class="card attendance-toolbar">
                    <strong class="attendance-page-title"><span class="las la-qrcode"></span> Attendance</strong>
                    <div class="attendance-mode-switch">
                        <button class="btn" id="tabBtn-daily" onclick="showPage('daily')">Daily</button>
                        <button class="btn" id="tabBtn-event" onclick="showPage('event')">Event</button>
                        <button class="btn" id="tabBtn-disaster" onclick="showPage('disaster')">Disaster</button>
                    </div>
                </div>
                <div class="attendance-layout">
                    <div class="attendance-scanner-panel">
                        <div id="daily" class="page active">
                            <h3>Daily Attendance</h3>
                            <form action="attendance.php" method="post" style="width:100%;max-width:380px;margin-bottom:15px;">
                                <input type="hidden" name="type" value="daily">
                                <div>
                                    <button type="button" id="startCameraDaily" class="btn primary" style="width:100%;margin-bottom:10px;">
                                        <span class="las la-camera"></span> Start Camera
                                    </button>
                                    <label>QR Code Data:</label>
                                    <input type="text" name="text" id="text-daily" readonly placeholder="Click Start Camera to begin scanning" class="search" style="width:100%;margin-bottom:10px;">
                                    <label>Details:</label>
                                    <input type="text" name="details" value="Regular Check-in" class="search" style="width:100%;" readonly>
                                </div>
                            </form>
                            <div class="camera-container" id="camera-container-daily" style="display:none;">
                                <video id="preview-daily" width="100%" style="max-height: 100%;"></video>
                            </div>
                            <div id="status-daily" class="camera-status"></div>
                        </div>
                        <div id="event" class="page">
                            <h3>Event Attendance</h3>
                            <form action="attendance.php" method="post" style="width:100%;max-width:380px;margin-bottom:15px;">
                                <input type="hidden" name="type" value="event">
                                <div>
                                    <label for="eventDetails">Event Details: <span style="color:red;">*</span></label>
                                    <input type="text" name="details" id="eventDetails" placeholder="Enter event name or details" class="search" style="width:100%;margin-bottom:10px;" required>
                                    <button type="button" id="startCameraEvent" class="btn primary" style="width:100%;margin-bottom:10px;">
                                        <span class="las la-camera"></span> Start Camera
                                    </button>
                                    <label>QR Code Data:</label>
                                    <input type="text" name="text" id="text-event" readonly placeholder="Enter details first, then start camera" class="search" style="width:100%;">
                                </div>
                            </form>
                            <div class="camera-container" id="camera-container-event" style="display:none;">
                                <video id="preview-event" width="100%" style="max-height: 100%;"></video>
                            </div>
                            <div id="status-event" class="camera-status"></div>
                        </div>
                        <div id="disaster" class="page">
                            <h3>Disaster Attendance</h3>
                            <form action="attendance.php" method="post" style="width:100%;max-width:380px;margin-bottom:15px;">
                                <input type="hidden" name="type" value="disaster">
                                <div>
                                    <label for="disasterDetails">Disaster Details: <span style="color:red;">*</span></label>
                                    <input type="text" name="details" id="disasterDetails" placeholder="Enter disaster type or details" class="search" style="width:100%;margin-bottom:10px;" required>
                                    <button type="button" id="startCameraDisaster" class="btn primary" style="width:100%;margin-bottom:10px;">
                                        <span class="las la-camera"></span> Start Camera
                                    </button>
                                    <label>QR Code Data:</label>
                                    <input type="text" name="text" id="text-disaster" readonly placeholder="Enter details first, then start camera" class="search" style="width:100%;">
                                </div>
                            </form>
                            <div class="camera-container" id="camera-container-disaster" style="display:none;">
                                <video id="preview-disaster" width="100%" style="max-height: 100%;"></video>
                            </div>
                            <div id="status-disaster" class="camera-status"></div>
                        </div>
                    </div>
                    <div class="card attendance-records-card">
                        <div class="attendance-records-heading">
                            <div>
                                <h3>Attendance Records</h3>
                                <p>Browse and search recorded check-ins</p>
                            </div>
                            <span class="attendance-records-icon las la-clipboard-list" aria-hidden="true"></span>
                        </div>
                        <div class="attendance-table-scroll">
                            <table id="attendance-records" class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Employee ID</th>
                                        <th>First Name</th>
                                        <th>Last Name</th>
                                        <th>Position</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Type</th>
                                        <th>Details</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $resAtt = $conn->query("SELECT id, employee_id, first_name, last_name, position, type, details, status, clock_in, clock_out, created_at FROM attendance_db ORDER BY created_at DESC");
                                    if ($resAtt && $resAtt->num_rows) {
                                        while ($row = $resAtt->fetch_assoc()) {
                                            echo '<tr>'
                                                .'<td>'.htmlspecialchars($row['id']).'</td>'
                                                .'<td>'.htmlspecialchars($row['employee_id']).'</td>'
                                                .'<td>'.htmlspecialchars($row['first_name']).'</td>'
                                                .'<td>'.htmlspecialchars($row['last_name']).'</td>'
                                                .'<td>'.htmlspecialchars($row['position']).'</td>'
                                                .'<td>'.($row['clock_in']?date('M d, Y h:i A', strtotime($row['clock_in'])):'').'</td>'
                                                .'<td>'.($row['clock_out']?date('M d, Y h:i A', strtotime($row['clock_out'])):'').'</td>'
                                                .'<td>'.(strtolower($row['type']) === 'daily' ? 'Daily' : htmlspecialchars($row['type'])).'</td>'
                                                .'<td>'.htmlspecialchars($row['details']).'</td>'
                                                .'<td>'.htmlspecialchars($row['status']).'</td>'
                                            .'</tr>';
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/webrtc-adapter/3.3.3/adapter.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/vue/2.1.10/vue.min.js"></script>
                <script src="https://cdn.jsdelivr.net/gh/schmich/instascan-builds@master/instascan.min.js"></script>
            </div>

            <div id="employees" class="section">
                <div class="card" style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="margin:0;">Employee Management</h3>
                    <div class="d-flex align-items-center" style="gap: 10px;">
                        <a class="btn primary" href="/jowawbackup/create.php" style="display: inline-flex; align-items: center; gap: 6px;">
                            <i class="las la-user-plus"></i> Add Employee
                        </a>
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#qrCodeModal" 
                                style="display: inline-flex; align-items: center; gap: 6px; background-color: #0dcaf0; border: none; padding: 8px 16px; border-radius: 6px; color: white; font-weight: 500; transition: all 0.3s ease;">
                            <i class="bi bi-qr-code-scan"></i> QR Format
                        </button>
                    </div>
                </div>
                
                <!-- QR Code Format Modal -->
                <div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true" style="display: none;">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.15);">
                            <div class="modal-header" style="background-color: #0d6efd; color: white; border: none; padding: 1.2rem 1.5rem;">
                                <h5 class="modal-title d-flex align-items-center gap-2" id="qrCodeModalLabel" style="font-weight: 600; font-size: 1.25rem;">
                                    <i class="bi bi-qr-code-scan" style="font-size: 1.5rem;"></i>
                                    <span>QR Code Format</span>
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="filter: brightness(0) invert(1);"></button>
                            </div>
                            <div class="modal-body" style="padding: 1.5rem;">
                                <div class="alert alert-info" style="background-color: #e7f5ff; border: 1px solid #b3e0ff; color: #0c5460; border-radius: 8px; padding: 1rem;">
                                    <div class="d-flex align-items-start gap-3">
                                        <i class="bi bi-info-circle-fill" style="font-size: 1.25rem; margin-top: 2px;"></i>
                                        <div>
                                            <h6 class="alert-heading" style="font-weight: 600; margin: 0 0 8px 0; color: #0c5460;">QR Code Format</h6>
                                            <p style="margin: 0 0 12px 0; line-height: 1.5;">
                                                When creating QR codes for employees, use this format:
                                            </p>
                                            <div class="bg-dark text-light p-3 rounded-2 mb-3" style="font-family: 'Courier New', monospace; font-size: 0.95rem; word-break: break-all;">
                                                EMP-XXX|First Name|Last Name|Position
                                            </div>
                                            <p class="mb-2" style="font-weight: 500;">Example:</p>
                                            <div class="p-3 rounded-2" style="font-family: 'Courier New', monospace; font-size: 0.95rem; word-break: break-all; color: inherit;">
                                                EMP-001|John|Doe|Tanod
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <h6 class="mb-3" style="font-weight: 600; color: #495057; font-size: 1rem;">Important Notes:</h6>
                                    <ul class="list-group" style="border-radius: 8px; overflow: hidden;">
                                        <li class="list-group-item d-flex align-items-center" style="padding: 0.75rem 1.25rem; border: 1px solid rgba(0,0,0,.125);">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span>Employee ID must start with <code class="bg-light p-1 rounded">EMP-</code></span>
                                        </li>
                                        <li class="list-group-item d-flex align-items-center" style="padding: 0.75rem 1.25rem; border-left: 1px solid rgba(0,0,0,.125); border-right: 1px solid rgba(0,0,0,.125);">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span>Use the pipe symbol <code class="bg-light p-1 rounded">|</code> as a separator</span>
                                        </li>
                                        <li class="list-group-item d-flex align-items-center" style="padding: 0.75rem 1.25rem; border: 1px solid rgba(0,0,0,.125); border-top: none;">
                                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                                            <span>Follow the order: <span class="text-nowrap">Employee ID → First Name → Last Name → Position</span></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1rem 1.5rem; background-color: #f8f9fa;">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="background-color: #6c757d; border: none; padding: 0.5rem 1.25rem; border-radius: 6px; font-weight: 500; transition: all 0.2s ease;">
                                    <i class="bi bi-x-lg me-1"></i> Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <table id="employeeTable" class="display" style="width:100%">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Employee ID</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Position</th>
                                <th>Age <i class="las la-sort"></i></th>
                                <th>Phone <i class="las la-phone"></i></th>
                                <th>Status</th>
                                <th>Actions <i class="las la-cog"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $resEmp = $conn->query("SELECT * FROM emp_db ORDER BY id DESC");
                            if ($resEmp && $resEmp->num_rows) {
                                while ($row = $resEmp->fetch_assoc()) {
                                    echo '<tr>'
                                        .'<td>'.htmlspecialchars($row['id']).'</td>'
                                        .'<td>'.htmlspecialchars($row['employee_id']).'</td>'
                                        .'<td>'.htmlspecialchars($row['first_name']).'</td>'
                                        .'<td>'.htmlspecialchars($row['last_name']).'</td>'
                                        .'<td>'.htmlspecialchars($row['position']).'</td>'
                                        .'<td>'.htmlspecialchars($row['age']).'</td>'
                                        .'<td>'.htmlspecialchars($row['phone']).'</td>'
                                        .'<td>'.htmlspecialchars($row['status']).'</td>'
                                        .'<td>'
                                            .'<a class="btn" href="/jowawbackup/edit.php?id='.urlencode($row['id']).'">Edit</a> '
                                            .'<a class="btn" href="/jowawbackup/delete.php?id='.urlencode($row['id']).'">Delete</a>'
                                        .'</td>'
                                    .'</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="9">No employees found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="reports" class="section">
                <?php 
                $reportRange = $_GET['r_range'] ?? 'today'; 
                $reportType = $_GET['r_type'] ?? 'all'; 
                $reportStatus = $_GET['r_status'] ?? 'all'; 
                $reportSearch = trim($_GET['r_search'] ?? ''); 
                $startDate = $_GET['start_date'] ?? '';
                $endDate = $_GET['end_date'] ?? '';
                ?>
                <div class="reports-header">
                    <div class="reports-title-section">
                        <h2 class="reports-title"><i class="las la-chart-bar"></i> Attendance Reports</h2>
                        <p class="reports-subtitle">Generate and export attendance reports with advanced filters</p>
                    </div>
                </div>
                
                <div class="reports-filter-card">
                    <form method="get" class="reports-filter-form">
                        <div class="filter-group">
                            <label class="filter-group-label">
                                <i class="las la-calendar"></i> Date Range
                            </label>
                            <select name="r_range" id="dateRangeSelect" class="report-select" onchange="toggleCustomDateRange()">
                                <option value="today" <?php echo $reportRange==='today'?'selected':''; ?>>Today</option>
                                <option value="week" <?php echo $reportRange==='week'?'selected':''; ?>>This Week</option>
                                <option value="month" <?php echo $reportRange==='month'?'selected':''; ?>>This Month</option>
                                <option value="custom" <?php echo $reportRange==='custom'?'selected':''; ?>>Custom Range</option>
                            </select>
                        </div>
                        
                        <div class="filter-group" id="customDateRange" style="display: <?php echo $reportRange==='custom'?'flex':'none'; ?>;">
                            <label class="filter-group-label">
                                <i class="las la-calendar-day"></i> Start Date
                            </label>
                            <input type="date" name="start_date" class="report-date-input" value="<?php echo htmlspecialchars($startDate); ?>" />
                        </div>
                        
                        <div class="filter-group" id="customDateRangeEnd" style="display: <?php echo $reportRange==='custom'?'flex':'none'; ?>;">
                            <label class="filter-group-label">
                                <i class="las la-calendar-check"></i> End Date
                            </label>
                            <input type="date" name="end_date" class="report-date-input" value="<?php echo htmlspecialchars($endDate); ?>" />
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-group-label">
                                <i class="las la-tag"></i> Type
                            </label>
                            <select name="r_type" class="report-select">
                                <option value="all" <?php echo $reportType==='all'?'selected':''; ?>>All Types</option>
                                <option value="daily" <?php echo $reportType==='daily'?'selected':''; ?>>Daily Attendance</option>
                                <option value="event" <?php echo $reportType==='event'?'selected':''; ?>>Events</option>
                                <option value="disaster" <?php echo $reportType==='disaster'?'selected':''; ?>>Disaster Response</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-group-label">
                                <i class="las la-check-circle"></i> Status
                            </label>
                            <select name="r_status" class="report-select">
                                <option value="all" <?php echo $reportStatus==='all'?'selected':''; ?>>All Status</option>
                                <option value="complete" <?php echo $reportStatus==='complete'?'selected':''; ?>>Complete</option>
                                <option value="incomplete" <?php echo $reportStatus==='incomplete'?'selected':''; ?>>Incomplete</option>
                            </select>
                        </div>
                        
                        <div class="filter-group filter-group-search">
                            <label class="filter-group-label">
                                <i class="las la-search"></i> Search
                            </label>
                            <input type="text" class="report-search-input" name="r_search" value="<?php echo htmlspecialchars($reportSearch); ?>" placeholder="Search by name, ID, position..." />
                        </div>
                        
                        <div class="report-actions">
                            <button class="btn-report btn-report-primary" type="submit">
                                <i class="las la-sync"></i> Generate Report
                            </button>
                            <a class="btn-report btn-report-success" href="?export=1&r_range=<?php echo urlencode($reportRange); ?>&r_type=<?php echo urlencode($reportType); ?>&r_status=<?php echo urlencode($reportStatus); ?>&r_search=<?php echo urlencode($reportSearch); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>">
                                <i class="las la-file-download"></i> Export CSV
                            </a>
                            <button class="btn-report btn-report-secondary" type="button" onclick="window.print()">
                                <i class="las la-print"></i> Print
                            </button>
                        </div>
                    </form>
                </div>
                <?php
                $where=[];$params=[];$types='';
                if ($reportRange==='today') $where[]='DATE(clock_in)=CURDATE()';
                elseif ($reportRange==='week') $where[]='YEARWEEK(clock_in,1)=YEARWEEK(CURDATE(),1)';
                elseif ($reportRange==='month') $where[]='YEAR(clock_in)=YEAR(CURDATE()) AND MONTH(clock_in)=MONTH(CURDATE())';
                elseif ($reportRange==='custom' && $startDate && $endDate) {
                    $where[]='DATE(clock_in) BETWEEN ? AND ?';
                    $params[]=$startDate;
                    $params[]=$endDate;
                    $types.='ss';
                }
                if ($reportType==='daily'){ $where[]='type=?'; $params[]='daily'; $types.='s'; }
                elseif ($reportType==='event'){ $where[]='type=?'; $params[]='Event'; $types.='s'; }
                elseif ($reportType==='disaster'){ $where[]='type=?'; $params[]='Disaster'; $types.='s'; }
                if ($reportStatus==='complete') $where[]="status='Complete'";
                elseif ($reportStatus==='incomplete') $where[]="status='Incomplete'";
                if ($reportSearch!==''){ $where[]='(employee_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR position LIKE ? OR details LIKE ?)'; $s='%'.$reportSearch.'%'; $params=array_merge($params,[$s,$s,$s,$s,$s]); $types.='sssss'; }
                $whereSql = $where?('WHERE '.implode(' AND ',$where)) : '';
                $countSql = "SELECT COUNT(*) AS c FROM attendance_db $whereSql";
                $cstmt = $conn->prepare($countSql); if ($params){ $cstmt->bind_param($types, ...$params);} $cstmt->execute(); $cnt=(int)$cstmt->get_result()->fetch_assoc()['c'];
                ?>
                <div class="report-summary">
                    <div class="report-summary-badge">
                        <i class="las la-database"></i>
                        <span>Total Records: <strong><?php echo $cnt; ?></strong></span>
                    </div>
                </div>
                <div class="print-report-header">
                    <h2>Attendance Report</h2>
                    <p>
                        Generated: <?php echo (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('F j, Y g:i A'); ?>
                        <span>|</span>
                        Range: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $reportRange))); ?>
                        <span>|</span>
                        Type: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $reportType))); ?>
                        <span>|</span>
                        Status: <?php echo htmlspecialchars(ucwords($reportStatus)); ?>
                    </p>
                </div>
                <div class="report-table-container">
                <table class="table" id="reportTable">
                    <thead>
                        <tr>
                            <th>ID</th><th>Employee ID</th><th>Name</th><th>Position</th><th>Clock In</th><th>Clock Out</th><th>Type</th><th>Details</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT id, employee_id, first_name, last_name, position, clock_in, clock_out, type, details, status FROM attendance_db $whereSql ORDER BY clock_in DESC";
                        $stmt = $conn->prepare($sql); if ($params){ $stmt->bind_param($types,...$params);} $stmt->execute(); $res=$stmt->get_result();
                        if ($res && $res->num_rows){ while($r=$res->fetch_assoc()){
                            $name = trim(($r['first_name']??'') . ' ' . ($r['last_name']??'')); if ($name===''){ $name=$r['employee_id']; }
                            $typeLower = strtolower($r['type']);
                            $typeLabel = ($typeLower === 'daily') ? 'Daily' : (($typeLower === 'disaster') ? 'Disaster Response' : ucfirst(str_replace('_', ' ', $typeLower)));
                            $details = ($typeLower === 'daily') ? 'Regular Check-in' : $r['details'];
                            echo '<tr>'
                                .'<td>'.htmlspecialchars($r['id']).'</td>'
                                .'<td>'.htmlspecialchars($r['employee_id']).'</td>'
                                .'<td>'.htmlspecialchars($name).'</td>'
                                .'<td>'.htmlspecialchars($r['position']).'</td>'
                                .'<td>'.($r['clock_in']?date('M d, Y h:i A', strtotime($r['clock_in'])):'').'</td>'
                                .'<td>'.($r['clock_out']?date('M d, Y h:i A', strtotime($r['clock_out'])):'').'</td>'
                                .'<td>'.htmlspecialchars($typeLabel).'</td>'
                                .'<td>'.htmlspecialchars($details).'</td>'
                                .'<td>'.htmlspecialchars($r['status']).'</td>'
                            .'</tr>';
                        }} else { echo '<tr><td colspan="9">No data for selected filters.</td></tr>'; }
                        ?>
                    </tbody>
                </table>
                </div>
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
                                <img src="<?php echo htmlspecialchars($_SESSION['profile_image'] ?? 'https://ui-avatars.com/api/?name='.urlencode($user_name)); ?>" alt="Avatar" class="profile-avatar-img" />
                                <div class="profile-avatar-overlay">
                                    <i class="las la-camera"></i>
                                </div>
                            </div>
                            <div class="profile-user-info">
                                <h3 class="profile-user-name"><?php echo htmlspecialchars($user_name); ?></h3>
                                <p class="profile-user-role"><i class="las la-shield-alt"></i> Administrator</p>
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