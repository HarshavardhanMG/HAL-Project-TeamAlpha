<?php
session_start();
require_once '../config/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_logged_in'])) {
    header("Location: index.php");
    exit();
}

// Fetch note details
$notes_sql = "SELECT id, file_name, upload_date FROM notes";
$notes_result = $conn->query($notes_sql);
?>

<!DOCTYPE html>
<html lang="en">
</head>
<link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            <li>
                <a href="#" onclick="showStudentInfo(); return false;">
                    <i class="fas fa-user"></i>
                    <span>Student Information</span>
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

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
        }

        .notes-container {
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .notes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .note-card {
            background-color: #ffffff;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .note-card:hover {
            transform: translateY(-5px);
        }

        .note-card h3 {
            margin-top: 0;
            font-size: 1.2em;
            color: #333;
        }

        .note-card p {
            color: #666;
            font-size: 0.9em;
        }

        .download-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 15px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .download-btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="notes-container">
        <h1>Available Notes</h1>
        <div class="notes-grid">
            <?php if ($notes_result->num_rows > 0): ?>
                <?php while ($note = $notes_result->fetch_assoc()): ?>
                    <div class="note-card">
                        <h3><?php echo htmlspecialchars($note['file_name']); ?></h3>
                        <p>Uploaded on: <?php echo htmlspecialchars(date('F j, Y', strtotime($note['upload_date']))); ?></p>
                        <a href="download_note.php?id=<?php echo $note['id']; ?>" class="download-btn">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No notes available</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>