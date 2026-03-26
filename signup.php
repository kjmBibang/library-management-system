<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | BryceLibrary</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">

    <div class="login-container">
        <div class="login-box">
            <a href="index.php" class="back-home">&larr; Back to Home</a>
            
            <div class="login-header">
                <h2>Create Account</h2>
                <p>Join the BryceLibrary community</p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div style="color: #27ae60; background: #eafaf1; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; border: 1px solid #27ae60;">
                    Registration successful! <a href="login.php">Login here</a>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] == 'exists'): ?>
                <div style="color: #e74c3c; background: #fdeaea; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; border: 1px solid #e74c3c;">
                    Username already registered.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid_role'): ?>
                <div style="color: #e74c3c; background: #fdeaea; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; border: 1px solid #e74c3c;">
                    Invalid role selected.
                </div>
            <?php endif; ?>

            <form action="handlers/auth/process_signup.php" method="POST">
                <div class="input-group">
                    <label for="username">Create Username</label>
                    <input type="text" id="username" name="username" placeholder="e.g. john_doe" required>
                </div>

                <div class="input-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="staff" selected>Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>

                <button type="submit" class="login-submit">Register</button>
            </form>

            <div class="login-footer">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>

</body>
</html>