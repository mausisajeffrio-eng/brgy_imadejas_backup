<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for flash messages
session_start();

// Log the start of the request
error_log('=== Starting attendance.php ===');
error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
error_log('POST Data: ' . print_r($_POST, true));

// Handle attendance submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /jowawbackup/admin_page.php#attendance');
    exit;
}

// Database configuration
$servername = 'localhost';
$username = 'root';
$password = '';
$dbname = 'brgy_db';

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    $_SESSION['error'] = 'Database connection failed. Please try again.';
    header('Location: /jowawbackup/admin_page.php#attendance');
}

try {
    // Debug log all POST data
    error_log('POST Data: ' . print_r($_POST, true));
    
    // Sanitize and validate input
    $qrText = isset($_POST['text']) ? trim($_POST['text']) : '';
    $attendanceType = isset($_POST['type']) ? strtolower(trim($_POST['type'])) : 'daily'; // Default to 'daily' if not specified
    $details = isset($_POST['details']) ? trim($_POST['details']) : '';

    // Normalize type variants (e.g., 'disaster', 'disaster-response')
    $normalizedType = str_replace([' ', '-'], '_', $attendanceType);
    if (in_array($normalizedType, ['disaster', 'disasterresponse'])) {
        $normalizedType = 'disaster_response';
    }
    $attendanceType = $normalizedType;

    error_log("Initial values - Normalized Type: $attendanceType, Details: $details");

    // Validate attendance type and details
    $validTypes = ['daily', 'event', 'disaster_response'];
    if (!in_array($attendanceType, $validTypes, true)) {
        throw new Exception('Invalid attendance type');
    }
    
    // Set default details based on type if not provided
    if ($attendanceType === 'daily') {
        $details = 'Regular Check-in';
    } elseif ($attendanceType === 'event' && $details === '') {
        $details = 'Event: No details provided';
    } elseif ($attendanceType === 'disaster_response' && $details === '') {
        $details = 'No details provided';
    }
    
    error_log("After processing - Type: $attendanceType, Details: $details");

    // Parse QR code data (format: EMP-001|First|Last|Position)
    $qrData = explode('|', $qrText);

    // Validate QR code format
    if (count($qrData) !== 4) {
        throw new Exception('Invalid QR code format. Expected: EMP-001|First|Last|Position');
    }

    // Extract and validate data
    $employeeId = strtoupper(trim($qrData[0]));
    $firstName = trim($qrData[1]);
    $lastName = trim($qrData[2]);
    $position = trim($qrData[3]);

    // Validate employee ID format
    if (!preg_match('/^EMP-\d+$/', $employeeId)) {
        throw new Exception('Invalid employee ID format. Must be in EMP-001 format');
    }

    // Check if employee exists in database
    $stmt = $conn->prepare('SELECT id FROM emp_db WHERE employee_id = ?');
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param('s', $employeeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        throw new Exception('Employee not found in database');
    }

    // Check for existing attendance today
    $date = date('Y-m-d');
    
    // First, check if user has already completed attendance (both clock in and clock out)
    $checkCompleteStmt = $conn->prepare(
        'SELECT id, clock_in, clock_out FROM attendance_db 
         WHERE employee_id = ? 
         AND DATE(clock_in) = ? 
         AND clock_out IS NOT NULL'
    );
    
    if (!$checkCompleteStmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $checkCompleteStmt->bind_param('ss', $employeeId, $date);
    $checkCompleteStmt->execute();
    $completeResult = $checkCompleteStmt->get_result();
    $checkCompleteStmt->close();
    
    if ($completeResult->num_rows > 0) {
        // User has already completed attendance for today
        throw new Exception('Already completed attendance for today. You have clocked in and out.');
    }
    
    // Check for existing clock-in without clock-out
    $checkStmt = $conn->prepare(
        'SELECT id FROM attendance_db 
         WHERE employee_id = ? 
         AND DATE(clock_in) = ? 
         AND clock_out IS NULL'
    );
    
    if (!$checkStmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $checkStmt->bind_param('ss', $employeeId, $date);
    
    if (!$checkStmt->execute()) {
        throw new Exception('Database error: ' . $checkStmt->error);
    }
    
    $result = $checkStmt->get_result();
    $checkStmt->close();

    if ($result->num_rows > 0) {
        // Clock out
        $row = $result->fetch_assoc();
        $updateStmt = $conn->prepare(
            'UPDATE attendance_db 
             SET clock_out = NOW(), status = "Complete"
             WHERE id = ?'
        );
        
        if (!$updateStmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $updateStmt->bind_param('i', $row['id']);
        
        if ($updateStmt->execute()) {
            $updateStmt->close();
            
            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo '<title>Success: Clock Out Successful</title>';
                exit;
            }
            
            $_SESSION['success'] = 'Clock Out successful';
        } else {
            throw new Exception('Failed to update clock out time');
        }
    } else {
        // Clock in
        // Log before insertion
        error_log("About to insert attendance record:");
        error_log("- Employee: $employeeId");
        error_log("- Name: $firstName $lastName");
        error_log("- Position: $position");
        error_log("- Type: $attendanceType");
        error_log("- Details: $details");
        
        $insertStmt = $conn->prepare(
            'INSERT INTO attendance_db 
             (employee_id, first_name, last_name, position, type, details, clock_in, status) 
             VALUES (?, ?, ?, ?, ?, ?, NOW(), "Incomplete")'
        );
        
        if (!$insertStmt) {
            $error = $conn->error;
            error_log("Database prepare error: $error");
            throw new Exception('Database error: ' . $error);
        }
        
        $insertStmt->bind_param('ssssss', $employeeId, $firstName, $lastName, $position, $attendanceType, $details);
        
        if ($insertStmt->execute()) {
            error_log("Successfully inserted attendance record");
            $insertStmt->close();
            
            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo '<title>Success: Clock In Successful</title>';
                exit;
            }
            
            $_SESSION['success'] = 'Clock In successful';
        } else {
            throw new Exception('Failed to record clock in');
        }
    }
    
    $conn->close();
    header('Location: /jowawbackup/admin_page.php#attendance');
    exit;
    
} catch (Exception $e) {
    error_log('Attendance Error: ' . $e->getMessage());
    
    // Check if this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(400);
        echo '<title>Error: ' . htmlspecialchars($e->getMessage()) . '</title>';
        if (isset($conn)) $conn->close();
        exit;
    }
    
    $_SESSION['error'] = $e->getMessage();
    if (isset($conn)) $conn->close();
    header('Location: /jowawbackup/admin_page.php#attendance');
    exit;
}

