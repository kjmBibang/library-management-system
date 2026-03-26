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

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$borrowers = [];
$dbError = '';

try {
    $stmt = $conn->prepare('CALL sp_borrower_search(?, ?, ?)');
    if ($stmt) {
        $limitRows = 200;
        $offsetRows = 0;
        $stmt->bind_param('sii', $search, $limitRows, $offsetRows);
        $stmt->execute();

        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $borrowers[] = $row;
            }
            $result->free();
        }

        $stmt->close();
        clearStoredResults($conn);
    }
} catch (mysqli_sql_exception $e) {
    $dbError = $e->getMessage();
}

$conn->close();

$openRegisterForm = isset($_GET['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<title>Borrowers</title>

<link rel="stylesheet" href="css/style.css">
<style>
.borrowers-shell {
    max-width: 1200px;
    margin: 0 auto;
}

.borrowers-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.borrowers-subtext {
    color: #667085;
    margin-top: 4px;
}

.borrowers-table-wrap {
    margin-top: 20px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow-x: auto;
}

.borrowers-register-panel {
    margin-top: 20px;
    padding: 18px;
    border: 1px solid #dbe6f0;
    border-radius: 10px;
    background: #f9fcff;
}

.borrowers-register-panel h2 {
    margin-bottom: 10px;
}

.borrowers-register-panel.is-hidden {
    display: none;
}

.borrowers-stats {
    margin-top: 12px;
    color: #475467;
    font-size: 0.95rem;
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
<div class="borrowers-shell">

<div class="borrowers-topbar">
<div>
<h1>Borrowers Management</h1>
<p class="borrowers-subtext">Track registered members and quickly add new borrowers.</p>
</div>
<button type="button" id="toggleRegisterBtn" class="primary-btn"><?php echo $openRegisterForm ? 'Hide Register Form' : '+ Register Borrower'; ?></button>
</div>

<?php if ($dbError !== ''): ?>
<div class="error-alert">Unable to load borrowers right now: <?php echo htmlspecialchars($dbError); ?></div>
<?php endif; ?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'borrower_registered'): ?>
<div class="error-alert" style="background:#eafaf1 !important; color:#27ae60 !important; border-color:#27ae60 !important;">Borrower registered successfully.</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
<div class="error-alert">Please provide valid borrower details.</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'exists'): ?>
<div class="error-alert">Borrower email already exists.</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'db'): ?>
<div class="error-alert">Unable to register borrower right now. Please try again.</div>
<?php endif; ?>

<form action="borrowers.php" method="GET" style="margin-top: 15px; display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
<div>
<label for="q">Search Borrowers</label><br>
<input type="text" id="q" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email, or contact">
</div>
<div>
<button type="submit" class="primary-btn">Search</button>
<a href="borrowers.php" class="primary-btn" style="background:#7f8c8d;">Clear</a>
</div>
</form>

<p class="borrowers-stats">Total results: <?php echo count($borrowers); ?></p>

<div class="borrowers-table-wrap">
<table class="data-table">

<thead>
<tr>
<th>Borrower ID</th>
<th>Name</th>
<th>Email</th>
<th>Contact</th>
<th>Registered Date</th>
</tr>
</thead>

<tbody>

<?php if (count($borrowers) === 0): ?>
<tr>
<td colspan="5">No borrowers found.</td>
</tr>
<?php else: ?>
<?php foreach ($borrowers as $borrower): ?>
<tr>
<td><?php echo (int) $borrower['borrowerID']; ?></td>
<td><?php echo htmlspecialchars($borrower['full_name']); ?></td>
<td><?php echo htmlspecialchars($borrower['email']); ?></td>
<td><?php echo htmlspecialchars($borrower['contact_number']); ?></td>
<td><?php echo htmlspecialchars((string) $borrower['registered_date']); ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>

</tbody>

</table>
</div>

<div id="registerPanel" class="borrowers-register-panel <?php echo $openRegisterForm ? '' : 'is-hidden'; ?>">
<h2>Register Borrower</h2>

<form action="handlers/borrowers/process_borrower_register.php" method="POST">

<label>Full Name</label><br>
<input type="text" name="full_name" required>
<br><br>

<label>Email</label><br>
<input type="email" name="email" required>
<br><br>

<label>Contact Number</label><br>
<input type="text" name="contact_number" required>
<br><br>

<button type="submit" class="primary-btn">Register</button>

</form>
</div>

</div>
</section>

<script>
(function () {
    var registerPanel = document.getElementById('registerPanel');
    var toggleBtn = document.getElementById('toggleRegisterBtn');

    if (!registerPanel || !toggleBtn) {
        return;
    }

    toggleBtn.addEventListener('click', function () {
        var isHidden = registerPanel.classList.toggle('is-hidden');
        toggleBtn.textContent = isHidden ? '+ Register Borrower' : 'Hide Register Form';
    });
})();
</script>

</body>
</html>
