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

$bookId = isset($_POST['book_id']) ? (int) $_POST['book_id'] : 0;
$borrowerId = isset($_POST['borrower_id']) ? (int) $_POST['borrower_id'] : 0;
$dueDateInput = isset($_POST['due_date']) ? trim($_POST['due_date']) : '';
$processedBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if ($bookId <= 0 || $borrowerId <= 0 || $processedBy <= 0 || $dueDateInput === '') {
    header('Location: ../../transactions.php?error=borrow_invalid');
    exit();
}

$dueDate = str_replace('T', ' ', $dueDateInput) . ':00';

try {
    $stmt = $conn->prepare('CALL sp_borrow_book(?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare borrow operation.');
    }

    $stmt->bind_param('iiis', $bookId, $borrowerId, $processedBy, $dueDate);
    $stmt->execute();
    $stmt->close();

    while ($conn->more_results() && $conn->next_result()) {
        if ($pendingResult = $conn->store_result()) {
            $pendingResult->free();
        }
    }

    $conn->close();
    header('Location: ../../transactions.php?success=borrowed');
    exit();
} catch (mysqli_sql_exception $e) {
    $conn->close();

    if ($e->getCode() === 1644) {
        $message = strtolower($e->getMessage());

        if (strpos($message, 'book is currently unavailable') !== false) {
            header('Location: ../../transactions.php?error=book_unavailable');
            exit();
        }

        if (strpos($message, 'due date cannot be earlier than borrow date') !== false) {
            header('Location: ../../transactions.php?error=borrow_invalid_due');
            exit();
        }

        if (strpos($message, 'book not found') !== false || strpos($message, 'borrower not found') !== false) {
            header('Location: ../../transactions.php?error=borrow_reference');
            exit();
        }
    }

    header('Location: ../../transactions.php?error=borrow_failed');
    exit();
} catch (Throwable $e) {
    $conn->close();
    header('Location: ../../transactions.php?error=borrow_failed');
    exit();
}
