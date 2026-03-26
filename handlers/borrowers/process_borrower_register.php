<?php
require_once '../../includes/auth_guard.php';

if (function_exists('require_auth')) {
    require_auth(['admin', 'staff']);
} else {
    header('Location: /library-management-system/login.php');
    exit();
}

require_once '../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../borrowers.php');
    exit();
}

$fullName = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$contactNumber = isset($_POST['contact_number']) ? trim($_POST['contact_number']) : '';

if ($fullName === '' || $email === '' || $contactNumber === '') {
    header('Location: ../../borrowers.php?error=invalid');
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../../borrowers.php?error=invalid');
    exit();
}

try {
    $borrowerId = 0;
    $stmt = $conn->prepare('CALL sp_borrower_save(?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare borrower save.');
    }

    $stmt->bind_param('isss', $borrowerId, $fullName, $email, $contactNumber);
    $stmt->execute();
    $stmt->close();

    while ($conn->more_results() && $conn->next_result()) {
        if ($pendingResult = $conn->store_result()) {
            $pendingResult->free();
        }
    }

    $conn->close();
    header('Location: ../../borrowers.php?success=borrower_registered');
    exit();
} catch (mysqli_sql_exception $e) {
    $conn->close();

    if ($e->getCode() === 1062) {
        header('Location: ../../borrowers.php?error=exists');
        exit();
    }

    header('Location: ../../borrowers.php?error=db');
    exit();
} catch (Throwable $e) {
    $conn->close();
    header('Location: ../../borrowers.php?error=db');
    exit();
}
