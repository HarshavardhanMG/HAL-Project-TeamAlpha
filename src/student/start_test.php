<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['student_logged_in'])) {
    header("Location: index.php");
    exit();
}

$student_usn = $_SESSION['student_usn'];
$test_id = $_GET['test_id'] ?? 0;

// Update the test query with correct table references
$test_sql = "SELECT tests.*, teachers.name as teacher_name,
             (SELECT COUNT(*) FROM test_questions WHERE test_id = tests.test_id) as question_count,
             (SELECT COALESCE(SUM(marks), 0) FROM test_questions WHERE test_id = tests.test_id) as total_marks
             FROM tests 
             JOIN teachers ON tests.teacher_id = teachers.id 
             WHERE tests.test_id = ?";
$stmt = $conn->prepare($test_sql);
$stmt->bind_param("i", $test_id);
$stmt->execute();
$test_result = $stmt->get_result();
$test = $test_result->fetch_assoc();

if (!$test) {
    header("Location: take_test.php");
    exit();
}

// Get all questions and their options for this test
$questions_sql = "SELECT q.question_id, q.question_text, q.marks, q.question_type,
                        o.option_id, o.option_text, o.is_correct
                 FROM test_questions q
                 LEFT JOIN question_options o ON q.question_id = o.question_id
                 WHERE q.test_id = ?";
$q_stmt = $conn->prepare($questions_sql);
$q_stmt->bind_param("i", $test_id);
$q_stmt->execute();
$questions_result = $q_stmt->get_result();

// Organize questions and options
$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    $q_id = $row['question_id'];
    if (!isset($questions[$q_id])) {
        $questions[$q_id] = [
            'id' => $q_id,
            'question_text' => $row['question_text'],
            'marks' => $row['marks'],
            'question_type' => $row['question_type'],
            'options' => []
        ];
    }
    
    // Add option if it exists
    if ($row['option_id']) {
        $questions[$q_id]['options'][] = [
            'id' => $row['option_id'],
            'text' => $row['option_text'],
            'is_correct' => $row['is_correct']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taking Test - <?php echo htmlspecialchars($test['test_name']); ?></title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .test-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 30px;
        }
        .test-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        .test-header:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border-color: #2196F3;
        }
        .test-header h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        .test-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .test-info p {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 1.1rem;
        }
        .test-info i {
            color: #2196F3;
            width: 24px;
            text-align: center;
        }
        .question-card {
            background: white;
            width: 500px;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }
        .question-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            border-color: #2196F3;
        }
        .question-card h3 {
            color: #333;
            font-size: 1.3rem;
            margin-bottom: 20px;
        }
        .marks {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .options-list {
            display: grid;
            gap: 15px;
        }
        .option-item {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        .option-item:hover {
            background: #e8f4fe;
            border-color: #2196F3;
        }
        .option-item input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.2);
        }
        .option-item label {
            cursor: pointer;
            flex: 1;
            font-size: 1.1rem;
            color: #444;
        }
        .timer {
            position: fixed;
            top: 30px;
            right: 30px;
            background: linear-gradient(45deg, #2196F3, #1976D2);
            color: white;
            padding: 15px 25px;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
            font-size: 1.2rem;
            z-index: 1000;
            animation: pulse 2s infinite;
        }
        .submit-btn {
            background: linear-gradient(45deg, #2196F3, #1976D2);
            color: white;
            padding: 15px 30px;
            border-radius: 25px;
            border: none;
            font-size: 1.1rem;
            cursor: pointer;
            display: block;
            margin: 40px auto;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
            background: linear-gradient(45deg, #1976D2, #2196F3);
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(33, 150, 243, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(33, 150, 243, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(33, 150, 243, 0);
            }
        }
        .question-card:nth-child(1) { animation-delay: 0.1s; }
        .question-card:nth-child(2) { animation-delay: 0.2s; }
        .question-card:nth-child(3) { animation-delay: 0.3s; }
        .question-card:nth-child(4) { animation-delay: 0.4s; }
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

    <div class="timer" id="timer"></div>
    
    <div class="test-container">
        <h1><?php echo htmlspecialchars($test['test_name']); ?></h1>
        <div class="test-info">
            <p><i class="fas fa-user-tie"></i> Teacher: <?php echo htmlspecialchars($test['teacher_name']); ?></p>
            <p><i class="fas fa-clock"></i> Duration: <?php echo htmlspecialchars($test['duration']); ?> minutes</p>
        </div>

        <form action="submit_test.php" method="POST" id="testForm">
            <input type="hidden" name="test_id" value="<?php echo $test_id; ?>">
            
            <?php foreach ($questions as $question): ?>
                <div class="question-card">
                    <h3><?php echo htmlspecialchars($question['question_text']); ?></h3>
                    <p class="marks">Marks: <?php echo htmlspecialchars($question['marks']); ?></p>
                    
                    <div class="options-list">
                        <?php foreach ($question['options'] as $option): ?>
                            <div class="option-item">
                                <input type="radio" 
                                       name="answers[<?php echo $question['id']; ?>]" 
                                       value="<?php echo $option['id']; ?>" 
                                       id="opt_<?php echo $option['id']; ?>" 
                                       required>
                                <label for="opt_<?php echo $option['id']; ?>">
                                    <?php echo htmlspecialchars($option['text']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="submit-btn">
                <i class="fas fa-paper-plane"></i> Submit Test
            </button>
        </form>
    </div>

    <script>
        // Timer functionality
        let timeLeft = <?php echo $test['duration'] * 60; ?>;
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('timer').innerHTML = 
                `Time Left: ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            
            if (timeLeft === 0) {
                document.getElementById('testForm').submit();
            } else {
                timeLeft--;
                setTimeout(updateTimer, 1000);
            }
        }

        updateTimer();
    </script>
</body>
</html> 