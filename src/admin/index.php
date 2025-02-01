<?php
session_start();

// Predefined admin credentials
$admin_username = "admin";
$admin_password = "admin";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['logged_in'] = true;
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid credentials!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <div class="login-section">
            <div class="login-box">
                <div class="header">
                    <h2>Admin Login</h2>
                    <p>Please enter your credentials</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username:</label>
                        <input type="text" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password:</label>
                        <input type="password" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>

                    <div class="back-link-container">
                        <a href="../index.html" class="back-link">
                            <i class="fas fa-arrow-left"></i> Back to Home
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 