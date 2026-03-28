<?php
/* Mabanag: Borrower history report page (MVP) */
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db_connect.php';

if (function_exists('require_auth')) {
    require_auth(['admin', 'staff']);
} else {
    header('Location: login.php');
    exit();
}

/**
 * Clears stored results from the connection to allow subsequent queries
 */
function clearStoredResults(mysqli $conn): void
{
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}

$borrowerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$history = [];
$borrowerName = '';
$dbError = '';

try {
    if ($borrowerId > 0) {
        // 1. Fetch Borrower Name for header
        $stmt = $conn->prepare("SELECT full_name FROM borrowers WHERE borrowerID = ?");
        if ($stmt) {
            $stmt->bind_param("i", $borrowerId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $borrowerName = $row['full_name'];
            }
            $stmt->close();
        }

        // 2. Fetch History using correct column: penalty_fee 
        $query = "SELECT b.title, t.borrow_date, t.due_date, t.return_date, t.status, t.penalty_fee 
                  FROM transactions t
                  JOIN books b ON t.bookID = b.bookID
                  WHERE t.borrowerID = ?
                  ORDER BY t.borrow_date DESC";
        
        $historyStmt = $conn->prepare($query);
        
        if ($historyStmt === false) {
            throw new Exception("Database Prepare Error: " . $conn->error);
        }

        $historyStmt->bind_param("i", $borrowerId);
        $historyStmt->execute();
        $result = $historyStmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $historyStmt->close();
    }
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrower History | BryceLibrary</title>
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
        <h1>Borrower Transaction History</h1>
        <p>Records for: <strong><?php echo htmlspecialchars($borrowerName ?: 'Unknown Borrower'); ?></strong></p>

        <?php if ($dbError !== ''): ?>
            <div class="error-alert">
                <strong>Error:</strong> <?php echo htmlspecialchars($dbError); ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="borrowers.php" class="primary-btn" style="background:#7f8c8d;">&larr; Back to Borrowers</a>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Book Title</th>
                    <th>Borrow Date</th>
                    <th>Due Date</th>
                    <th>Return Date</th>
                    <th>Status</th>
                    <th>Penalty</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history) && $dbError === ''): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No history found for this borrower.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($history as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['borrow_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['due_date']); ?></td>
                            <td><?php echo $row['return_date'] ? htmlspecialchars($row['return_date']) : '<em>Pending</em>'; ?></td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td style="color: #e74c3c; font-weight: bold;">
    <?php 
    if ($row['status'] === 'returned' || $row['penalty_fee'] > 0) {
        echo number_format($row['penalty_fee'], 2);
    } else if ($row['status'] === 'overdue') {
        $today = new DateTime();
        $dueDate = new DateTime($row['due_date']);
        $days = $today->diff($dueDate)->days;
        echo number_format($days * 25.00, 2);
    } else {
        echo '0.00';
    }
    ?>
</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

</body>
</html>