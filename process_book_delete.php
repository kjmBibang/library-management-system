<?php
require_once 'auth_guard.php';
require_auth(['admin', 'staff']);
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: books.php');
    exit();
}

$bookId = isset($_POST['book_id']) ? (int) $_POST['book_id'] : 0;
if ($bookId <= 0) {
    header('Location: books.php?error=not_found');
    exit();
}

try {
    $stmt = $conn->prepare('CALL sp_book_delete(?)');
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare delete step.');
    }

    $stmt->bind_param('i', $bookId);
    $stmt->execute();
    $stmt->close();

    while ($conn->more_results() && $conn->next_result()) {
        if ($pendingResult = $conn->store_result()) {
            $pendingResult->free();
        }
    }

    $conn->close();
    header('Location: books.php?book_deleted=1');
    exit();
} catch (mysqli_sql_exception $e) {
    $conn->close();

    if ($e->getCode() === 1644 && stripos($e->getMessage(), 'Cannot delete book with active transactions') !== false) {
        header('Location: books.php?error=cannot_delete_active');
        exit();
    }

    header('Location: books.php?error=delete_failed');
    exit();
} catch (Throwable $e) {
    $conn->close();
    header('Location: books.php?error=delete_failed');
    exit();
}
