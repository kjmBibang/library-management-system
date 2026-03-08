<?php
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "bryce_library";
$port = 3306; // Change this to your custom port

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>