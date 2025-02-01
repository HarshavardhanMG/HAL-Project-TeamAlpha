<?php
session_start();
require_once '../config/db_connect.php';

// Get available subjects
$subjects_sql = "SELECT * FROM subjects ORDER BY subject_code";
$subjects_result = $conn->query($subjects_sql);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $subject_code = $_POST['subject_code'];
    $subject = $_POST['subject'];
    
    $sql = "INSERT INTO teachers (name, phone, email, username, password, subject_code, subject) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssss", $name, $phone, $email, $username, $password, $subject_code, $subject);
    
    if ($stmt->execute()) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Teacher - Admin Dashboard</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2>Admin Panel</h2>
        </div>
        <ul class="nav-links">
            <li>
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            </li>
            <li>
                <a href="add_student.php"><i class="fas fa-user-graduate"></i> Add Student</a>
            </li>
            <li class="active">
                <a href="add_teacher.php"><i class="fas fa-chalkboard-teacher"></i> Add Teacher</a>
            </li>
            <li class="logout">
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-bar">
            <div class="welcome">
                <h1>Add New Teacher</h1>
                <p>Enter teacher details below</p>
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['username'] ?? 'Admin'; ?></span>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <div class="form-container">
            <form action="" method="POST" class="add-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Name</label>
                        <input type="text" name="name" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone</label>
                        <input type="tel" name="phone" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username</label>
                        <input type="text" name="username" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-code"></i> Subject Code</label>
                        <input type="text" name="subject_code" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-book"></i> Subject</label>
                        <input type="text" name="subject" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-plus-circle"></i> Add Teacher
                    </button>
                    <button type="reset" class="btn-reset">
                        <i class="fas fa-undo"></i> Reset Form
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html> 