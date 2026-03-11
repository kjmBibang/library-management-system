<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
<body class="dashboard-page-body">

    <nav class="navbar">
        <div class="logo">Bryce<span>Library</span></div>
        <ul class="nav-links">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="books.php">Books</a></li>
            <li><a href="login.php" class="login-btn">Logout</a></li>
        </ul>
    </nav>

    <div class="dashboard-page-wrapper">
        <div class="dashboard-page-hero">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['library_id']); ?>!</h1>
            <p>You have successfully accessed the BryceLibrary dashboard.</p>
        </div>

        <div class="dashboard-page-stats-grid">
            <div class="dashboard-page-stat-card">
                <h3>Total Books</h3>
                <div class="dashboard-page-stat-number">120</div>
                <p>Books currently listed in the library system.</p>
            </div>

            <div class="dashboard-page-stat-card">
                <h3>Available Books</h3>
                <div class="dashboard-page-stat-number">85</div>
                <p>Books ready for borrowing.</p>
            </div>

            <div class="dashboard-page-stat-card">
                <h3>Borrowed Books</h3>
                <div class="dashboard-page-stat-number">35</div>
                <p>Books currently borrowed by users.</p>
            </div>

            <div class="dashboard-page-stat-card">
                <h3>Active Members</h3>
                <div class="dashboard-page-stat-number">48</div>
                <p>Registered users with access to the system.</p>
            </div>
        </div>

        <div class="dashboard-page-panel">
            <h2>Quick Overview</h2>
            <ul>
                <li>Manage and view book records from the Books page.</li>
                <li>Track book availability and borrowed status.</li>
                <li>Use this dashboard as the main control panel of your library system.</li>
                <li>All values are static for now until database features are added.</li>
            </ul>

            <div class="dashboard-page-links">
                <a href="books.php">Go to Books</a>
                <a href="index.php">Home Page</a>
            </div>
        </div>
    </div>

</body>
</html>