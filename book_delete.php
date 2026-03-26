<?php
require_once 'auth_guard.php';
require_auth(['admin', 'staff']);
require_once 'db_connect.php';

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($bookId <= 0) {
    header('Location: books.php?error=not_found');
    exit();
}

$book = null;

try {
    $stmt = $conn->prepare('CALL sp_book_get_by_id(?)');
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare delete lookup');
    }

    $stmt->bind_param('i', $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result ? $result->fetch_assoc() : null;

    if ($result) {
        $result->free();
    }

    $stmt->close();

    while ($conn->more_results() && $conn->next_result()) {
        if ($pendingResult = $conn->store_result()) {
            $pendingResult->free();
        }
    }
} catch (Throwable $e) {
    $book = null;
}

$conn->close();

if (!$book) {
    header('Location: books.php?error=not_found');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Book | BryceLibrary</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <nav class="navbar">
        <div class="logo">Bryce<span>Library</span></div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="books.php">Books</a></li>
            <li><a href="borrowers.php">Borrowers</a></li>
            <li><a href="logout.php" class="login-btn">Logout</a></li>
        </ul>
    </nav>

    <section>
        <h1>Delete Book</h1>
        <div class="error-alert">
            This action cannot be undone. Are you sure you want to delete <strong><?php echo htmlspecialchars($book['title']); ?></strong> by <strong><?php echo htmlspecialchars($book['author']); ?></strong>?
        </div>

        <form action="process_book_delete.php" method="POST">
            <input type="hidden" name="book_id" value="<?php echo $bookId; ?>">
            <button type="submit" class="primary-btn" style="background:#e74c3c;">Yes, Delete</button>
            <a href="books.php" class="primary-btn" style="background:#7f8c8d;">Cancel</a>
        </form>
    </section>

</body>
</html>
