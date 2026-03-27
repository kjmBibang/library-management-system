<?php
require_once '../../includes/auth_guard.php';
require_auth(['admin', 'staff']);
require_once '../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../books.php');
    exit();
}

$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$author = isset($_POST['author']) ? trim($_POST['author']) : '';
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
$yearPublished = isset($_POST['year_published']) ? (int) $_POST['year_published'] : 0;
$totalCopies = isset($_POST['total_copies']) ? (int) $_POST['total_copies'] : -1;
$availableCopies = isset($_POST['available_copies']) ? (int) $_POST['available_copies'] : -1;

if ($title === '' || $author === '' || $category === '' || $yearPublished <= 0 || $totalCopies < 0 || $availableCopies < 0 || $availableCopies > $totalCopies) {
    header('Location: ../../book_add.php?error=invalid');
    exit();
}

if ($yearPublished < 1 || $yearPublished > 9999) {
    header('Location: ../../book_add.php?error=invalid_year');
    exit();
}

try {
    $categoryStmt = $conn->prepare('CALL sp_category_get_or_create(?)');
    if (!$categoryStmt) {
        throw new RuntimeException('Unable to prepare category step.');
    }

    $categoryStmt->bind_param('s', $category);
    $categoryStmt->execute();

    $categoryResult = $categoryStmt->get_result();
    $categoryRow = $categoryResult ? $categoryResult->fetch_assoc() : null;
    $categoryId = $categoryRow ? (int) $categoryRow['categoryID'] : 0;

    if ($categoryResult) {
        $categoryResult->free();
    }

    $categoryStmt->close();

    while ($conn->more_results() && $conn->next_result()) {
        if ($pendingResult = $conn->store_result()) {
            $pendingResult->free();
        }
    }

    if ($categoryId <= 0) {
        throw new RuntimeException('Unable to resolve category.');
    }

    $bookStmt = $conn->prepare('CALL sp_book_save(?, ?, ?, ?, ?, ?, ?)');
    if (!$bookStmt) {
        throw new RuntimeException('Unable to prepare book save step.');
    }

    $bookId = 0;
    $bookStmt->bind_param('issiiii', $bookId, $title, $author, $categoryId, $totalCopies, $availableCopies, $yearPublished);
    $bookStmt->execute();
    $bookStmt->close();

    while ($conn->more_results() && $conn->next_result()) {
        if ($pendingResult = $conn->store_result()) {
            $pendingResult->free();
        }
    }

    $conn->close();
    header('Location: ../../books.php?book_added=1');
    exit();
} catch (Throwable $e) {
    $conn->close();
    header('Location: ../../book_add.php?error=db');
    exit();
}

