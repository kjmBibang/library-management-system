<?php
session_start();

function current_user_role(): ?string
{
    return isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;
}

function is_authenticated(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
}

function is_admin(): bool
{
    return current_user_role() === 'admin';
}

function require_auth(array $allowedRoles = ['admin', 'staff']): void
{
    if (!is_authenticated()) {
        header('Location: /library-management-system/login.php');
        exit();
    }

    $role = (string) $_SESSION['role'];
    if (!in_array($role, $allowedRoles, true)) {
        header('Location: /library-management-system/login.php?error=forbidden');
        exit();
    }
}
