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
$dailyPenaltyRate = 25.00;

try {
    if ($borrowerId > 0) {
        $borrowerStmt = $conn->prepare('CALL sp_borrower_get_by_id(?)');
        if ($borrowerStmt === false) {
            throw new Exception('Database Prepare Error: ' . $conn->error);
        }

        $borrowerStmt->bind_param('i', $borrowerId);
        $borrowerStmt->execute();
        $borrowerResult = $borrowerStmt->get_result();
        if ($borrowerResult && ($borrowerRow = $borrowerResult->fetch_assoc())) {
            $borrowerName = (string) $borrowerRow['full_name'];
        }
        if ($borrowerResult) {
            $borrowerResult->free();
        }
        $borrowerStmt->close();
        clearStoredResults($conn);

        $historyStmt = $conn->prepare('CALL sp_borrower_history_list(?)');
        if ($historyStmt === false) {
            throw new Exception('Database Prepare Error: ' . $conn->error);
        }
    
        $historyStmt->bind_param('i', $borrowerId);
        $historyStmt->execute();
        $historyResult = $historyStmt->get_result();

        if ($historyResult) {
            while ($row = $historyResult->fetch_assoc()) {
                $history[] = $row;
            }
            $historyResult->free();
        }

        $historyStmt->close();
        clearStoredResults($conn);
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
                                $storedPenalty = isset($row['penalty_fee']) ? (float) $row['penalty_fee'] : 0.0;
                                $status = strtolower((string) $row['status']);
                                $isOverdue = $status === 'overdue';
                                $daysOverdue = 0;

                                $dueTimestamp = strtotime((string) $row['due_date']);
                                if ($isOverdue && $dueTimestamp !== false && $dueTimestamp < time()) {
                                    $daysOverdue = (int) floor((time() - $dueTimestamp) / 86400);
                                }

                                $computedPenalty = $daysOverdue * $dailyPenaltyRate;
                                $displayPenalty = $isOverdue ? max($storedPenalty, $computedPenalty) : $storedPenalty;

                                echo number_format(max(0.0, $displayPenalty), 2);
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