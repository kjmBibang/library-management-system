<?php

require_once '../../config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $role = isset($_POST['role']) ? trim(strtolower($_POST['role'])) : 'staff';
    $plain_password = $_POST['password'];

    if ($username === '') {
        header("Location: ../../signup.php?error=exists");
        exit();
    }

    if (!in_array($role, ['admin', 'staff'], true)) {
        header("Location: ../../signup.php?error=invalid_role");
        exit();
    }

    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO users (username, password, role)
         VALUES (?, ?, ?)"
    );
    if (!$stmt) {
        die("Registration failed: " . $conn->error);
    }

    $stmt->bind_param("sss", $username, $hashed_password, $role);

    try {
        $stmt->execute();
        header("Location: ../../signup.php?success=1");
        exit();
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062 || stripos($e->getMessage(), 'Duplicate entry') !== false) {
            header("Location: ../../signup.php?error=exists");
            exit();
        }

        die("Registration failed: " . $e->getMessage());
    }

    $stmt->close();

    $conn->close();
} else {

    header("Location: ../../signup.php");
    exit();
}
?>