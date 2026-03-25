<?php
require_once 'auth_guard.php';
require_auth(['admin', 'staff']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: books.php');
    exit();
}

$bookId = isset($_POST['book_id']) ? (int) $_POST['book_id'] : 0;
if ($bookId <= 0) {
    header('Location: books.php?error=not_found');
    exit();
}

header('Location: books.php?book_deleted=1');
exit();
