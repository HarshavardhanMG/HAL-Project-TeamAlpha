<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit();
}

// Fetch teachers and students
$teachers = $conn->query("SELECT * FROM teachers");
$students = $conn->query("SELECT * FROM students");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2>Admin Panel</h2>
        </div>
        <ul class="nav-links">
            <li class="active">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            </li>
            <li>
                <a href="add_student.php"><i class="fas fa-user-graduate"></i> Add Student</a>
            </li>
            <li>
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
                <h1>Welcome, Admin</h1>
                <p>Manage your educational institution</p>
            </div>
            <div class="user-info">
                <span><?php echo $_SESSION['username'] ?? 'Admin'; ?></span>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Add Student Card -->
            <div class="action-card">
                <div class="card-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="card-info">
                    <h3>Add New Student</h3>
                    <p>Register a new student to the system</p>
                    <a href="add_student.php" class="card-btn">Add Student</a>
                </div>
            </div>

            <!-- Add Teacher Card -->
            <div class="action-card">
                <div class="card-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="card-info">
                    <h3>Add New Teacher</h3>
                    <p>Register a new teacher to the system</p>
                    <a href="add_teacher.php" class="card-btn">Add Teacher</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html> 