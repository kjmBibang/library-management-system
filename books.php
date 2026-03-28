<?php
require_once __DIR__ . '/includes/auth_guard.php';

if (function_exists('require_auth')) {
    require_auth(['admin', 'staff']);
} else {
    header('Location: /library-management-system/login.php');
    exit();
}

require_once __DIR__ . '/config/db_connect.php';

$isAdmin = function_exists('is_admin') && is_admin();

function clearStoredResults(mysqli $conn): void
{
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;

/* Mabanag: Books pagination logic */
$limitRows = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offsetRows = ($page - 1) * $limitRows;

$categories = [];
$filteredBooks = [];
$dbError = '';

try {
    $categoryStmt = $conn->prepare("CALL sp_category_list()");
    if ($categoryStmt) {
        $categoryStmt->execute();
        $categoryResult = $categoryStmt->get_result();

        if ($categoryResult) {
            while ($row = $categoryResult->fetch_assoc()) {
                $categories[] = $row;
            }
            $categoryResult->free();
        }

        $categoryStmt->close();
        clearStoredResults($conn);
    }

    $bookStmt = $conn->prepare("CALL sp_book_search(?, ?, ?, ?)");
    if ($bookStmt) {
        // Mabanag: Using dynamic limit and offset
        $bookStmt->bind_param("siii", $search, $categoryId, $limitRows, $offsetRows);
        $bookStmt->execute();

        $bookResult = $bookStmt->get_result();
        if ($bookResult) {
            while ($row = $bookResult->fetch_assoc()) {
                $filteredBooks[] = $row;
            }
            $bookResult->free();
        }

        $bookStmt->close();
        clearStoredResults($conn);
    }
} catch (mysqli_sql_exception $e) {
    $dbError = $e->getMessage();
}

$conn->close();
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
            <li><a href="transactions.php">Transactions</a></li>
            <li><a href="handlers/auth/logout.php" class="login-btn">Logout</a></li>
        </ul>
    </nav>

    <section>
        <h1>Books Management</h1>
        <p>Manage your catalog and monitor live availability.</p>

        <?php if ($dbError !== ''): ?>
            <div class="error-alert">Unable to load books right now: <?php echo htmlspecialchars($dbError); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['book_added'])): ?>
            <div class="error-alert" style="background:#eafaf1 !important; color:#27ae60 !important; border-color:#27ae60 !important;">Book added successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET['book_updated'])): ?>
            <div class="error-alert" style="background:#eafaf1 !important; color:#27ae60 !important; border-color:#27ae60 !important;">Book updated successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET['book_deleted'])): ?>
            <div class="error-alert" style="background:#eafaf1 !important; color:#27ae60 !important; border-color:#27ae60 !important;">Book deleted successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'cannot_delete_active'): ?>
            <div class="error-alert">Cannot delete this book because it has active borrowed or overdue transactions.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'delete_failed'): ?>
            <div class="error-alert">Delete failed. Please try again.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'not_found'): ?>
            <div class="error-alert">Book not found.</div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <a href="book_add.php" class="primary-btn">+ Add Book</a>
        <?php else: ?>
            <div class="error-alert" style="background:#eef7ff; color:#2c3e50; border-color:#7aa7d9;">Staff role has view-only access to the book catalog.</div>
        <?php endif; ?>

        <form action="books.php" method="GET" style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 10px; align-items: end;">
            <div>
                <label for="q">Search (title / author / category)</label><br>
                <input type="text" id="q" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Type keyword...">
            </div>

            <div>
                <label for="category_id">Category</label><br>
                <select id="category_id" name="category_id" style="width: 220px; padding: 8px; border: 1px solid #ccc; border-radius: 5px;">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo (int) $cat['categoryID']; ?>" <?php echo $categoryId === (int) $cat['categoryID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
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
                        <tr>
                            <td><?php echo (int) $book['bookID']; ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['category_name']); ?></td>
                            <td><?php echo ($book['year_published'] !== null && $book['year_published'] !== '') ? (int) $book['year_published'] : 'N/A'; ?></td>
                            <td><?php echo (int) $book['total_copies']; ?></td>
                            <td><?php echo (int) $book['available_copies']; ?></td>
                            <td><?php echo htmlspecialchars($book['availability_status']); ?></td>
                            <td>
                                <?php if ($isAdmin): ?>
                                    <a href="book_edit.php?id=<?php echo (int) $book['bookID']; ?>" class="primary-btn">Edit</a>
                                    <a href="book_delete.php?id=<?php echo (int) $book['bookID']; ?>" class="primary-btn" style="background:#e74c3c;">Delete</a>
                                <?php else: ?>
                                    <span style="color:#6b7280;">View only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination" style="margin-top: 20px; text-align: center; display: flex; justify-content: center; align-items: center; gap: 10px;">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($search); ?>&category_id=<?php echo $categoryId; ?>" class="primary-btn">Prev</a>
            <?php endif; ?>
            
            <span style="font-weight: bold; color: #2c3e50;">Page <?php echo $page; ?></span>
            
            <?php if (count($filteredBooks) === $limitRows): ?>
                <a href="?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($search); ?>&category_id=<?php echo $categoryId; ?>" class="primary-btn">Next</a>
            <?php endif; ?>
        </div>
    </section>

</body>
</html>