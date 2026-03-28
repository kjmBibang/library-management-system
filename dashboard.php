<?php
require_once __DIR__ . '/includes/auth_guard.php';

if (function_exists('require_auth')) {
    require_auth(['admin', 'staff']);
} else {
    header('Location: /library-management-system/login.php');
    exit();
}

$canViewAutomationGuardrails = function_exists('is_admin') ? is_admin() : false;

$metrics = [
    'total_books' => 0,
    'active_transactions' => 0,
    'overdue_count' => 0,
];
$collectionAlerts = [
    'unavailable_titles' => 0,
    'low_stock_titles' => 0,
    'overdue_borrowers' => 0,
    'borrowers_with_penalties' => 0,
];
$overdueRiskRows = [];
$automationStatus = [
    'event_scheduler' => 'Unknown',
    'active_trigger_count' => 0,
    'trigger_expected_count' => 3,
    'events' => [
        'ev_mark_overdue_transactions' => [
            'status' => 'Missing',
            'last_executed' => null,
            'purpose' => 'Marks late borrows as overdue.',
        ],
        'ev_refresh_penalties' => [
            'status' => 'Missing',
            'last_executed' => null,
            'purpose' => 'Refreshes penalty amounts for overdue items.',
        ],
        'ev_auto_set_returned_status' => [
            'status' => 'Missing',
            'last_executed' => null,
            'purpose' => 'Keeps status aligned for returned items.',
        ],
    ],
    'is_degraded' => false,
];
$recentTransactions = [];
$dbError = '';
$conn = null;

