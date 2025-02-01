<?php
session_start();
require_once '../config/db_connect.php';

// Check for authentication
if (!isset($_SESSION['student_logged_in']) && !isset($_SESSION['teacher_logged_in'])) {
    exit('Unauthorized access');
}

if (isset($_GET['id'])) {
    $submission_id = $_GET['id'];
    
    // Add security check to ensure the logged-in student can only view their own submissions
    $sql = "SELECT pdf_data, pdf_mime_type, file_name, extracted_text FROM ia_submissions 
            WHERE id = ? AND usn = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (isset($_SESSION['student_logged_in'])) {
        $stmt->bind_param("is", $submission_id, $_SESSION['student_usn']);
    } else {
        $stmt->bind_param("i", $submission_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (isset($_GET['text'])) {
            // Output the blob content directly
            header('Content-Type: text/plain');
            echo $row['extracted_text'];
            exit;
        }
        // Set appropriate headers for PDF display
        header('Content-Type: ' . $row['pdf_mime_type']);
        header('Content-Disposition: inline; filename="' . $row['file_name'] . '"');
        echo $row['pdf_data'];
        exit;
    }
}

echo "PDF not found or access denied"; 