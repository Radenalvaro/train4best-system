<?php
// Database connection configuration for Train4Best system
$servername = "sql310.infinityfree.com";
$username = "if0_40133653";
$password = "UuNpd4AzB7TFRXp";
$dbname = "if0_40133653_train4best_db";

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to check if user is logged in
function check_login() {
    session_start();
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        header("Location: login.php");
        exit();
    }
}

// Function to generate unique filename
function generate_filename($original_name, $prefix = '') {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $filename = $prefix . '_' . uniqid() . '_' . time() . '.' . $extension;
    return $filename;
}

// Helper function to sanitize filenames
function sanitize_filename($filename) {
    // Remove special characters and spaces, replace with underscores
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    $filename = preg_replace('/_+/', '_', $filename); // Remove multiple underscores
    return trim($filename, '_');
}

function upload_file($file, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $prefix = 'file', $report_name = null) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid parameters.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }

    if ($file['size'] > 10000000) { // 10MB limit
        throw new RuntimeException('Exceeded filesize limit.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed_types)) {
        throw new RuntimeException('Invalid file format.');
    }

    if ($report_name) {
        $sanitized_report = sanitize_filename($report_name);
        $upload_dir = 'uploads/' . $sanitized_report . '/' . basename($upload_dir);
    }

    // Create upload directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = generate_filename($file['name'], $prefix);
    $filepath = $upload_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return $filename;
}

function upload_documentation_files($files, $report_name, $section_index) {
    $sanitized_report = sanitize_filename($report_name);
    $base_dir = 'uploads/' . $sanitized_report . '/documentation';
    $document_dir = $base_dir . '/document' . ($section_index + 1);
    
    // Create directory if it doesn't exist
    if (!is_dir($document_dir)) {
        mkdir($document_dir, 0755, true);
    }
    
    $uploaded_files = [];
    $file_count = 0;
    
    // Handle multiple files
    if (is_array($files['name'])) {
        for ($i = 0; $i < count($files['name']) && $file_count < 4; $i++) {
            if ($files['error'][$i] == 0) {
                $file_data = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                $extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
                if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
                    $filename = 'img_' . ($file_count + 1) . '.' . $extension;
                    $filepath = $document_dir . '/' . $filename;
                    
                    if (move_uploaded_file($file_data['tmp_name'], $filepath)) {
                        $uploaded_files[] = [
                            'filename' => $filename,
                            'path' => $filepath,
                            'section' => $section_index + 1
                        ];
                        $file_count++;
                    }
                }
            }
        }
    }
    
    return $uploaded_files;
}
?>
