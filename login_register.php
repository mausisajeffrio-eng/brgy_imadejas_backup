<?php
require_once 'config.php';

// Debug: Log POST data
error_log('POST data: ' . print_r($_POST, true));

// Debug: Check if script is being accessed
error_log('Script accessed: ' . $_SERVER['REQUEST_METHOD']);

// Initialize error array
if (!isset($_SESSION['errors'])) {
    $_SESSION['errors'] = ['login' => '', 'register' => ''];
}

// Handle Login
if (isset($_POST['login'])) {
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password'];
    $selected_role = isset($_POST['role']) ? trim($_POST['role']) : '';
    
    if (empty($email) || empty($password) || empty($selected_role)) {
        $_SESSION['errors']['login'] = "Please enter email, password, and select a role";
    } else {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Ensure selected role matches stored role
                if ($selected_role !== $user['role']) {
                    $_SESSION['errors']['login'] = "Selected role does not match your account role";
                } else {
                    // Password and role are correct, start a new session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['email'] = $user['email']; // For compatibility with existing isLoggedIn()
                    $_SESSION['role'] = $user['role'];   // For compatibility with isAdmin()
                    // Load avatar into session if present
                    if (isset($user['profile_image']) && $user['profile_image'] !== '') {
                        $_SESSION['profile_image'] = $user['profile_image'];
                    } else {
                        unset($_SESSION['profile_image']);
                    }
                    
                    // Redirect based on stored role
                    if ($user['role'] === 'admin') {
                        header("Location: admin_page.php");
                    } else {
                        header("Location: user_page.php");
                    }
                    exit();
                }
            } else {
                $_SESSION['errors']['login'] = "Invalid email or password";
            }
        } else {
            $_SESSION['errors']['login'] = "No account found with this email";
        }
        $stmt->close();
    }
    
    header("Location: index.php?form=login");
    exit();
}

// Handle Registration
if (isset($_POST['register'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'admin' : 'user';
    
    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        $_SESSION['errors']['register'] = "❌ All fields are required";
        $_SESSION['form_data'] = $_POST; // Save form data
        header("Location: index.php?form=register");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['errors']['register'] = "❌ Invalid email format";
        $_SESSION['form_data'] = $_POST; // Save form data
        header("Location: index.php?form=register");
        exit();
    }
    
    // Check if email already exists using prepared statement
    $check_sql = "SELECT id FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['errors']['register'] = "❌ This email is already registered. Please use a different email or login.";
        $_SESSION['form_data'] = $_POST; // Save form data
        $check_stmt->close();
        header("Location: index.php?form=register");
        exit();
    }
    $check_stmt->close();
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user using prepared statement
    $sql = "INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $first_name, $last_name, $email, $hashed_password, $role);
    
    if ($stmt->execute()) {
        // Registration successful, redirect to login
        $_SESSION['success'] = "🎉 Registration successful! You can now login with your credentials.";
        $stmt->close();
        header("Location: index.php?form=login");
    } else {
        $_SESSION['errors']['register'] = "❌ Registration failed. Please try again. " . $conn->error;
        $stmt->close();
        header("Location: index.php?form=register");
    }
    
    exit();
}

// If someone tries to access this file directly
header("Location: index.php");
exit();
?>
