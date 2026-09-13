<?php
require_once 'config/init.php';

// Log activity
if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'logout', 'User logged out', $db);
}

// Destroy session
session_destroy();

// Redirect to login page
redirect('login.php');
?>