<?php
require_once __DIR__ . '/includes/auth_guard.php';

if (function_exists('require_auth')) {
    require_auth(['admin', 'staff']);
} else {
    header('Location: /library-management-system/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book | BryceLibrary</title>
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
        <h1>Add Book</h1>
        <p>UI scaffold form for book creation.</p>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
            <div class="error-alert">Please provide valid values for all required fields.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'db'): ?>
            <div class="error-alert">Unable to save book right now. Please try again.</div>
        <?php endif; ?>

        <form action="handlers/books/process_book_add.php" method="POST">
            <label for="title">Title</label><br>
            <input type="text" id="title" name="title" required><br><br>

            <label for="author">Author</label><br>
            <input type="text" id="author" name="author" required><br><br>

            <label for="category">Category</label><br>
            <input type="text" id="category" name="category" required><br><br>

            <label for="year_published">Year Published</label><br>
            <input type="number" id="year_published" name="year_published" min="1000" max="9999" required><br><br>

            <label for="total_copies">Total Copies</label><br>
            <input type="number" id="total_copies" name="total_copies" min="0" required><br><br>

            <label for="available_copies">Available Copies</label><br>
            <input type="number" id="available_copies" name="available_copies" min="0" required><br><br>

            <button type="submit" class="primary-btn">Save Book</button>
            <a href="books.php" class="primary-btn" style="background:#7f8c8d;">Cancel</a>
        </form>
    </section>

</body>
</html>
