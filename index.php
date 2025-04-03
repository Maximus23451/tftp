<?php
// Start session at the VERY TOP
session_start();

// Process form submission before any HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tftpServer = $_POST['tftp_server'] ?? '';
    $filename = $_POST['filename'] ?? '';
    $uploadFile = $_FILES['file']['tmp_name'] ?? '';

    try {
        // Validate required fields
        if (empty($tftpServer) || empty($filename) || empty($uploadFile)) {
            throw new Exception("All fields are required.");
        }

        // Validate file upload status
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $_FILES['file']['error']);
        }

        // Security Check: Validate File Type
        $allowedExtensions = ['txt', 'bin', 'cfg'];
        $fileExt = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExtensions)) {
            throw new Exception("Invalid file type. Allowed: " . implode(", ", $allowedExtensions));
        }

        // Secure File Storage
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $safeFilename = basename($_FILES['file']['name']);
        $filePath = $uploadDir . $safeFilename;

        if (!move_uploaded_file($uploadFile, $filePath)) {
            throw new Exception("Failed to process uploaded file.");
        }

        // Execute TFTP Command Securely
        $escapedTftpServer = escapeshellarg($tftpServer);
        $escapedFilePath = escapeshellarg($filePath);
        $escapedFilename = escapeshellarg($filename);

        $command = "tftp $escapedTftpServer -c put $escapedFilePath $escapedFilename";
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("TFTP transfer failed: " . implode("\n", $output));
        }

        $_SESSION['status'] = [
            'type' => 'success',
            'message' => 'File transferred successfully!'
        ];
    } catch (Exception $e) {
        $_SESSION['status'] = [
            'type' => 'error',
            'message' => htmlspecialchars($e->getMessage())
        ];
    }

    // Redirect to avoid resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TFTP File Upload</title>
    <!-- Keep the existing dark theme styles -->
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 2rem auto;
            padding: 0 1rem;
            background-color: #1a1a1a;
            color: #e0e0e0;
        }
        .container {
            border: 1px solid #333;
            padding: 2rem;
            border-radius: 5px;
            background-color: #2d2d2d;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        input, button {
            width: 100%;
            padding: 0.5rem;
            margin-top: 0.3rem;
            background-color: #404040;
            border: 1px solid #333;
            color: #e0e0e0;
        }
        button {
            background: #0066cc;
            color: white;
            border: none;
            cursor: pointer;
            padding: 0.7rem;
            transition: background 0.3s;
        }
        button:hover {
            background: #0052a3;
        }
        .status {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 4px;
            font-weight: bold;
        }
        .success {
            background: #004d00;
            color: #90ee90;
            border: 1px solid #008000;
        }
        .error {
            background: #4d0000;
            color: #ff9999;
            border: 1px solid #cc0000;
        }    
		</style>
</head>
<body>
    <div class="container">
        <h1>TFTP File Upload</h1>

        <?php if (!empty($_SESSION['status'])): ?>
            <div class="status <?= $_SESSION['status']['type'] ?>">
                <?= $_SESSION['status']['message'] ?>
            </div>
            <?php unset($_SESSION['status']); ?>
        <?php endif; ?>

        <<form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>TFTP Server:</label>
                <input type="text" name="tftp_server" required 
                    placeholder="TFTP server IP/hostname">
            </div>
            
            <div class="form-group">
                <label>Remote Filename:</label>
                <input type="text" name="filename" required 
                    placeholder="Destination filename">
            </div>
            
            <div class="form-group">
                <label>Select File:</label>
                <input type="file" name="file" required>
            </div>
            
            <button type="submit">Upload via TFTP</button>
        </form>
    </div>
</body>
</html>
