<?php
session_start();
require_once '../../config/db_connect.php';

function clearStoredResults(mysqli $conn): void
{
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = trim($_POST['username']);
    $pass_input = $_POST['password'];

    if ($user_input === '') {
        header("Location: ../../login.php?error=invalid");
        exit();
    }

    $stmt = $conn->prepare('CALL sp_auth_get_user(?)');
    if (!$stmt) {
        header("Location: ../../login.php?error=invalid");
        exit();
    }

    $stmt->bind_param("s", $user_input);
    if (!$stmt->execute()) {
        header("Location: ../../login.php?error=invalid");
        exit();
    }

    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $result->free();
        $stmt->close();
        clearStoredResults($conn);

        if (password_verify($pass_input, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            header("Location: ../../dashboard.php");
            exit();
        } else {
            header("Location: ../../login.php?error=invalid");
            exit();
        }
    } else {
        header("Location: ../../login.php?error=invalid");
        exit();
    }

    if ($result) {
        $result->free();
    }

    $stmt->close();
    clearStoredResults($conn);
}
?>