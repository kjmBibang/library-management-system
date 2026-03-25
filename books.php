<?php
require_once 'auth_guard.php';
require_auth(['admin', 'staff']);

$allBooks = [
    ['bookID' => 1, 'title' => 'Clean Code', 'author' => 'Robert C. Martin', 'category' => 'Programming', 'year_published' => 2008, 'total_copies' => 5, 'available_copies' => 2],
    ['bookID' => 2, 'title' => 'The Pragmatic Programmer', 'author' => 'Andrew Hunt', 'category' => 'Programming', 'year_published' => 1999, 'total_copies' => 4, 'available_copies' => 0],
    ['bookID' => 3, 'title' => 'Atomic Habits', 'author' => 'James Clear', 'category' => 'Self-Help', 'year_published' => 2018, 'total_copies' => 6, 'available_copies' => 4],
    ['bookID' => 4, 'title' => 'Sapiens', 'author' => 'Yuval Noah Harari', 'category' => 'History', 'year_published' => 2011, 'total_copies' => 3, 'available_copies' => 1],
];

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

$categories = array_values(array_unique(array_map(static function ($book) {
    return $book['category'];
}, $allBooks)));
sort($categories);

$filteredBooks = array_values(array_filter($allBooks, static function ($book) use ($search, $category) {
    $matchesSearch = true;
    $matchesCategory = true;

    if ($search !== '') {
        $haystack = strtolower($book['title'] . ' ' . $book['author'] . ' ' . $book['category']);
        $matchesSearch = strpos($haystack, strtolower($search)) !== false;
    }

    if ($category !== '') {
        $matchesCategory = strcasecmp($book['category'], $category) === 0;
    }

    return $matchesSearch && $matchesCategory;
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Books | BryceLibrary</title>
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
        <h1>Books Management</h1>
        <p>UI scaffold mode: actions are wired but persistence will be connected in the next step.</p>

        <?php if (isset($_GET['book_added'])): ?>
            <div class="error-alert" style="background:#eafaf1 !important; color:#27ae60 !important; border-color:#27ae60 !important;">Book added (UI flow check).</div>
        <?php endif; ?>

        <?php if (isset($_GET['book_updated'])): ?>
            <div class="error-alert" style="background:#eafaf1 !important; color:#27ae60 !important; border-color:#27ae60 !important;">Book updated (UI flow check).</div>
        <?php endif; ?>

        <?php if (isset($_GET['book_deleted'])): ?>
            <div class="error-alert" style="background:#eafaf1 !important; color:#27ae60 !important; border-color:#27ae60 !important;">Book deleted (UI flow check).</div>
        <?php endif; ?>

        <a href="book_add.php" class="primary-btn">+ Add Book</a>

        <form action="books.php" method="GET" style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; align-items: end;">
            <div>
                <label for="q">Search (title / author / category)</label><br>
                <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type keyword...">
            </div>

            <div>
                <label for="category">Category</label><br>
                <select id="category" name="category" style="width: 220px; padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo strcasecmp($category, $cat) === 0 ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <button type="submit" class="primary-btn">Search</button>
                <a href="books.php" class="primary-btn" style="background:#7f8c8d;">Clear</a>
            </div>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Book ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Year</th>
                    <th>Total Copies</th>
                    <th>Available</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($filteredBooks) === 0): ?>
                    <tr>
                        <td colspan="9">No books matched your filters.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($filteredBooks as $book): ?>
                        <?php $status = $book['available_copies'] > 0 ? 'Available' : 'Unavailable'; ?>
                        <tr>
                            <td><?php echo (int) $book['bookID']; ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['category']); ?></td>
                            <td><?php echo (int) $book['year_published']; ?></td>
                            <td><?php echo (int) $book['total_copies']; ?></td>
                            <td><?php echo (int) $book['available_copies']; ?></td>
                            <td><?php echo $status; ?></td>
                            <td>
                                <a href="book_edit.php?id=<?php echo (int) $book['bookID']; ?>" class="primary-btn">Edit</a>
                                <a href="book_delete.php?id=<?php echo (int) $book['bookID']; ?>&title=<?php echo urlencode($book['title']); ?>" class="primary-btn" style="background:#e74c3c;">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

</body>
</html>
