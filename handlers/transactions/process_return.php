<?php
require_once '../../includes/auth_guard.php';
require_once '../../config/db_connect.php';

if (function_exists('require_auth')) {
    require_auth(['admin', 'staff']);
} else {
    header('Location: /library-management-system/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../transactions.php');
    exit();
}

$transactionId = isset($_POST['transaction_id']) ? (int) $_POST['transaction_id'] : 0;
$returnDateInput = isset($_POST['return_date']) ? trim($_POST['return_date']) : '';
$processedBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$dailyRate = 5.00;

if ($transactionId <= 0 || $processedBy <= 0 || $returnDateInput === '') {
    header('Location: ../../transactions.php?error=return_invalid');
    exit();
}

$returnDate = str_replace('T', ' ', $returnDateInput) . ':00';

try {
    $stmt = $conn->prepare('CALL sp_return_book(?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare return operation.');
    }

    $stmt->bind_param('iisd', $transactionId, $processedBy, $returnDate, $dailyRate);
    $stmt->execute();
    $stmt->close();

    while ($conn->more_results() && $conn->next_result()) {
        if ($pendingResult = $conn->store_result()) {
            $pendingResult->free();
        }
    }

    $conn->close();
    header('Location: ../../transactions.php?success=returned');
    exit();
} catch (mysqli_sql_exception $e) {
    $conn->close();

    if ($e->getCode() === 1644) {
        $message = strtolower($e->getMessage());

        if (strpos($message, 'transaction already returned') !== false) {
            header('Location: ../../transactions.php?error=already_returned');
            exit();
        }

        if (strpos($message, 'transaction not found') !== false) {
            header('Location: ../../transactions.php?error=return_reference');
            exit();
        }
    }

    header('Location: ../../transactions.php?error=return_failed');
    exit();
} catch (Throwable $e) {
    $conn->close();
    header('Location: ../../transactions.php?error=return_failed');
    exit();
}
