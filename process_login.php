<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = $_POST['username'];
    $pass_input = $_POST['password'];

    // 1. Prepare the statement to prevent SQL Injection (Security!)
    $stmt = $conn->prepare("SELECT id, library_id, password FROM users WHERE library_id = ?");
    $stmt->bind_param("s", $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // 2. Verify the hashed password
        if (password_verify($pass_input, $user['password'])) {
            // Success!
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['library_id'] = $user['library_id'];
            
            header("Location: dashboard.php");
            exit();
        } else {
            // Wrong password
            header("Location: login.php?error=invalid");
            exit();
        }
    } else {
        // User not found
        header("Location: login.php?error=invalid");
        exit();
    }
}
?>