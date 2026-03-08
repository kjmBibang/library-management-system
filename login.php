<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | BryceLibrary</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">

    <div class="login-container">
        <div class="login-box">
            <a href="index.php" class="back-home">&larr; Back to Home</a>
            
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Please enter your library credentials</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div style="background: #fdeaea; color: #e74c3c; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 0.85rem; text-align: center; border: 1px solid #e74c3c;">
                    <?php 
                        if($_GET['error'] == "wrong_password") echo "Incorrect password. Please try again.";
                        elseif($_GET['error'] == "user_not_found") echo "Library ID not found.";
                        else echo "An error occurred. Please try again.";
                    ?>
                </div>
            <?php endif; ?>

            <form action="process_login.php" method="POST">
                <div class="input-group">
                    <label for="username">Library ID / Email</label>
                    <input type="text" id="username" name="username" placeholder="e.g. LIB-9921" required>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="login-submit">Sign In</button>
            </form>

            <div class="login-footer">
                <p>Forgot password? <a href="#">Contact Librarian</a></p>
            </div>
        </div>
    </div>

</body>
</html>