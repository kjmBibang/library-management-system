<?php
session_start();

function require_auth(array $allowedRoles = ['admin', 'staff']): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'])) {
        header('Location: /library-management-system/login.php');
        exit();
    }

    $role = $_SESSION['role'];
    if (!in_array($role, $allowedRoles, true)) {
        header('Location: /library-management-system/login.php?error=forbidden');
        exit();
    }
}
