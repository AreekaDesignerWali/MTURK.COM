<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['test_key'] = 'test_value';
    error_log("test_session.php - Session set: test_key=test_value");
    echo "<script>window.location.href='test_session.php';</script>";
    exit();
}

if (isset($_SESSION['test_key'])) {
    echo "Session test successful: test_key = " . $_SESSION['test_key'];
} else {
    echo "<form method='POST'><button type='submit'>Set Session</button></form>";
}
error_log("test_session.php - Session data: " . print_r($_SESSION, true));
ob_end_flush();
?>
