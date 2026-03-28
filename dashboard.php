<?php
require_once __DIR__ . '/includes/auth_guard.php';

if (function_exists('require_auth')) {
    require_auth(['admin', 'staff']);
} else {
    header('Location: /library-management-system/login.php');
    exit();
}

$metrics = [
    'total_books' => 0,
    'active_borrowers' => 0,
    'overdue_count' => 0,
];
$recentTransactions = [];
$dbError = '';
$conn = null;

function fetchSingleValue(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!$result) {
        throw new RuntimeException('Database query failed.');
    }

    $row = $result->fetch_row();
    $value = $row ? (int) $row[0] : 0;
    $result->free();

    return $value;
}

function formatDashboardDate(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'N/A';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return 'N/A';
    }

    return date('M d, Y h:i A', $timestamp);
}

try {
    require_once __DIR__ . '/config/db_connect.php';

    $metrics['total_books'] = fetchSingleValue(
        $conn,
        'SELECT COUNT(*) FROM books'
    );

    $metrics['active_borrowers'] = fetchSingleValue(
        $conn,
        "SELECT COUNT(DISTINCT borrowerID) FROM transactions WHERE return_date IS NULL AND status IN ('borrowed', 'overdue')"
    );

    $metrics['overdue_count'] = fetchSingleValue(
        $conn,
        "SELECT COUNT(*) FROM transactions WHERE return_date IS NULL AND status = 'overdue'"
    );

    $recentSql = "
        SELECT
            br.full_name AS borrower_name,
            b.title AS book_title,
            CASE
                WHEN t.return_date IS NULL AND t.due_date < NOW() THEN 'overdue'
                ELSE t.status
            END AS status,
            t.borrow_date,
            t.due_date
        FROM transactions t
        INNER JOIN borrowers br ON br.borrowerID = t.borrowerID
        INNER JOIN books b ON b.bookID = t.bookID
        ORDER BY t.transactionID DESC
        LIMIT 10
    ";

    $recentResult = $conn->query($recentSql);
    if (!$recentResult) {
        throw new RuntimeException('Unable to load recent transactions.');
    }

    while ($row = $recentResult->fetch_assoc()) {
        $recentTransactions[] = $row;
    }

    $recentResult->free();
} catch (Throwable $e) {
    $dbError = 'Unable to load dashboard data right now.';
}

if ($conn !== null) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | BryceLibrary</title>
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

    <section class="section">
        <h1>Welcome, <?php echo htmlspecialchars((string) $_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>!</h1>
        <p>You have successfully accessed the BryceLibrary dashboard.</p>
        <a href="books.php" class="primary-btn">Go to Books</a>
        <a href="borrowers.php" class="primary-btn">Borrowers</a>
        <a href="transactions.php" class="primary-btn">Transactions</a>
    </section>

    <section class="section bg-light">
        <h2>Library Statistics</h2>

        <?php if ($dbError !== ''): ?>
            <div class="error-alert"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="services-grid">
            <div class="service-card">
                <h3>Total Books</h3>
                <p><?php echo (int) $metrics['total_books']; ?> books currently listed in the library system.</p>
            </div>

            <div class="service-card">
                <h3>Active Borrowers</h3>
                <p><?php echo (int) $metrics['active_borrowers']; ?> borrowers currently have active transactions.</p>
            </div>

            <div class="service-card">
                <h3>Overdue Count</h3>
                <p><?php echo (int) $metrics['overdue_count']; ?> active transactions are marked overdue.</p>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>Recent Transactions</h2>

        <?php if ($dbError !== ''): ?>
            <p>Recent transactions are unavailable right now.</p>
        <?php elseif (count($recentTransactions) === 0): ?>
            <p>No recent transactions.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Borrower</th>
                        <th>Book Title</th>
                        <th>Status</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $transaction['borrower_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $transaction['book_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst((string) $transaction['status']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(formatDashboardDate($transaction['borrow_date']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(formatDashboardDate($transaction['due_date']), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

</body>
</html>
