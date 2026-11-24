<?php
// Start the session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page with a success message
$_SESSION['logout_success'] = 'You have been successfully logged out.';
header("Location: index.php");
exit();
?>
