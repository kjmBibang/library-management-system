<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/config/db_connect.php';

if (function_exists('require_auth')) {
    require_auth(['admin', 'staff']);
} else {
    header('Location: /library-management-system/login.php');
    exit();
}

function clearStoredResults(mysqli $conn): void
{
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}

$books = [];
$borrowers = [];
$activeBorrows = [];
$dbError = '';
$dailyPenaltyRate = 25.00;

try {
    // --- 1. Load available books ---
    $bookStmt = $conn->prepare('CALL sp_book_search(?, ?, ?, ?)');
    if (!$bookStmt) {
        throw new mysqli_sql_exception('Book query prepare failed: ' . $conn->error);
    }

    $search = '';
    $categoryId = 0;
    $limitRows = 200;
    $offsetRows = 0;
    $bookStmt->bind_param('siii', $search, $categoryId, $limitRows, $offsetRows);
    $bookStmt->execute();

    $bookResult = $bookStmt->get_result();
    if ($bookResult) {
        while ($row = $bookResult->fetch_assoc()) {
            if ((int) $row['available_copies'] > 0) {
                $books[] = $row;
            }
        }
        $bookResult->free();
    }

    $bookStmt->close();
    clearStoredResults($conn);

    // --- 2. Load borrowers ---
    $borrowerStmt = $conn->prepare('CALL sp_borrower_search(?, ?, ?)');
    if (!$borrowerStmt) {
        throw new mysqli_sql_exception('Borrower query prepare failed: ' . $conn->error);
    }

    $searchBorrower = '';
    $borrowerLimit = 200;
    $borrowerOffset = 0;
    $borrowerStmt->bind_param('sii', $searchBorrower, $borrowerLimit, $borrowerOffset);
    $borrowerStmt->execute();

    $borrowerResult = $borrowerStmt->get_result();
    if ($borrowerResult) {
        while ($row = $borrowerResult->fetch_assoc()) {
            $borrowers[] = $row;
        }
        $borrowerResult->free();
    }

    $borrowerStmt->close();
    clearStoredResults($conn);

    // --- 3. Load active transactions ---
    $activeStmt = $conn->prepare('CALL sp_transaction_active_list(?, ?)');
    if (!$activeStmt) {
        throw new mysqli_sql_exception('Active transactions query prepare failed: ' . $conn->error);
    }

    $activeLimit = 200;
    $activeOffset = 0;
    $activeStmt->bind_param('ii', $activeLimit, $activeOffset);
    $activeStmt->execute();

    $activeResult = $activeStmt->get_result();
    if ($activeResult) {
        while ($row = $activeResult->fetch_assoc()) {
            $activeBorrows[] = $row;
        }
        $activeResult->free();
    }

    $activeStmt->close();
    clearStoredResults($conn);

} catch (mysqli_sql_exception $e) {
    $dbError = $e->getMessage();
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
    <title>Transactions | BryceLibrary</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .tx-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .tx-card {
            background: #fff;
            border: 1px solid #dbe6f0;
            border-radius: 10px;
            padding: 16px;
        }

        .tx-card h2 {
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .tx-card select,
        .tx-card input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .tx-list-wrap {
            margin-top: 20px;
            background: #fff;
            border: 1px solid #dbe6f0;
            border-radius: 10px;
            overflow-x: auto;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: #eef2f7;
            color: #2c3e50;
        }

        .status-pill.overdue {
            background: #fdeaea;
            color: #c0392b;
        }

        .row-overdue {
            background: #fff6f6 !important;
        }

        .row-overdue td {
            background: #fdeaea !important;
        }

        .debug-alert {
            margin-top: 10px;
            padding: 10px 14px;
            background: #fff8e1;
            border: 1px solid #f0c040;
            border-radius: 6px;
            color: #7a5c00;
            font-size: 0.9rem;
        }
    </style>
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
        <h1>Borrow and Return Transactions</h1>
        <p>Process borrow and return actions with live due-date and overdue tracking.</p>

        <?php if ($dbError !== ''): ?>
            <div class="error-alert">Unable to load transaction data right now: <?php echo htmlspecialchars($dbError); ?></div>
        <?php endif; ?>

        <?php if (empty($borrowers) && $dbError === ''): ?>
            <div class="debug-alert">⚠️ No borrowers loaded. Make sure <strong>sp_borrower_search</strong> exists in your database and accepts 3 parameters: <code>(search VARCHAR, limit INT, offset INT)</code>.</div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'borrowed'): ?>
            <div class="error-alert" style="background:#eafaf1 !important; color:#27ae60 !important; border-color:#27ae60 !important;">Book borrowed successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] === 'returned'): ?>
            <div class="error-alert" style="background:#eafaf1 !important; color:#27ae60 !important; border-color:#27ae60 !important;">Book returned successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'borrow_invalid'): ?>
            <div class="error-alert">Please select a book, borrower, and due date.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'borrow_invalid_due'): ?>
            <div class="error-alert">Due date must be later than the current borrow date.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'book_unavailable'): ?>
            <div class="error-alert">Selected book is no longer available for borrowing.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'borrow_reference'): ?>
            <div class="error-alert">Selected book or borrower was not found.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'borrow_failed'): ?>
            <div class="error-alert">Borrow transaction failed. Please try again.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'return_invalid'): ?>
            <div class="error-alert">Please select a transaction and return date.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'already_returned'): ?>
            <div class="error-alert">This transaction has already been returned.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'return_reference'): ?>
            <div class="error-alert">Transaction not found. Refresh and try again.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'return_failed'): ?>
            <div class="error-alert">Return transaction failed. Please try again.</div>
        <?php endif; ?>

        <div class="tx-grid">
            <div class="tx-card">
                <h2>Borrow a Book</h2>
                <form action="handlers/transactions/process_borrow.php" method="POST">
                    <label for="borrow_book_id">Available Book</label><br>
                    <select id="borrow_book_id" name="book_id" required>
                        <option value="">Select available book</option>
                        <?php foreach ($books as $book): ?>
                            <option value="<?php echo (int) $book['bookID']; ?>">
                                <?php echo htmlspecialchars($book['title']); ?> (<?php echo (int) $book['available_copies']; ?> available)
                            </option>
                        <?php endforeach; ?>
                    </select><br><br>

                    <label for="borrow_borrower_id">Borrower</label><br>
                    <select id="borrow_borrower_id" name="borrower_id" required>
                        <option value="">
                            <?php if (empty($borrowers)): ?>
                                No borrowers found — check sp_borrower_search
                            <?php else: ?>
                                Select borrower
                            <?php endif; ?>
                        </option>
                        <?php foreach ($borrowers as $borrower): ?>
                            <option value="<?php echo (int) $borrower['borrowerID']; ?>">
                                <?php echo htmlspecialchars($borrower['full_name']); ?> (<?php echo htmlspecialchars($borrower['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select><br><br>

                    <label for="borrow_due_date">Due Date</label><br>
                    <input type="datetime-local" id="borrow_due_date" name="due_date" required><br><br>

                    <button type="submit" class="primary-btn">Confirm Borrow</button>
                </form>
            </div>

            <div class="tx-card">
                <h2>Return a Book</h2>
                <form action="handlers/transactions/process_return.php" method="POST">
                    <label for="return_transaction_id">Active Transaction</label><br>
                    <select id="return_transaction_id" name="transaction_id" required>
                        <option value="">Select active transaction</option>
                        <?php foreach ($activeBorrows as $tx): ?>
                            <option value="<?php echo (int) $tx['transactionID']; ?>">
                                TX #<?php echo (int) $tx['transactionID']; ?> - <?php echo htmlspecialchars($tx['title']); ?> (<?php echo htmlspecialchars($tx['borrower_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select><br><br>

                    <label for="return_date">Return Date</label><br>
                    <input type="datetime-local" id="return_date" name="return_date" required><br><br>

                    <button type="submit" class="primary-btn">Confirm Return</button>
                </form>
            </div>
        </div>

        <div class="tx-list-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>TX ID</th>
                        <th>Book</th>
                        <th>Borrower</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Due Tracking</th>
                        <th>Status</th>
                        <th>Overdue Days</th>
                        <th>Penalty Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($activeBorrows) === 0): ?>
                        <tr>
                            <td colspan="9">No active borrows right now.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activeBorrows as $tx): ?>
                            <?php
                                $dueTimestamp = strtotime((string) $tx['due_date']);
                                $secondsDiff = $dueTimestamp !== false ? $dueTimestamp - time() : 0;
                                $isPastDueNow = $dueTimestamp !== false && $dueTimestamp < time();
                                $isOverdue = $isPastDueNow || (int) $tx['current_days_overdue'] > 0 || strtolower((string) $tx['status']) === 'overdue';
                                $statusClass = $isOverdue ? 'status-pill overdue' : 'status-pill';
                                $rowClass = $isOverdue ? 'row-overdue' : '';

                                if ($dueTimestamp === false) {
                                    $dueTrackingText = 'Unknown due date';
                                } elseif ($secondsDiff >= 0) {
                                    $hoursRemaining = (int) floor($secondsDiff / 3600);
                                    $minutesRemaining = (int) floor(($secondsDiff % 3600) / 60);
                                    $dueTrackingText = 'Due in ' . $hoursRemaining . 'h ' . $minutesRemaining . 'm';
                                } else {
                                    $lateSeconds = abs($secondsDiff);
                                    $hoursLate = (int) floor($lateSeconds / 3600);
                                    $minutesLate = (int) floor(($lateSeconds % 3600) / 60);
                                    $dueTrackingText = 'Overdue by ' . $hoursLate . 'h ' . $minutesLate . 'm';
                                }

                                $storedPenalty = isset($tx['penalty_fee']) ? (float) $tx['penalty_fee'] : 0.0;
                                $computedPenalty = max(0, (int) $tx['current_days_overdue']) * $dailyPenaltyRate;
                                $displayPenalty = $isOverdue ? max($storedPenalty, $computedPenalty) : $storedPenalty;
                            ?>
                            <tr class="<?php echo $rowClass; ?>" data-due-date="<?php echo htmlspecialchars((string) $tx['due_date']); ?>">
                                <td><?php echo (int) $tx['transactionID']; ?></td>
                                <td><?php echo htmlspecialchars($tx['title']); ?></td>
                                <td><?php echo htmlspecialchars($tx['borrower_name']); ?></td>
                                <td><?php echo htmlspecialchars((string) $tx['borrow_date']); ?></td>
                                <td><?php echo htmlspecialchars((string) $tx['due_date']); ?></td>
                                <td class="js-due-tracking"><?php echo htmlspecialchars($dueTrackingText); ?></td>
                                <td><span class="<?php echo $statusClass; ?> js-status-pill"><?php echo $isOverdue ? 'Overdue' : 'Active'; ?></span></td>
                                <td><?php echo (int) $tx['current_days_overdue']; ?></td>
                                <td class="penalty-text"><?php echo number_format(max(0.0, $displayPenalty), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <script>
        (function () {
            function parseMysqlDateTime(dateText) {
                if (!dateText) {
                    return null;
                }

                var normalized = dateText.trim().replace(' ', 'T');
                var parsed = new Date(normalized);
                return Number.isNaN(parsed.getTime()) ? null : parsed;
            }

            function refreshOverdueStyles() {
                var now = new Date();
                var rows = document.querySelectorAll('tr[data-due-date]');

                rows.forEach(function (row) {
                    var dueDate = parseMysqlDateTime(row.getAttribute('data-due-date'));
                    if (!dueDate) {
                        return;
                    }

                    var diffMs = dueDate.getTime() - now.getTime();
                    var isOverdue = diffMs < 0;
                    var pill = row.querySelector('.js-status-pill');
                    var dueTrackingCell = row.querySelector('.js-due-tracking');

                    if (dueTrackingCell) {
                        var absMs = Math.abs(diffMs);
                        var totalMinutes = Math.floor(absMs / 60000);
                        var hours = Math.floor(totalMinutes / 60);
                        var minutes = totalMinutes % 60;

                        if (isOverdue) {
                            dueTrackingCell.textContent = 'Overdue by ' + hours + 'h ' + minutes + 'm';
                        } else {
                            dueTrackingCell.textContent = 'Due in ' + hours + 'h ' + minutes + 'm';
                        }
                    }

                    if (isOverdue) {
                        row.classList.add('row-overdue');
                        if (pill) {
                            pill.classList.add('overdue');
                            pill.textContent = 'Overdue';
                        }
                    } else if (pill) {
                        row.classList.remove('row-overdue');
                        pill.classList.remove('overdue');
                        pill.textContent = 'Active';
                    }
                });
            }

            refreshOverdueStyles();
            setInterval(refreshOverdueStyles, 30000);
        })();
    </script>

</body>
</html>