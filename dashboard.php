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
            <li><a href="handlers/auth/logout.php" class="login-btn">Logout</a></li>
        </ul>
    </nav>

    <section class="section">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <p>You have successfully accessed the BryceLibrary dashboard.</p>
        <a href="books.php" class="primary-btn">Go to Books</a>
        <a href="borrowers.php" class="primary-btn">Borrowers</a>
    </section>

    <section class="section bg-light">
        <h2>Library Statistics</h2>
        <div class="services-grid">
            <div class="service-card">
                <h3>Total Books</h3>
                <p>120 books currently listed in the library system.</p>
            </div>

            <div class="service-card">
                <h3>Available Books</h3>
                <p>85 books ready for borrowing.</p>
            </div>

            <div class="service-card">
                <h3>Borrowed Books</h3>
                <p>35 books currently borrowed by users.</p>
            </div>

            <div class="service-card">
                <h3>Active Members</h3>
                <p>48 registered users with access to the system.</p>
            </div>
        </div>
    </section>

    <section class="section">
            <h2>Quick Overview</h2>
            <ul>
                <li>Manage and view book records from the Books page.</li>
                <li>Track book availability and borrowed status.</li>
                <li>Use this dashboard as the main control panel of your library system.</li>
                <li>All values are static for now until database features are added.</li>
            </ul>

            <a href="index.php" class="primary-btn">Home Page</a>
    </section>

</body>
</html>