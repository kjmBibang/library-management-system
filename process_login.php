<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = $_POST['username'];
    $pass_input = $_POST['password'];

    // 1. Check Database connection
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }

    $stmt = $conn->prepare("SELECT id, library_id, password FROM users WHERE library_id = ?");
    $stmt->bind_param("s", $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    // DEBUG: Check if user was found
    if ($result->num_rows === 0) {
        die("DEBUG: User not found in database. You typed: " . htmlspecialchars($user_input));
    }

    $user = $result->fetch_assoc();

    // DEBUG: Show the hash from the DB vs what you typed
    if (password_verify($pass_input, $user['password'])) {
        // Success!
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['library_id'] = $user['library_id'];
        header("Location: dashboard.php");
        exit();
    } else {
        // DEBUG: Password mismatch
        echo "DEBUG: Password verify failed.<br>";
        echo "Typed: " . htmlspecialchars($pass_input) . "<br>";
        echo "Hash in DB: " . $user['password'] . "<br>";
        die("Verify failed.");
    }
}
?>