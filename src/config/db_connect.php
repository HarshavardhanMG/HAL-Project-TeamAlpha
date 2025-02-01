<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "school";

// Create connection with adjusted timeout settings
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timeout and packet size
$conn->query("SET GLOBAL max_allowed_packet=67108864"); // 64MB
$conn->query("SET GLOBAL net_buffer_length=32768");
$conn->query("SET GLOBAL interactive_timeout=28800");
$conn->query("SET GLOBAL wait_timeout=28800");

// Set the SQL mode to handle strict requirements
$conn->query("SET SESSION sql_mode=''");
?> 