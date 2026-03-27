<?php
require_once __DIR__ . '/includes/auth_guard.php';

if (function_exists('require_auth')) {
    require_auth(['admin', 'staff']);
} else {
    header('Location: /library-management-system/login.php');
    exit();
}

require_once __DIR__ . '/config/db_connect.php';

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($bookId <= 0) {
    header('Location: books.php?error=not_found');
    exit();
}

$book = null;
$dbError = '';

try {
    $stmt = $conn->prepare('CALL sp_book_get_by_id(?)');
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare book lookup');
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
    $dbError = $e->getMessage();
}

$conn->close();

if (!$book && $dbError === '') {
    header('Location: books.php?error=not_found');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book | BryceLibrary</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <nav class="navbar">
        <div class="logo">Bryce<span>Library</span></div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="books.php">Books</a></li>
            <li><a href="borrowers.php">Borrowers</a></li>
            <li><a href="transactions.php">Transactions</a></li>
            <li><a href="handlers/auth/logout.php" class="login-btn">Logout</a></li>
        </ul>
    </nav>

    <section>
        <h1>Edit Book</h1>
        <p>Update book details and availability values.</p>

        <?php if ($dbError !== ''): ?>
            <div class="error-alert">Unable to load book right now.</div>
        <?php else: ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
            <div class="error-alert">Please provide valid values for all required fields.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid_year'): ?>
            <div class="error-alert">Year published must be between 1 and 9999.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'db'): ?>
            <div class="error-alert">Unable to update book right now. Please try again.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'schema'): ?>
            <div class="error-alert">Database schema is outdated. Run seed-file/seed.sql once, then retry.</div>
        <?php endif; ?>

        <form action="handlers/books/process_book_edit.php" method="POST">
            <input type="hidden" name="book_id" value="<?php echo $bookId; ?>">

            <label for="title">Title</label><br>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required><br><br>

            <label for="author">Author</label><br>
            <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required><br><br>

            <label for="category">Category</label><br>
            <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($book['category_name']); ?>" required><br><br>

            <label for="year_published">Year Published</label><br>
            <input type="number" id="year_published" name="year_published" min="1" max="9999" value="<?php echo ($book['year_published'] !== null && $book['year_published'] !== '') ? (int) $book['year_published'] : ''; ?>" required><br><br>

            <label for="total_copies">Total Copies</label><br>
            <input type="number" id="total_copies" name="total_copies" min="0" value="<?php echo (int) $book['total_copies']; ?>" required><br><br>

            <label for="available_copies">Available Copies</label><br>
            <input type="number" id="available_copies" name="available_copies" min="0" value="<?php echo (int) $book['available_copies']; ?>" required><br><br>

            <button type="submit" class="primary-btn">Update Book</button>
            <a href="books.php" class="primary-btn" style="background:#7f8c8d;">Cancel</a>
        </form>

        <?php endif; ?>
    </section>

</body>
</html>
