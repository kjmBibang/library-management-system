<?php

require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $library_id = trim($_POST['username']);
    $plain_password = $_POST['password'];

   
    $check_stmt = $conn->prepare("SELECT library_id FROM users WHERE library_id = ?");
    $check_stmt->bind_param("s", $library_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        
        header("Location: signup.php?error=exists");
        exit();
    }

    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

    $insert_stmt = $conn->prepare("INSERT INTO users (library_id, password) VALUES (?, ?)");
    $insert_stmt->bind_param("ss", $library_id, $hashed_password);

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