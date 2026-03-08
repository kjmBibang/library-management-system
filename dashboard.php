<?php
session_start();

// If the user isn't logged in, kick them back to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard | BryceLibrary</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo">Bryce<span>Library</span></div>
        <ul class="nav-links">
            <li><a href="logout.php" class="login-btn">Logout</a></li>
        </ul>
    </nav>

    <section class="section">
        <h1>Welcome, <?php echo $_SESSION['library_id']; ?>!</h1>
        <p>You have successfully accessed the library management dashboard.</p>
    </section>
</body>
</html>