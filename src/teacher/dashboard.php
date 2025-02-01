<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['teacher_logged_in'])) {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$sql = "SELECT * FROM teachers WHERE id = $teacher_id";
$result = $conn->query($sql);
$teacher = $result->fetch_assoc();
$subject_code = $teacher['subject_code'];

$ia_types = ['IA-1', 'IA-2', 'IA-3'];
$answer_keys = [];

foreach ($ia_types as $ia) {
    $check_sql = "SELECT * FROM answer_keys WHERE subject_code = '$subject_code' AND ia_type = '$ia'";
    $check_result = $conn->query($check_sql);
    $answer_keys[$ia] = $check_result->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .teacher-info-section {
            display: none;
        }
        .teacher-info-section.active {
            display: block;
        }
        .dashboard-section {
            display: none;
        }
        .dashboard-section.active {
            display: block;
        }
    </style>
<</head>
<body class="dashboard-body">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Teacher Portal</h2>
        </div>
        <ul class="nav-links">
            <li class="active">
                <a href="dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="add_mcq.php">
                    <i class="fas fa-question-circle"></i>
                    <span>Add MCQ Test</span>
                </a>
            </li>
            <li>
                <a href="add_theory.php">
                    <i class="fas fa-file-alt"></i>
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
                <h1>Welcome, <?php echo htmlspecialchars($teacher['name']); ?>!</h1>
                <p>Manage your Internal Assessments</p>
            </div>
            <div class="user-info">
                <span><?php echo htmlspecialchars($teacher['email']); ?></span>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Dashboard Section -->
        <div class="dashboard-section active" id="dashboard-section">
            <div class="dashboard-grid">
                <?php foreach ($ia_types as $ia): ?>
                <div class="action-card">
                    <div class="card-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="card-info">
                        <h3><?php echo $ia; ?> Management</h3>
                        <?php if ($answer_keys[$ia]): ?>
                            <p class="status success">Answer Key Uploaded</p>
                        <?php else: ?>
                            <p class="status pending">Answer Key Pending</p>
                        <?php endif; ?>
                        <a href="manage_ia.php?type=<?php echo urlencode($ia); ?>" class="card-btn">
                            Manage <?php echo $ia; ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Teacher Information Section -->
        <div class="teacher-info-section" id="teacher-info-section">
            <div class="recent-section">
                <h2>Teacher Information</h2>
                <div class="table-container">
                    <table>
                        <tr>
                            <th>Name</th>
                            <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Subject</th>
                            <td><?php echo htmlspecialchars($teacher['subject']); ?></td>
                        </tr>
                        <tr>
                            <th>Subject Code</th>
                            <td><?php echo htmlspecialchars($teacher['subject_code']); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.dashboard-section, .teacher-info-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all nav links
            document.querySelectorAll('.nav-links li a').forEach(link => {
                link.parentElement.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').classList.add('active');
            
            // Add active class to clicked link
            document.getElementById(sectionName + '-link').parentElement.classList.add('active');
        }

        // Set dashboard as active by default
        document.getElementById('dashboard-link').parentElement.classList.add('active');
    </script>
</body>
</html> 