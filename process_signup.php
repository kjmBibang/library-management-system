<?php
// 1. Connect to the Database
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize input
    $library_id = trim($_POST['username']);
    $plain_password = $_POST['password'];

    // 2. Check if the Library ID already exists
    $check_stmt = $conn->prepare("SELECT library_id FROM users WHERE library_id = ?");
    $check_stmt->bind_param("s", $library_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // ID is taken, send them back with an error
        header("Location: signup.php?error=exists");
        exit();
    }

    // 3. HASH the password (this creates the secure 60-character string)
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

    // 4. Insert into the Database
    $insert_stmt = $conn->prepare("INSERT INTO users (library_id, password) VALUES (?, ?)");
    $insert_stmt->bind_param("ss", $library_id, $hashed_password);

    if ($insert_stmt->execute()) {
        // SUCCESS! Send to signup page with success message
        header("Location: signup.php?success=1");
        exit();
    } else {
        // If something went wrong with the DB
        die("Registration failed: " . $conn->error);
    }

    $insert_stmt->close();
    $conn->close();
} else {
    // If they tried to access this file directly without the form
    header("Location: signup.php");
    exit();
}
?>