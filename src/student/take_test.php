<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['student_logged_in'])) {
    header("Location: index.php");
    exit();
}

$student_usn = $_SESSION['student_usn'];
$sql = "SELECT * FROM students WHERE usn = '$student_usn'";
$result = $conn->query($sql);
$student = $result->fetch_assoc();

// Fetch all tests with their questions
$test_sql = "SELECT tests.*, teachers.name as teacher_name,
             (SELECT COUNT(*) FROM test_questions WHERE test_id = tests.test_id) as question_count,
             (SELECT COALESCE(SUM(marks), 0) FROM test_questions WHERE test_id = tests.test_id) as total_marks
             FROM tests 
             JOIN teachers ON tests.teacher_id = teachers.id 
             ORDER BY tests.created_at DESC";
$tests_result = $conn->query($test_sql);

if (!$tests_result) {
    die("Error fetching tests: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Test</title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .test-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 40px;
        }
        .test-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            border: 1px solid #e0e0e0;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }
        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            border-color: #2196F3;
        }
        .test-card h3 {
            color: #333;
            font-size: 1.5rem;
            margin-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .test-info {
            margin: 15px 0;
        }
        .test-info p {
            margin: 8px 0;
            color: #666;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .test-info i {
            color: #2196F3;
            width: 20px;
            text-align: center;
        }
        .take-test-btn {
            background: linear-gradient(45deg, #2196F3, #1976D2);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        .take-test-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
            background: linear-gradient(45deg, #1976D2, #2196F3);
        }
        .marks-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 0.9em;
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
        .test-card:nth-child(1) { animation-delay: 0.1s; }
        .test-card:nth-child(2) { animation-delay: 0.2s; }
        .test-card:nth-child(3) { animation-delay: 0.3s; }
        .test-card:nth-child(4) { animation-delay: 0.4s; }
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="welcome">
                <h1>Available Tests</h1>
                <p>Select a test to begin</p>
            </div>
            <div class="user-info">
                <span><?php echo htmlspecialchars($student['usn']); ?></span>
            </div>
        </div>

        <!-- Tests Section -->
        <div class="tests-container">
            <?php if ($tests_result && $tests_result->num_rows > 0): ?>
                <?php while ($test = $tests_result->fetch_assoc()): 
                    // Fetch questions for this test
                    $questions_sql = "SELECT question_id, question_text, marks FROM test_questions WHERE test_id = ? LIMIT 3";
                    $stmt = $conn->prepare($questions_sql);
                    $stmt->bind_param("i", $test['test_id']);
                    $stmt->execute();
                    $questions = $stmt->get_result();
                ?>
                    <div class="test-card">
                        <h3><?php echo htmlspecialchars($test['test_name']); ?></h3>
                        <div class="test-info">
                            <p><i class="fas fa-user-tie"></i> Teacher: <?php echo htmlspecialchars($test['teacher_name']); ?></p>
                            <p><i class="fas fa-clock"></i> Duration: <?php echo htmlspecialchars($test['duration']); ?> minutes</p>
                            <p><i class="fas fa-list"></i> Type: <?php echo ucfirst(htmlspecialchars($test['test_type'])); ?></p>
                            <p><i class="fas fa-question-circle"></i> Total Questions: <?php echo $test['question_count']; ?></p>
                            <p><i class="fas fa-star"></i> Total Marks: <?php echo $test['total_marks']; ?></p>
                        </div>

                        <div class="question-preview">
                            <h4>Sample Questions:</h4>
                            <?php while ($question = $questions->fetch_assoc()): ?>
                                <p>
                                    • <?php echo htmlspecialchars($question['question_text']); ?>
                                    <span class="marks-badge"><?php echo $question['marks']; ?> marks</span>
                                </p>
                            <?php endwhile; ?>
                        </div>

                        <?php if ($test['question_count'] > 0): ?>
                            <a href="start_test.php?test_id=<?php echo $test['test_id']; ?>" class="take-test-btn">
                                Start Test <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <p class="no-questions">No questions available for this test.</p>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-tests">
                    <p>No tests available at the moment.</p>
                </div>
            <?php endif; ?>
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