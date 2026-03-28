<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/db_connect.php';

const ADMIN_SIGNUP_ACCESS_CODE = 'mezzzc6d';

function clearStoredResults(mysqli $conn): void
{
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $role = isset($_POST['role']) ? trim(strtolower($_POST['role'])) : 'staff';
    $plain_password = $_POST['password'];
    $adminAccessCode = isset($_POST['admin_access_code']) ? trim((string) $_POST['admin_access_code']) : '';
    $actorRole = current_user_role();
    $adminCodeValidated = false;

    if ($username === '') {
        header("Location: ../../signup.php?error=exists");
        exit();
    }

    if (!in_array($role, ['admin', 'staff'], true)) {
        header("Location: ../../signup.php?error=invalid_role");
        exit();
    }

    if ($role === 'admin' && $actorRole !== 'admin') {
        $hasAdminAccount = false;
        $adminCountResult = $conn->query("SELECT COUNT(*) AS admin_count FROM users WHERE role = 'admin'");
        if ($adminCountResult) {
            $adminCountRow = $adminCountResult->fetch_assoc();
            $hasAdminAccount = ((int) ($adminCountRow['admin_count'] ?? 0)) > 0;
            $adminCountResult->free();
        }

        if ($hasAdminAccount && !hash_equals(ADMIN_SIGNUP_ACCESS_CODE, $adminAccessCode)) {
            header("Location: ../../signup.php?error=invalid_admin_code");
            exit();
        }

        if ($hasAdminAccount) {
            $adminCodeValidated = true;
        }
    }

    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare('CALL sp_user_create(?, ?, ?, ?)');
    if (!$stmt) {
        header("Location: ../../signup.php?error=db");
        exit();
    }

    $actorRoleParam = $actorRole !== null ? $actorRole : '';
    if ($role === 'admin' && $adminCodeValidated) {
        $actorRoleParam = 'admin';
    }
    $stmt->bind_param("ssss", $username, $hashed_password, $role, $actorRoleParam);

    try {
        $stmt->execute();
        $stmt->close();
        clearStoredResults($conn);

        $conn->close();
        header("Location: ../../signup.php?success=1");
        exit();
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062 || stripos($e->getMessage(), 'already exists') !== false || stripos($e->getMessage(), 'Duplicate entry') !== false) {
            header("Location: ../../signup.php?error=exists");
            exit();
        }

        if ($e->getCode() === 1644 && stripos($e->getMessage(), 'Only admin users can create admin accounts') !== false) {
            header("Location: ../../signup.php?error=forbidden_role");
            exit();
        }

        header("Location: ../../signup.php?error=db");
        exit();
    }

    $stmt->close();
    clearStoredResults($conn);

    $conn->close();
} else {

    header("Location: ../../signup.php");
    exit();
}
?>