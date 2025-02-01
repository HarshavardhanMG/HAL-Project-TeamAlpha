<?php
session_start();
require_once '../config/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_logged_in'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit("Unauthorized access");
}

if (isset($_GET['id'])) {
    $note_id = (int)$_GET['id'];
    
    // Get file details from database
    $stmt = $conn->prepare("SELECT file_name, file_path FROM notes WHERE id = ?");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($file_name, $file_data);
        $stmt->fetch();
        
        // Set headers for file download
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"" . basename($file_name) . "\"");
        header("Content-Length: " . strlen($file_data));
        
        echo $file_data;
        exit;
    } else {
        header("HTTP/1.0 404 Not Found");
        exit("File not found");
    }
} else {
    header("HTTP/1.0 400 Bad Request");
    exit("Invalid request");
}
?>