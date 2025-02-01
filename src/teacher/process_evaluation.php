<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['teacher_logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $ia_type = $data['ia_type'];
    $subject_code = $data['subject_code'];
    $student_usn = $data['student_usn'];

    // Get student details
    $student_sql = "SELECT name FROM students WHERE usn = ?";
    $stmt = $conn->prepare($student_sql);
    $stmt->bind_param("s", $student_usn);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $student_name = $student['name'];

    // Get answer key content
    $answer_key_sql = "SELECT extracted_text FROM answer_keys 
                      WHERE subject_code = ? AND ia_type = ?";
    $stmt = $conn->prepare($answer_key_sql);
    $stmt->bind_param("ss", $subject_code, $ia_type);
    $stmt->execute();
    $answer_key_content = $stmt->get_result()->fetch_assoc()['extracted_text'];

    // Get question paper content
    $question_paper_sql = "SELECT extracted_text FROM question_papers 
                          WHERE subject_code = ? AND ia_type = ?";
    $stmt = $conn->prepare($question_paper_sql);
    $stmt->bind_param("ss", $subject_code, $ia_type);
    $stmt->execute();
    $question_content = $stmt->get_result()->fetch_assoc()['extracted_text'];

    // Get student submission
    $submission_sql = "SELECT extracted_text FROM ia_submissions 
                      WHERE usn = ? AND subject_code = ? AND ia_type = ?";
    $stmt = $conn->prepare($submission_sql);
    $stmt->bind_param("sss", $student_usn, $subject_code, $ia_type);
    $stmt->execute();
    $student_content = $stmt->get_result()->fetch_assoc()['extracted_text'];

    // Create temp files
    $temp_dir = sys_get_temp_dir();
    $temp_files = [
        'question' => tempnam($temp_dir, 'q_') . '.txt',
        'answer' => tempnam($temp_dir, 'a_') . '.txt',
        'student' => tempnam($temp_dir, 's_') . '.txt'
    ];

    file_put_contents($temp_files['question'], $question_content);
    file_put_contents($temp_files['answer'], $answer_key_content);
    file_put_contents($temp_files['student'], $student_content);

    // Execute Python script
    $python_script = escapeshellarg(__DIR__ . '/../answersheet_final/evaluator.py');
    $command = "python $python_script " .
               escapeshellarg($temp_files['question']) . " " .
               escapeshellarg($temp_files['answer']) . " " .
               escapeshellarg($temp_files['student']) . " 2>&1";

    exec($command, $output, $return_var);

    // Cleanup temp files
    array_map('unlink', $temp_files);

    if ($return_var !== 0) {
        throw new Exception('Evaluation failed: ' . implode("\n", $output));
    }

    // Parse evaluation result
    $evaluation_data = implode("\n", $output);
    preg_match('/Total Score: (\d+)/', $evaluation_data, $matches);
    $score = $matches[1] ?? 0;

    // Store in results table
    $result_sql = "INSERT INTO results 
                  (student_usn, student_name, subject_code, ia_type, evaluation_data, score, feedback)
                  VALUES (?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE
                  evaluation_data = VALUES(evaluation_data),
                  score = VALUES(score),
                  feedback = VALUES(feedback),
                  evaluation_date = CURRENT_TIMESTAMP";

    $stmt = $conn->prepare($result_sql);
    $null = null; // Needed for blob reference
    $stmt->bind_param("ssssbss", 
        $student_usn,
        $student_name,
        $subject_code,
        $ia_type,
        $null, // Placeholder for blob
        $score,
        $evaluation_data
    );

    // Send blob data separately
    $stmt->send_long_data(4, $evaluation_data);

    if (!$stmt->execute()) {
        throw new Exception("Database error: " . $conn->error);
    }

    // Debug: Verify stored data
    $check_sql = "SELECT LENGTH(evaluation_data) as len FROM results 
                 WHERE student_usn = ? 
                 AND subject_code = ?
                 AND ia_type = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("sss", $student_usn, $subject_code, $ia_type);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();

    if ($result['len'] < 1) {
        throw new Exception("Blob storage failed - empty evaluation data");
    }

    echo json_encode([
        'status' => 'success',
        'score' => $score,
        'message' => 'Evaluation completed successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

