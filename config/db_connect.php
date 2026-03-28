<?php
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "bryce_library";
$port = 3306; // if mag error, ilisdi and port i match sa xampp port

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    throw new RuntimeException('Database connection failed.');
}
?>