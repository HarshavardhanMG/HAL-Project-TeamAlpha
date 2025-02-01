<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['student_logged_in'])) {
    header("Location: index.php");
    exit();
}

$student_usn = $_SESSION['student_usn'];
$subject_code = $_GET['code'] ?? '';

// Get student information
$sql = "SELECT * FROM students WHERE usn = '$student_usn'";
$result = $conn->query($sql);
$student = $result->fetch_assoc();

// Get subject information
$subject_sql = "SELECT * FROM subjects WHERE subject_code = '$subject_code'";
$subject_result = $conn->query($subject_sql);
$subject = $subject_result->fetch_assoc();

if (!$subject) {
    header("Location: dashboard.php");
    exit();
}

// Get IA submissions for this student and subject
$ia_types = ['IA-1', 'IA-2', 'IA-3'];
$submissions = [];

foreach ($ia_types as $ia) {
    $sub_sql = "SELECT * FROM ia_submissions WHERE usn = '$student_usn' AND subject_code = '$subject_code' AND ia_type = '$ia'";
    $sub_result = $conn->query($sub_sql);
    $submissions[$ia] = $sub_result->num_rows > 0 ? $sub_result->fetch_assoc() : null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $subject['subject_name']; ?> IAs</title>
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
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
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
                <h1><?php echo htmlspecialchars($subject['subject_name']); ?></h1>
                <p>Manage your IA submissions for <?php echo htmlspecialchars($subject['subject_code']); ?></p>
            </div>
            <div class="user-info">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Subjects
                </a>
            </div>
        </div>

        <!-- IA Submissions Grid -->
        <div class="dashboard-grid">
            <?php foreach ($ia_types as $ia): ?>
            <div class="action-card">
                <div class="card-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="card-info">
                    <h3><?php echo $ia; ?></h3>
                    <?php if (isset($submissions[$ia])): ?>
                        <p class="status submitted">Submitted</p>
                        <div class="action-buttons">
                            <a href="view_pdf.php?id=<?php echo $submissions[$ia]['id']; ?>" 
                               target="_blank" 
                               class="btn btn-view">
                                <i class="fas fa-eye"></i> View Submission
                            </a>
                            <a href="upload_ia.php?type=<?php echo urlencode($ia); ?>&subject=<?php echo urlencode($subject_code); ?>" 
                               class="btn btn-resubmit">
                                <i class="fas fa-redo"></i> Resubmit
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="status pending">Not Submitted</p>
                        <a href="upload_ia.php?type=<?php echo urlencode($ia); ?>&subject=<?php echo urlencode($subject_code); ?>" 
                           class="btn">
                            <i class="fas fa-upload"></i> Submit <?php echo $ia; ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html> 