function clearDashboardResults(mysqli $conn): void
{
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
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

    if (!($conn instanceof mysqli)) {
        throw new RuntimeException('Database connection was not initialized.');
    }
    $dashboardConn = $conn;

    $summaryStmt = $dashboardConn->prepare('CALL sp_dashboard_summary()');
    if (!$summaryStmt) {
        throw new RuntimeException('Failed to prepare dashboard summary statement.');
    }
    $summaryStmt->execute();
    $summaryResult = $summaryStmt->get_result();
    if ($summaryResult) {
        $summaryRow = $summaryResult->fetch_assoc();
        if ($summaryRow) {
            $metrics['total_books'] = (int) $summaryRow['total_books'];
            $metrics['active_transactions'] = (int) $summaryRow['active_transactions'];
            $metrics['overdue_count'] = (int) $summaryRow['overdue_transactions'];
        }
        $summaryResult->free();
    }
    $summaryStmt->close();
    clearDashboardResults($dashboardConn);

    $recentStmt = $dashboardConn->prepare('CALL sp_recent_transactions(?)');
    if (!$recentStmt) {
        throw new RuntimeException('Failed to prepare recent transactions statement.');
    }
    $recentLimit = 10;
    $recentStmt->bind_param('i', $recentLimit);
    $recentStmt->execute();
    $recentResult = $recentStmt->get_result();
    if ($recentResult) {
        while ($row = $recentResult->fetch_assoc()) {
            $recentTransactions[] = $row;
        }
        $recentResult->free();
    }
    $recentStmt->close();
    clearDashboardResults($dashboardConn);

    // Operational insights powered by DB objects for user-facing decisions.
    try {
        $catalogAlertSql = "
            SELECT
                SUM(CASE WHEN fn_book_availability_status(available_copies) = 'Unavailable' THEN 1 ELSE 0 END) AS unavailable_titles,
                SUM(CASE WHEN available_copies BETWEEN 1 AND 2 THEN 1 ELSE 0 END) AS low_stock_titles
            FROM vw_books_catalog
        ";
        $catalogAlertResult = $dashboardConn->query($catalogAlertSql);
        if ($catalogAlertResult) {
            $row = $catalogAlertResult->fetch_assoc();
            if ($row) {
                $collectionAlerts['unavailable_titles'] = (int) ($row['unavailable_titles'] ?? 0);
                $collectionAlerts['low_stock_titles'] = (int) ($row['low_stock_titles'] ?? 0);
            }
            $catalogAlertResult->free();
        }

        $overdueRiskSql = "
            SELECT
                transactionID,
                borrower_name,
                title,
                due_date,
                fn_days_overdue(due_date, NOW()) AS overdue_days,
                fn_compute_penalty(due_date, NOW(), 25.00) AS projected_penalty
            FROM vw_active_overdue_transactions
            WHERE fn_days_overdue(due_date, NOW()) > 0
            ORDER BY overdue_days DESC, due_date ASC
            LIMIT 5
        ";
        $overdueRiskResult = $dashboardConn->query($overdueRiskSql);
        if ($overdueRiskResult) {
            while ($row = $overdueRiskResult->fetch_assoc()) {
                $overdueRiskRows[] = [
                    'transactionID' => (int) ($row['transactionID'] ?? 0),
                    'borrower_name' => (string) ($row['borrower_name'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'due_date' => (string) ($row['due_date'] ?? ''),
                    'overdue_days' => (int) ($row['overdue_days'] ?? 0),
                    'projected_penalty' => (float) ($row['projected_penalty'] ?? 0),
                ];
            }
            $overdueRiskResult->free();
        }

        $borrowerRiskSql = "
            SELECT
                COUNT(DISTINCT CASE WHEN status = 'overdue' OR (return_date IS NULL AND due_date < NOW()) THEN borrowerID END) AS overdue_borrowers,
                COUNT(DISTINCT CASE WHEN penalty_fee IS NOT NULL AND penalty_fee > 0 THEN borrowerID END) AS borrowers_with_penalties
            FROM vw_borrower_history
        ";
        $borrowerRiskResult = $dashboardConn->query($borrowerRiskSql);
        if ($borrowerRiskResult) {
            $row = $borrowerRiskResult->fetch_assoc();
            if ($row) {
                $collectionAlerts['overdue_borrowers'] = (int) ($row['overdue_borrowers'] ?? 0);
                $collectionAlerts['borrowers_with_penalties'] = (int) ($row['borrowers_with_penalties'] ?? 0);
            }
            $borrowerRiskResult->free();
        }

        if ($canViewAutomationGuardrails) {
            $schedulerResult = $dashboardConn->query("SELECT @@event_scheduler AS event_scheduler");
            if ($schedulerResult) {
                $row = $schedulerResult->fetch_assoc();
                $automationStatus['event_scheduler'] = strtoupper((string) ($row['event_scheduler'] ?? 'Unknown'));
                $schedulerResult->free();
            }

            $triggerNames = [
                'trg_transactions_bi_validate_borrow',
                'trg_transactions_ai_decrement_on_borrow',
                'trg_transactions_au_increment_on_return',
            ];
            $triggerQuery = "SELECT COUNT(*) AS trigger_count FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME IN ('" . implode("','", $triggerNames) . "')";
            $triggerResult = $dashboardConn->query($triggerQuery);
            if ($triggerResult) {
                $row = $triggerResult->fetch_assoc();
                $automationStatus['active_trigger_count'] = (int) ($row['trigger_count'] ?? 0);
                $triggerResult->free();
            }

            $eventNames = array_keys($automationStatus['events']);
            $eventQuery = "SELECT EVENT_NAME, STATUS, LAST_EXECUTED FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE() AND EVENT_NAME IN ('" . implode("','", $eventNames) . "')";
            $eventResult = $dashboardConn->query($eventQuery);
            if ($eventResult) {
                while ($row = $eventResult->fetch_assoc()) {
                    $name = (string) ($row['EVENT_NAME'] ?? '');
                    if ($name !== '' && array_key_exists($name, $automationStatus['events'])) {
                        $automationStatus['events'][$name]['status'] = (string) ($row['STATUS'] ?? 'Unknown');
                        $automationStatus['events'][$name]['last_executed'] = isset($row['LAST_EXECUTED']) ? (string) $row['LAST_EXECUTED'] : null;
                    }
                }
                $eventResult->free();
            }

            $automationStatus['is_degraded'] = $automationStatus['event_scheduler'] !== 'ON'
                || $automationStatus['active_trigger_count'] < $automationStatus['trigger_expected_count'];

            foreach ($automationStatus['events'] as $eventInfo) {
                if (stripos((string) $eventInfo['status'], 'ENABLED') === false) {
                    $automationStatus['is_degraded'] = true;
                    break;
                }
            }
        }
    } catch (Throwable $inner) {
        // Keep dashboard functional even if operational insight queries fail.
    }
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
                <h3>Active Transactions</h3>
                <p><?php echo (int) $metrics['active_transactions']; ?> active transactions currently in progress.</p>
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

    <section class="section bg-light">
        <h2>Operations Watchlist</h2>
        <p>Daily alerts pulled from live transactions and catalog data to guide staff actions.</p>

        <div class="services-grid" style="margin-top: 20px;">
            <div class="service-card" style="text-align:left;">
                <h3>Collection Alerts</h3>
                <p><strong><?php echo (int) $collectionAlerts['unavailable_titles']; ?></strong> titles are currently unavailable.</p>
                <p><strong><?php echo (int) $collectionAlerts['low_stock_titles']; ?></strong> titles are low stock (1-2 copies).</p>
            </div>
            <div class="service-card" style="text-align:left;">
                <h3>Borrower Risk</h3>
                <p><strong><?php echo (int) $collectionAlerts['overdue_borrowers']; ?></strong> borrowers currently have overdue items.</p>
                <p><strong><?php echo (int) $collectionAlerts['borrowers_with_penalties']; ?></strong> borrowers have penalty history.</p>
            </div>
            <div class="service-card" style="text-align:left;">
                <h3>Action Tips</h3>
                <p>Prioritize follow-up on the oldest overdue items below.</p>
                <p>Replenish low-stock titles before they become unavailable.</p>
            </div>
        </div>

        <h3 style="margin-top: 28px;">Top Overdue Risk Forecast</h3>
        <?php if (count($overdueRiskRows) === 0): ?>
            <p>No overdue risk rows right now.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>TX ID</th>
                        <th>Borrower</th>
                        <th>Book</th>
                        <th>Due Date</th>
                        <th>Overdue Days</th>
                        <th>Projected Penalty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overdueRiskRows as $risk): ?>
                        <tr>
                            <td><?php echo (int) $risk['transactionID']; ?></td>
                            <td><?php echo htmlspecialchars($risk['borrower_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($risk['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars(formatDashboardDate($risk['due_date']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $risk['overdue_days']; ?></td>
                            <td><?php echo number_format((float) $risk['projected_penalty'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <?php if ($canViewAutomationGuardrails): ?>
        <section class="section">
            <h2>Automation Guardrails</h2>
            <p>Automation status that keeps overdue labels, stock counts, and penalties accurate.</p>

            <?php if ($automationStatus['is_degraded']): ?>
                <div class="error-alert" style="background:#fff3cd !important; color:#856404 !important; border-color:#ffecb5 !important;">
                    Automation attention needed: overdue and penalty updates may lag until all scheduler/events/triggers are active.
                </div>
            <?php endif; ?>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Automation</th>
                        <th>Status</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Event Scheduler</td>
                        <td><?php echo htmlspecialchars((string) $automationStatus['event_scheduler'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>Runs periodic overdue and penalty maintenance jobs.</td>
                    </tr>
                    <tr>
                        <td>Stock Consistency Triggers</td>
                        <td><?php echo (int) $automationStatus['active_trigger_count']; ?> / <?php echo (int) $automationStatus['trigger_expected_count']; ?> active</td>
                        <td>Auto-updates available copies on borrow/return.</td>
                    </tr>
                    <?php foreach ($automationStatus['events'] as $name => $eventInfo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php echo htmlspecialchars((string) $eventInfo['status'], ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (!empty($eventInfo['last_executed'])): ?>
                                    <br><small>Last run: <?php echo htmlspecialchars(formatDashboardDate($eventInfo['last_executed']), ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars((string) $eventInfo['purpose'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

</body>
</html>
