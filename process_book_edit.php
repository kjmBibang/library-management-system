<?php
require_once 'auth_guard.php';
require_auth(['admin', 'staff']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: books.php');
    exit();
}

$bookId = isset($_POST['book_id']) ? (int) $_POST['book_id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$author = isset($_POST['author']) ? trim($_POST['author']) : '';
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
$yearPublished = isset($_POST['year_published']) ? (int) $_POST['year_published'] : 0;
$totalCopies = isset($_POST['total_copies']) ? (int) $_POST['total_copies'] : -1;
$availableCopies = isset($_POST['available_copies']) ? (int) $_POST['available_copies'] : -1;

if ($bookId <= 0 || $title === '' || $author === '' || $category === '' || $yearPublished <= 0 || $totalCopies < 0 || $availableCopies < 0 || $availableCopies > $totalCopies) {
    header('Location: book_edit.php?id=' . $bookId . '&error=invalid');
    exit();
}

header('Location: books.php?book_updated=1');
exit();
