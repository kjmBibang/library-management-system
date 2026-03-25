<?php
require_once 'auth_guard.php';
require_auth(['admin', 'staff']);

$bookId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$mockBooks = [
    1 => ['title' => 'Clean Code', 'author' => 'Robert C. Martin', 'category' => 'Programming', 'year_published' => 2008, 'total_copies' => 5, 'available_copies' => 2],
    2 => ['title' => 'The Pragmatic Programmer', 'author' => 'Andrew Hunt', 'category' => 'Programming', 'year_published' => 1999, 'total_copies' => 4, 'available_copies' => 0],
    3 => ['title' => 'Atomic Habits', 'author' => 'James Clear', 'category' => 'Self-Help', 'year_published' => 2018, 'total_copies' => 6, 'available_copies' => 4],
    4 => ['title' => 'Sapiens', 'author' => 'Yuval Noah Harari', 'category' => 'History', 'year_published' => 2011, 'total_copies' => 3, 'available_copies' => 1],
];

if ($bookId <= 0 || !isset($mockBooks[$bookId])) {
    header('Location: books.php?error=not_found');
    exit();
}

$book = $mockBooks[$bookId];
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
            <li><a href="logout.php" class="login-btn">Logout</a></li>
        </ul>
    </nav>

    <section>
        <h1>Edit Book</h1>
        <p>UI scaffold form for book editing.</p>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
            <div class="error-alert">Please provide valid values for all required fields.</div>
        <?php endif; ?>

        <form action="process_book_edit.php" method="POST">
            <input type="hidden" name="book_id" value="<?php echo $bookId; ?>">

            <label for="title">Title</label><br>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($book['title']); ?>" required><br><br>

            <label for="author">Author</label><br>
            <input type="text" id="author" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required><br><br>

            <label for="category">Category</label><br>
            <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($book['category']); ?>" required><br><br>

            <label for="year_published">Year Published</label><br>
            <input type="number" id="year_published" name="year_published" min="1000" max="9999" value="<?php echo (int) $book['year_published']; ?>" required><br><br>

            <label for="total_copies">Total Copies</label><br>
            <input type="number" id="total_copies" name="total_copies" min="0" value="<?php echo (int) $book['total_copies']; ?>" required><br><br>

            <label for="available_copies">Available Copies</label><br>
            <input type="number" id="available_copies" name="available_copies" min="0" value="<?php echo (int) $book['available_copies']; ?>" required><br><br>

            <button type="submit" class="primary-btn">Update Book</button>
            <a href="books.php" class="primary-btn" style="background:#7f8c8d;">Cancel</a>
        </form>
    </section>

</body>
</html>
