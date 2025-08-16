<?php
session_start();

// Clear admin session data
unset($_SESSION['admin_id']);
unset($_SESSION['admin_nome']);
unset($_SESSION['admin_email']);

// Redirect to admin login
header('Location: /admin/login.php');
exit;
?>