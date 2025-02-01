<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['teacher_logged_in'])) {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process form submission
    $test_name = $_POST['test_name'];
    $duration = $_POST['duration'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert test
        $test_sql = "INSERT INTO tests (teacher_id, test_name, test_type, duration) VALUES (?, ?, 'mcq', ?)";
        $test_stmt = $conn->prepare($test_sql);
        $test_stmt->bind_param("isi", $teacher_id, $test_name, $duration);
        $test_stmt->execute();
        $test_id = $conn->insert_id;
        
        // Insert questions and options
        $question_sql = "INSERT INTO test_questions (test_id, question_text, question_type, marks) VALUES (?, ?, 'mcq', ?)";
        $option_sql = "INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)";
        
        foreach ($_POST['questions'] as $index => $question) {
            // Insert question
            $q_stmt = $conn->prepare($question_sql);
            $q_stmt->bind_param("isi", $test_id, $question, $_POST['marks'][$index]);
            $q_stmt->execute();
            $question_id = $conn->insert_id;
            
            // Insert options
            $opt_stmt = $conn->prepare($option_sql);
            foreach ($_POST['options'][$index] as $opt_index => $option) {
                // Subtract 1 from correct_answer to match 0-based array indexing
                $correct_answer = $_POST['correct_answer'][$index] - 1;
                $is_correct = ($opt_index == $correct_answer) ? 1 : 0;
                $opt_stmt->bind_param("isi", $question_id, $option, $is_correct);
                $opt_stmt->execute();
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "MCQ Test created successfully!";
        header("Location: dashboard.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error creating test: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add MCQ Test</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .question-block {
            background: #f9f9f9;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .option-group {
            margin: 10px 0;
        }
        .add-question-btn {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 20px 0;
        }
        .remove-question-btn {
            background: #f44336;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
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

    <div class="main-content">
        <div class="top-bar">
            <div class="welcome">
                <h1>Create MCQ Test</h1>
                <p>Add multiple choice questions</p>
            </div>
        </div>

        <div class="form-container">
            <form method="POST" action="" id="mcqForm">
                <div class="form-group">
                    <label>Test Name</label>
                    <input type="text" name="test_name" required>
                </div>

                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration" required>
                </div>

                <div id="questions-container">
                    <!-- Question blocks will be added here -->
                </div>

                <button type="button" class="add-question-btn" onclick="addQuestion()">
                    <i class="fas fa-plus"></i> Add Question
                </button>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Create Test
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addQuestion() {
            const container = document.getElementById('questions-container');
            const questionIndex = container.children.length;
            
            const questionBlock = document.createElement('div');
            questionBlock.className = 'question-block';
            questionBlock.innerHTML = `
                <div class="form-group">
                    <label>Question ${questionIndex + 1}</label>
                    <input type="text" name="questions[]" required>
                    <input type="number" name="marks[]" placeholder="Marks" required>
                    <button type="button" class="remove-question-btn" onclick="removeQuestion(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="option-group">
                    <label>Options:</label>
                    <input type="text" name="options[${questionIndex}][]" placeholder="Option 1" required>
                    <input type="text" name="options[${questionIndex}][]" placeholder="Option 2" required>
                    <input type="text" name="options[${questionIndex}][]" placeholder="Option 3" required>
                    <input type="text" name="options[${questionIndex}][]" placeholder="Option 4" required>
                </div>
                <div class="form-group">
                    <label>Correct Answer (1-4):</label>
                    <input type="number" name="correct_answer[]" min="1" max="4" required>
                </div>
            `;
            
            container.appendChild(questionBlock);
        }

        function removeQuestion(button) {
            button.closest('.question-block').remove();
        }

        // Add first question by default
        document.addEventListener('DOMContentLoaded', function() {
            addQuestion();
        });
    </script>
</body>
</html> 