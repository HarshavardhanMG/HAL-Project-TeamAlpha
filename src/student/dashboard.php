<?php
session_start();
require_once '../config/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_logged_in'])) {
    header("Location: index.php");
    exit();
}

// Get student information using student_usn
$student_usn = $_SESSION['student_usn'];
$sql = "SELECT * FROM students WHERE usn = '$student_usn'";
$result = $conn->query($sql);
$student = $result->fetch_assoc();

// Get all subjects
$subjects_sql = "SELECT * FROM subjects ORDER BY subject_code";
$subjects_result = $conn->query($subjects_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
<body class="dashboard-body">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Student Portal</h2>
        </div>
        <ul class="nav-links">
            <li class="active">
                <a href="dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="take_test.php">
                    <i class="fas fa-pen"></i>
                    <span>Take Test</span>
                </a>
            </li>
            <li>
                <a href="notes.php" return false;" id="notes-link">
                    <i class="fas fa-book"></i>
                    <span>Notes</span>
                </a>
            </li>
            
            <li class="logout">
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="welcome">
                <h1>Welcome, <?php echo htmlspecialchars($student['name']); ?>!</h1>
                <p>Select a subject to view and manage your IA submissions</p>
            </div>
            <div class="user-info">
                <span><?php echo htmlspecialchars($student['usn']); ?></span>
            </div>
        </div>

        <!-- Subjects Grid -->
        <div class="dashboard-grid">
            <?php while ($subject = $subjects_result->fetch_assoc()): ?>
            <div class="action-card">
                <div class="card-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="card-info">
                    <h3><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                    <p class="subject-code"><?php echo htmlspecialchars($subject['subject_code']); ?></p>
                    <a href="subject_ia.php?code=<?php echo urlencode($subject['subject_code']); ?>" class="btn">
                        View IAs
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        function showStudentInfo() {
            alert("Student Information:\n" + 
                  "Name: <?php echo $student['name']; ?>\n" + 
                  "USN: <?php echo $student['usn']; ?>\n" + 
                  "Email: <?php echo $student['email']; ?>");
        }
    </script>
</body>
</html> 