<?php

require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $username = trim($_POST['username']);
    $plain_password = $_POST['password'];

   
    $check_stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        
        header("Location: signup.php?error=exists");
        exit();
    }

    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

    $insert_stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $insert_stmt->bind_param("ss", $username, $hashed_password);

    if ($insert_stmt->execute()) {
        
        header("Location: signup.php?success=1");
        exit();
    } else {
        
        die("Registration failed: " . $conn->error);
    }

    $insert_stmt->close();
    $conn->close();
} else {
    
    header("Location: signup.php");
    exit();
}
?>