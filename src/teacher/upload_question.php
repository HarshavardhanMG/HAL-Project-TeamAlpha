<?php
session_start();
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['question_paper'];
    
    if ($file['type'] === 'application/pdf') {
        try {
            // Read PDF content directly
            $pdf_content = file_get_contents($file['tmp_name']);
            $pdf_mime_type = $file['type'];
            $filename = $conn->real_escape_string(basename($file['name']));

            // Create temporary files with proper extensions
            $temp_pdf = tempnam(sys_get_temp_dir(), 'qpdf_') . '.pdf';
            $temp_output = tempnam(sys_get_temp_dir(), 'qext_') . '.txt';
            
            // Save PDF to temp file
            file_put_contents($temp_pdf, $pdf_content);

            // Execute Python script with proper arguments
            $python_script = escapeshellarg(__DIR__ . '/../answersheet_final/question_paper_extractor.py');
            $command = "python $python_script " . escapeshellarg($temp_pdf) . " " . escapeshellarg($temp_output) . " 2>&1";
            exec($command, $output, $return_var);

            // Validate execution
            if ($return_var !== 0 || !file_exists($temp_output)) {
                throw new Exception('Extraction failed: ' . implode("\n", $output));
            }

            // Read extracted content
            $extracted_content = file_get_contents($temp_output);

            // Clean up temp files
            unlink($temp_pdf);
            unlink($temp_output);

            // Insert into database
            $sql = "INSERT INTO question_papers 
                    (subject_code, ia_type, file_name, pdf_data, pdf_mime_type, extracted_text, teacher_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssi", 
                $_POST['subject_code'], 
                $_POST['ia_type'], 
                $filename,
                $pdf_content,
                $pdf_mime_type,
                $extracted_content,
                $_SESSION['teacher_id']
            );
            
            if ($stmt->execute()) {
                header("Location: manage_ia.php?type=" . urlencode($_POST['ia_type']) . "&success=1");
                exit();
            } else {
                throw new Exception("Database error: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            // Cleanup temp files if they exist
            if (isset($temp_pdf) && file_exists($temp_pdf)) unlink($temp_pdf);
            if (isset($temp_output) && file_exists($temp_output)) unlink($temp_output);
            
            header("Location: manage_ia.php?type=" . urlencode($_POST['ia_type']) . "&error=" . urlencode($e->getMessage()));
            exit();
        }
    } else {
        header("Location: manage_ia.php?type=" . urlencode($_POST['ia_type']) . "&error=Invalid file type");
        exit();
    }
}
?>
