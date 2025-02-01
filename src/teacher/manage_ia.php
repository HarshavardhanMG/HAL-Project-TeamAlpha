<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['teacher_logged_in'])) {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$ia_type = $_GET['type'] ?? '';
$message = '';

// Validate IA type
if (!in_array($ia_type, ['IA-1', 'IA-2', 'IA-3'])) {
    header("Location: dashboard.php");
    exit();
}

// Get teacher's subject code
$sql = "SELECT * FROM teachers WHERE id = $teacher_id";
$result = $conn->query($sql);
$teacher = $result->fetch_assoc();
$subject_code = $teacher['subject_code'];

// Check if answer key exists
$key_sql = "SELECT * FROM answer_keys WHERE subject_code = '$subject_code' AND ia_type = '$ia_type'";
$key_result = $conn->query($key_sql);
$has_answer_key = $key_result->num_rows > 0;

// Get all students with their submission status
$students_sql = "
    SELECT 
        s.*,
        CASE 
            WHEN i.file_name IS NOT NULL THEN i.file_name
            ELSE NULL
        END as file_name,
        CASE 
            WHEN i.file_path IS NOT NULL THEN i.file_path
            ELSE NULL
        END as file_path,
        CASE 
            WHEN i.upload_date IS NOT NULL THEN i.upload_date
            ELSE NULL
        END as upload_date,
        CASE 
            WHEN i.evaluation_file_path IS NOT NULL THEN i.evaluation_file_path
            ELSE NULL
        END as evaluation_file_path,
        CASE 
            WHEN i.id IS NOT NULL THEN 'Submitted'
            ELSE 'Not Submitted'
        END as submission_status
    FROM 
        students s
    LEFT JOIN 
        ia_submissions i ON s.usn = i.usn 
        AND i.subject_code = ? 
        AND i.ia_type = ?
    ORDER BY 
        s.usn";

try {
    // Debug info
    echo "<!-- Debug:
    Subject Code: $subject_code
    IA Type: $ia_type
    -->";
    
    $stmt = $conn->prepare($students_sql);
    $stmt->bind_param("ss", $subject_code, $ia_type);
    $stmt->execute();
    $students_result = $stmt->get_result();

    // Initialize counters
    $total_students = 0;
    $submitted_count = 0;
    $submission_data = [];

    // Count submissions and store data
    while ($row = $students_result->fetch_assoc()) {
        $submission_data[] = $row;
        $total_students++;
        if ($row['submission_status'] == 'Submitted') {
            $submitted_count++;
        }
    }

    // Reset the result pointer for later use
    $students_result = $conn->prepare($students_sql);
    $stmt->bind_param("ss", $subject_code, $ia_type);
    $stmt->execute();
    $students_result = $stmt->get_result();

    if (!$students_result) {
        throw new Exception("Query failed: " . $conn->error);
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Handle answer key upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['answer_key'])) {
    $file = $_FILES['answer_key'];
    
    if ($file['type'] == 'application/pdf') {
        try {
            // Read PDF content directly from upload
            $pdf_content = file_get_contents($file['tmp_name']);
            $pdf_mime_type = $file['type'];
            $filename = $conn->real_escape_string(basename($file['name']));

            // Create temporary files with proper extensions
            $temp_pdf = tempnam(sys_get_temp_dir(), 'akpdf_') . '.pdf';
            $temp_output = tempnam(sys_get_temp_dir(), 'akext_') . '.txt';
            
            // Save PDF to temp file for processing
            file_put_contents($temp_pdf, $pdf_content);

            // Execute Python script with proper arguments
            $python_script = escapeshellarg(__DIR__ . '/../answersheet_final/reference_solution_extractor.py');
            $command = "python $python_script " . escapeshellarg($temp_pdf) . " " . escapeshellarg($temp_output) . " 2>&1";
            exec($command, $output, $return_var);

            // Validate Python script execution
            if ($return_var !== 0 || !file_exists($temp_output)) {
                throw new Exception("Answer key processing failed: " . implode("\n", $output));
            }

            // Read extracted content from temporary file
            $extracted_content = file_get_contents($temp_output);

            // Clean up temporary files
            unlink($temp_pdf);
            unlink($temp_output);

            // Prepare database query
            if ($has_answer_key) {
                $sql = "UPDATE answer_keys SET 
                        file_name = ?, 
                        pdf_data = ?, 
                        pdf_mime_type = ?,
                        extracted_text = ? 
                        WHERE subject_code = ? AND ia_type = ?";
            } else {
                $sql = "INSERT INTO answer_keys 
                        (subject_code, ia_type, file_name, pdf_data, pdf_mime_type, extracted_text) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            }

            $stmt = $conn->prepare($sql);
            $params = $has_answer_key ? 
                [$filename, $pdf_content, $pdf_mime_type, $extracted_content, $subject_code, $ia_type] :
                [$subject_code, $ia_type, $filename, $pdf_content, $pdf_mime_type, $extracted_content];

            $stmt->bind_param(str_repeat('s', count($params)), ...$params);

            if ($stmt->execute()) {
                $message = "Answer key uploaded and processed successfully!";
                $has_answer_key = true;
            } else {
                throw new Exception("Database error: " . $stmt->error);
            }

        } catch (Exception $e) {
            // Cleanup temp files if they exist
            if (isset($temp_pdf) && file_exists($temp_pdf)) unlink($temp_pdf);
            if (isset($temp_output) && file_exists($temp_output)) unlink($temp_output);
            $message = $e->getMessage();
        }
    } else {
        $message = "Please upload a valid PDF file.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage <?php echo $ia_type; ?></title>
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="../css/manage-ia.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="top-bar">
                <div class="welcome">
                    <h1>Manage <?php echo $ia_type; ?></h1>
                    <p><?php echo $teacher['subject']; ?> (<?php echo $teacher['subject_code']; ?>)</p>
                </div>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($teacher['email']); ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="top-section">
                <!-- Answer Key Upload Section -->
                <div class="upload-section">
                    <h2 class="section-title"><i class="fas fa-key"></i> Answer Key</h2>
                    <?php if ($message): ?>
                        <div class="message <?php echo strpos($message, 'success') !== false ? 'success' : 'error'; ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label><i class="fas fa-file-pdf"></i> Upload Answer Key (PDF only)</label>
                            <input type="file" name="answer_key" accept=".pdf" required>
                        </div>
                        <button type="submit" class="btn">
                            <i class="fas fa-cloud-upload-alt"></i> 
                            <?php echo $has_answer_key ? 'Update Answer Key' : 'Upload Answer Key'; ?>
                        </button>
                    </form>
                </div>

                <!-- Question Paper Upload Section -->
                <div class="upload-section">
                    <h2 class="section-title"><i class="fas fa-question-circle"></i> Question Paper</h2>
                    <form action="upload_question.php" method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label><i class="fas fa-file-pdf"></i> Upload Question Paper (PDF only)</label>
                            <input type="file" name="question_paper" accept=".pdf" required>
                        </div>
                        <input type="hidden" name="ia_type" value="<?php echo $ia_type; ?>">
                        <input type="hidden" name="subject_code" value="<?php echo $teacher['subject_code']; ?>">
                        <button type="submit" class="btn">
                            <i class="fas fa-cloud-upload-alt"></i> Upload Question Paper
                        </button>
                    </form>
                </div>

                <!-- Submission Statistics Section -->
                <div class="submission-stats">
                    <h2 class="section-title"><i class="fas fa-chart-pie"></i> Submission Statistics</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $total_students; ?></div>
                            <div>Total Students</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $submitted_count; ?></div>
                            <div>Submissions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $total_students - $submitted_count; ?></div>
                            <div>Pending</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Submissions Section -->
            <div class="submissions-section">
                <h2 class="section-title"><i class="fas fa-users"></i> Student Submissions</h2>
                <div class="evaluation-controls" style="margin-bottom: 20px;">
                    <?php if ($has_answer_key && $submitted_count > 0): ?>
                        <button onclick="startBatchEvaluation('<?php echo $ia_type; ?>', '<?php echo $teacher['subject_code']; ?>')" class="evaluate-btn">
                            <i class="fas fa-check-circle"></i> Start Batch Evaluation
                        </button>
                    <?php endif; ?>
                </div>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>USN</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Submission Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['usn']); ?></td>
                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                    <td>
                                        <div class="status <?php echo $student['submission_status'] == 'Submitted' ? 'status-uploaded' : 'status-pending'; ?>">
                                            <?php echo $student['submission_status']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $student['upload_date'] ? date('d-m-Y H:i', strtotime($student['upload_date'])) : '-'; ?>
                                    </td>
                                    <td class="action-cell">
                                        <?php if ($student['submission_status'] === 'Submitted'): ?>
                                            <button class="btn-evaluate" 
                                                    onclick="evaluateStudent('<?= $student['usn'] ?>', '<?= $subject_code ?>', '<?= $ia_type ?>')">
                                                <i class="fas fa-check-circle"></i> Evaluate
                                            </button>
                                            <div class="eval-status" id="status-<?= $student['usn'] ?>"></div>
                                        <?php else: ?>
                                            <span class="text-muted">Not submitted</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bottom-actions">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>

<script>
function startBatchEvaluation(iaType, subjectCode) {
    // Show loading indicator
    const button = event.target;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    fetch('process_evaluation.php', {
        method: 'POST',

        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            ia_type: iaType,
            subject_code: subjectCode,
            student_usn: button.getAttribute('data-usn')
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Evaluation completed successfully!');
            location.reload();
        } else {
            alert('Evaluation failed: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Evaluation error:', error);
        alert('Evaluation failed. Please check the console for details.');
    })
    .finally(() => {
        // Reset button state
        button.disabled = false;
        button.innerHTML = 'Start Evaluation Process';
    });
}

function evaluateStudent(usn, subjectCode, iaType) {
    const statusElem = document.getElementById(`status-${usn}`);
    statusElem.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Evaluating...';

    fetch('process_evaluation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            student_usn: usn,
            subject_code: subjectCode,
            ia_type: iaType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            statusElem.innerHTML = `
                <span class="text-success">
                    <i class="fas fa-check-circle"></i> 
                    Evaluated (Score: ${data.score})
                </span>
            `;
        } else {
            statusElem.innerHTML = `
                <span class="text-danger">
                    <i class="fas fa-times-circle"></i> 
                    ${data.message}
                </span>
            `;
        }
    })
    .catch(error => {
        statusElem.innerHTML = `
            <span class="text-danger">
                <i class="fas fa-times-circle"></i> 
                Evaluation failed: ${error.message}
            </span>
        `;
    });
}
</script>