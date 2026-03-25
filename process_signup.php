<?php

require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $username = trim($_POST['username']);
    $plain_password = $_POST['password'];

    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("CALL sp_user_create(?, ?, ?)");
    if (!$stmt) {
        die("Registration failed: " . $conn->error);
    }

    $role = 'staff';
    $stmt->bind_param("sss", $username, $hashed_password, $role);

    try {
        $stmt->execute();
        header("Location: signup.php?success=1");
        exit();
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1644 || stripos($e->getMessage(), 'Username already exists') !== false) {
            header("Location: signup.php?error=exists");
            exit();
        }

        die("Registration failed: " . $e->getMessage());
    }

    $stmt->close();

    while ($conn->more_results() && $conn->next_result()) {
    }

    $conn->close();
} else {
    
    header("Location: signup.php");
    exit();
}
?>