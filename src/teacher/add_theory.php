<?php
session_start();
require_once '../config/db_connect.php'; // Adjust the path as necessary

// Assuming you store teacher ID in session
$teacher_id = isset($_SESSION['teacher_id']) ? $_SESSION['teacher_id'] : null;

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['note_file']) && $teacher_id) {
    $file_name = basename($_FILES['note_file']['name']);
    $target_dir = "../uploads/notes/"; // Create this directory
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . time() . '_' . $file_name; // Add timestamp to prevent duplicate names
    
    if (move_uploaded_file($_FILES['note_file']['tmp_name'], $target_file)) {
        $file_path = $target_file;
        $stmt = $conn->prepare("INSERT INTO notes (teacher_id, file_name, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $teacher_id, $file_name, $file_path);
        $stmt->execute();
        $stmt->close();
        echo "File uploaded successfully.";
    } else {
        echo "Error uploading file.";
    }
}

// Handle file deletion
if (isset($_GET['delete_id']) && $teacher_id) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $delete_id, $teacher_id);
    $stmt->execute();
    $stmt->close();
    echo "File deleted successfully.";
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
    </head>
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
            <li>
                <a href="#" onclick="showSection('teacher-info'); return false;" id="teacher-info-link">
                    <i class="fas fa-user"></i>
                    <span>Teacher Information</span>
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2, h3 {
            color: #333;
        }
        form {
            margin-bottom: 20px;
        }
        input[type="file"] {
            margin-bottom: 10px;
        }
        button {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .notes-list ul {
            list-style-type: none;
            padding: 0;
        }
        .notes-list li {
            margin-bottom: 10px;
        }
        .notes-list a {
            color: #007bff;
            text-decoration: none;
        }
        .notes-list a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($teacher_id): ?>
            <h2>Upload and Manage Notes</h2>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="file" name="note_file" required>
                <button type="submit">Upload Note</button>
            </form>
        <?php endif; ?>
        
        <div class="notes-list">
            <h3>Available Notes</h3>
            <ul>
                <?php
                $notes_sql = "SELECT id, file_name, file_path FROM notes";
                $result = $conn->query($notes_sql);
                while ($note = $result->fetch_assoc()) {
                    echo '<li><a href="download_note.php?id=' . $note['id'] . '">' . htmlspecialchars($note['file_name']) . '</a>';
                    if ($teacher_id) {
                        echo ' <a href="?delete_id=' . $note['id'] . '">Delete</a>';
                    }
                    echo '</li>';
                }
                ?>
            </ul>
        </div>
    </div>
</body>
</html>