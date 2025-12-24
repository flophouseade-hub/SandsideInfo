<?php
session_start(); // Start session before any output

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
//$_SESSION['loggedin'] = FALSE;
session_unset();
session_destroy();

header("Location: ../index.php");
exit;