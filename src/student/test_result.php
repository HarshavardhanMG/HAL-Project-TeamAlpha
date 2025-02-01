<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['student_logged_in']) || !isset($_SESSION['test_result'])) {
    header("Location: dashboard.php");
    exit();
}

$result = $_SESSION['test_result'];
unset($_SESSION['test_result']); // Clear the result from session
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Result</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .result-card {
            background: white;
            border-radius: 8px;
            padding: 40px;
            margin: 50px auto;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .score {
            font-size: 48px;
            color: #2196F3;
            margin: 20px 0;
        }
        .back-btn {
            background: #2196F3;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        
    </style>
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
            <li class="active">
                <a href="take_test.php">
                    <i class="fas fa-pen"></i>
                    <span>Take Test</span>
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

    <div class="main-content">
        <div class="result-card">
            <h1>Test Completed!</h1>
            <div class="score">
                <?php echo $result['score']; ?>/<?php echo $result['total']; ?>
            </div>
            <p>Your score has been recorded.</p>
            <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>
    </div>

    <!-- Add the showStudentInfo function to both files -->
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