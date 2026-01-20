<?php
/**
 * File Upload Handler for cPanel
 * 
 * This script receives file uploads from the Vercel backend via HTTP POST
 * and saves them to the uploads directory on cPanel.
 * 
 * Security: Uses an API key to prevent unauthorized uploads
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Get API key from environment or config
$apiKey = getenv('UPLOAD_API_KEY') ?: 'fba996a67317673a07b8adeefdc1dec3';

// Verify API key
$providedKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? '';
if ($providedKey !== $apiKey) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Invalid API key'
    ]);
    exit();
}

// Get file type (images or videos)
$fileType = $_POST['file_type'] ?? 'images';
if (!in_array($fileType, ['images', 'videos'])) {
    $fileType = 'images';
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or upload error: ' . ($_FILES['file']['error'] ?? 'unknown')
    ]);
    exit();
}

$file = $_FILES['file'];
$uploadDir = __DIR__ . '/uploads/' . $fileType . '/';

// Create upload directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create upload directory'
        ]);
        exit();
    }
}

// Validate file size (20MB max for all files)
$maxSize = 20 * 1024 * 1024; // 20MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'File too large. Max size: 20MB'
    ]);
    exit();
}

// Validate file type
$allowedTypes = $fileType === 'videos' 
    ? ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime']
    : ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)
    ]);
    exit();
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = $file['name'] ?? 'upload-' . time() . '-' . uniqid() . '.' . $extension;
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
$filepath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save file'
    ]);
    exit();
}

// Get the domain URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host;

// Return success response with file URL
echo json_encode([
    'success' => true,
    'message' => 'File uploaded successfully',
    'data' => [
        'url' => $baseUrl . '/uploads/' . $fileType . '/' . $filename,
        'filename' => $filename,
        'size' => $file['size'],
        'mimetype' => $file['type']
    ]
]);
?>

