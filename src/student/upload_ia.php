<?php
// Increase PHP limits
ini_set('upload_max_filesize', '64M');
ini_set('post_max_size', '64M');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '300'); // 5 minutes
ini_set('max_input_time', '300'); // 5 minutes

session_start();
require_once '../config/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['student_logged_in'])) {
    header("Location: index.php");
    exit();
}

// Get parameters from URL
$ia_type = $_GET['type'] ?? '';
$subject_code = $_GET['subject'] ?? '';
$student_usn = $_SESSION['student_usn'];
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['ia_file'])) {
    $file = $_FILES['ia_file'];
    
    // Validate file type and size
    if ($file['type'] == 'application/pdf') {
        if ($file['size'] > 64 * 1024 * 1024) { // 64MB limit
            $message = "File is too large. Maximum size is 64MB.";
        } else {
            try {
                // Read PDF file content
                $pdf_content = file_get_contents($file['tmp_name']);
                $pdf_mime_type = $file['type'];
                $filename = $student_usn . '_' . $subject_code . '_' . $ia_type . '_' . time() . '.pdf';
                
                // Save PDF temporarily for processing
                $temp_pdf_path = sys_get_temp_dir() . '/' . $filename;
                file_put_contents($temp_pdf_path, $pdf_content);
                
                // Run Python script to extract content
                $command = "python ../answersheet_final/answer_sheet_extractor.py " . escapeshellarg($temp_pdf_path) . " 2>&1";
                exec($command, $output, $return_var);
                
                // Log the command and output for debugging
                error_log("Command executed: " . $command);
                error_log("Command output: " . implode("\n", $output));
                error_log("Return value: " . $return_var);
                
                if ($return_var !== 0) {
                    throw new Exception('Failed to extract answer sheet content. Error: ' . implode("\n", $output));
                }
                
                // Read extracted text as binary data
                if (!file_exists("student_answer.txt")) {
                    throw new Exception('Output file student_answer.txt not found');
                }
                $extracted_content = file_get_contents("student_answer.txt");
                unlink("student_answer.txt");  // Remove temporary file
                
                // Check if submission already exists
                $check_sql = "SELECT id FROM ia_submissions WHERE usn = ? AND subject_code = ? AND ia_type = ?";
                $stmt = $conn->prepare($check_sql);
                $stmt->bind_param("sss", $student_usn, $subject_code, $ia_type);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing submission
                    $update_sql = "UPDATE ia_submissions 
                                SET file_name = ?, 
                                    pdf_data = ?,
                                    pdf_mime_type = ?,
                                    extracted_text = ?,
                                    upload_date = CURRENT_TIMESTAMP
                                WHERE usn = ? AND subject_code = ? AND ia_type = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("sssssss", 
                                    $filename, 
                                    $pdf_content, 
                                    $pdf_mime_type,
                                    $extracted_content,  // Store as blob
                                    $student_usn, 
                                    $subject_code, 
                                    $ia_type);
                } else {
                    // Insert new submission
                    $insert_sql = "INSERT INTO ia_submissions 
                                (usn, subject_code, ia_type, file_name, pdf_data, pdf_mime_type, extracted_text) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($insert_sql);
                    $stmt->bind_param("sssssss", 
                                    $student_usn, 
                                    $subject_code, 
                                    $ia_type, 
                                    $filename, 
                                    $pdf_content, 
                                    $pdf_mime_type,
                                    $extracted_content);  // Store as blob
                }
                
                if ($stmt->execute()) {
                    $message = "File uploaded successfully!";
                    header("Location: subject_ia.php?subject=" . urlencode($subject_code) . "&success=1");
                    exit();
                } else {
                    throw new Exception("Error saving to database: " . $stmt->error);
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                error_log("Answer sheet processing error: " . $e->getMessage());
                // Clean up temporary files in case of error
                if (file_exists($temp_pdf_path)) {
                    unlink($temp_pdf_path);
                }
            }
        }
    } else {
        $message = "Please upload a PDF file only.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload <?php echo htmlspecialchars($ia_type); ?></title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/upload.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="main-content">
        <div class="content-wrapper">
            <div class="upload-container">
                <h2 class="page-title">Upload <?php echo htmlspecialchars($ia_type); ?></h2>
                
                <?php if ($message): ?>
                <div class="alert <?php echo strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data" class="upload-form">
                    <div class="form-group">
                        <label for="ia_file">Choose PDF File:</label>
                        <input type="file" 
                               id="ia_file" 
                               name="ia_file" 
                               accept=".pdf" 
                               required>
                        <small class="file-hint">Only PDF files are allowed (Max size: 64MB)</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                        <a href="subject_ia.php?subject=<?php echo urlencode($subject_code); ?>" 
                           class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 