<?php
session_start();
require_once '../config/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM students WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $student = $result->fetch_assoc();
        $_SESSION['student_logged_in'] = true;
        $_SESSION['student_id'] = $student['id'];
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: index.php?error=1");
        exit();
    }
}
?> 