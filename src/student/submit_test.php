<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['student_logged_in'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_usn = $_SESSION['student_usn'];
    $test_id = $_POST['test_id'];
    $answers = $_POST['answers'] ?? [];
    
    // Calculate score
    $total_score = 0;
    $total_marks = 0;
    
    foreach ($answers as $question_id => $selected_option_id) {
        // Get question marks and correct answer
        $q_sql = "SELECT q.marks, o.is_correct 
                  FROM test_questions q 
                  JOIN question_options o ON o.question_id = q.question_id 
                  WHERE q.question_id = ? AND o.option_id = ?";
        $stmt = $conn->prepare($q_sql);
        $stmt->bind_param("ii", $question_id, $selected_option_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        // If correct answer, add to score
        if ($result && $result['is_correct']) {
            $total_score += $result['marks'];
        }
        if ($result) {
            $total_marks += $result['marks'];
        }
    }
    
    // Get student ID from USN
    $student_sql = "SELECT id FROM students WHERE usn = ?";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bind_param("s", $student_usn);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result()->fetch_assoc();
    $student_id = $student_result['id'];
    
    // Store the submission
    $submission_sql = "INSERT INTO test_submissions (test_id, student_id, submission_time, score, status) 
                      VALUES (?, ?, NOW(), ?, 'completed')";
    $sub_stmt = $conn->prepare($submission_sql);
    $sub_stmt->bind_param("iii", $test_id, $student_id, $total_score);
    $sub_stmt->execute();
    
    // Store result in session for display
    $_SESSION['test_result'] = [
        'score' => $total_score,
        'total' => $total_marks
    ];
    
    header("Location: test_result.php");
    exit();
}

header("Location: take_test.php");
exit(); 