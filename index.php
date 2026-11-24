<?php
session_start();

// Initialize variables if not set
$activeForm = $_GET['form'] ?? 'login';
$errors = [
    'register' => $_SESSION['errors']['register'] ?? '',
    'login' => $_SESSION['errors']['login'] ?? ''
];

// Clear errors after displaying them
unset($_SESSION['errors']);

// Get saved form data if exists
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); // Clear after getting

// Function to check if form is active
function isActiveForm($form, $activeForm) {
    return $form === $activeForm ? 'active' : '';
}

// Function to show messages (error or success)
function showMessage($message, $type = 'error') {
    if (!$message) return '';
    $class = $type === 'success' ? 'success-message' : 'error-message';
    return '<div class="' . $class . '">' . htmlspecialchars($message) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Registration Form</title>
    <link rel="stylesheet" href="log_in.css">
</head>

<body>
  <canvas id="tech-canvas"></canvas>
  <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
    <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
    <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
  </button>
  <div class="main-wrapper">
    <div class="logo-section">
      <img src="imadejas_logo_no_bg2.png" alt="Imadejas Logo" class="logo-image" style="animation: none !important; transform: none !important; transition: none !important;">
    </div>
    <div class="container">
      <div class="form-box <?= isActiveForm('login', $activeForm) ?>" id="login-form">
        <form action="login_register.php" method="post">
            <h2>Log in</h2>
            <?= showMessage($errors['login']); ?>
            <?= showMessage($_SESSION['success'] ?? '', 'success'); unset($_SESSION['success']); ?>
            <input type="email" name="email" placeholder="Email" required>
            <div class="password-wrapper">
                <input type="password" name="password" id="login-password" placeholder="Password" required>
                <button type="button" class="toggle-password" onclick="togglePassword('login-password', this)">
                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="eye-off-icon" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
            </div>
            <select name="role" required>
                <option value="" selected>--Select Role--</option>
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit" name="login">Login</button>
            <p>Don't have an account? <a href="#" onclick="showForm('register'); return false;">Register</a></p>
        </form>
    </div>

    <div class="form-box <?= isActiveForm('register', $activeForm) ?>" id="register-form">
        <form action="login_register.php" method="post">
            <h2>Register</h2>
            <?= showMessage($errors['register']); ?>
            <?= showMessage($_SESSION['success'] ?? '', 'success'); unset($_SESSION['success']); ?>
            <input type="text" name="first_name" placeholder="First Name" value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>" required>
            <input type="text" name="last_name" placeholder="Last Name" value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>" required>
            <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
            <div class="password-wrapper">
                <input type="password" name="password" id="register-password" placeholder="Password" required>
                <button type="button" class="toggle-password" onclick="togglePassword('register-password', this)">
                    <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="eye-off-icon" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
            </div>
            <select name="role" required>
                <option value="" <?= !isset($formData['role']) ? 'selected' : '' ?>>--Select Role--</option>
                <option value="user" <?= ($formData['role'] ?? '') === 'user' ? 'selected' : '' ?>>User</option>
                <option value="admin" <?= ($formData['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            <button type="submit" name="register">Register</button>
            <p>Already have an account? <a href="#" onclick="showForm('login'); return false;">Login</a></p>
        </form>
    </div>
    </div>
  </div>
    <script src="script.js"></script>
</body>

</html>