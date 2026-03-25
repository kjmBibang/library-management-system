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

            <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid'): ?>
                <div class="error-alert">
                    Invalid Username or Password.
                </div>
            <?php endif; ?>

            <form action="process_login.php" method="POST">
                <div class="input-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="e.g. john_doe" required>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="login-submit">Sign In</button>
            </form>

            <div class="login-footer">
                <p>Dont have an account? <a href="signup.php">Sign Up</a></p>
            </div>
        </div>
    </div>

</body>
</html>