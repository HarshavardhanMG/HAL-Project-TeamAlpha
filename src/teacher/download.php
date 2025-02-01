<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['teacher_logged_in'])) {
    header("Location: index.php");
    exit();
}

if (isset($_GET['file']) && isset($_GET['name'])) {
    $file_path = $_GET['file'];
    $file_name = $_GET['name'];
    
    // Validate file path to prevent directory traversal
    $real_path = realpath($file_path);
    $uploads_dir = realpath('../uploads');
    
    if ($real_path === false || strpos($real_path, $uploads_dir) !== 0) {
        die("Invalid file path");
    }
    
    if (file_exists($file_path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit();
    }
}

header("Location: dashboard.php");
exit(); 