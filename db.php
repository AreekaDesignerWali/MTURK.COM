<?php
$servername = "localhost";
$username = "uc7ggok7oyoza";
$password = "gqypavorhbbc";
$dbname = "dbstildvg1rzfm";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed in db.php: " . $conn->connect_error);
    exit("Connection failed: " . $conn->connect_error);
}
?>
