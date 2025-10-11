<?php
/**
 * File Server for Uploaded KYC Documents
 */

// Security: Only allow access to specific file types
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

// Get requested file path
$requestedPath = $_SERVER['REQUEST_URI'] ?? '';
$pathParts = explode('/uploads/', $requestedPath);
$filePath = isset($pathParts[1]) ? $pathParts[1] : '';

// Remove query string if present
$filePath = strtok($filePath, '?');

// Security check: prevent directory traversal
if (strpos($filePath, '..') !== false || strpos($filePath, './') !== false) {
    http_response_code(403);
    die('Access denied');
}

// Full file path
$fullPath = __DIR__ . '/' . $filePath;

// Check if file exists
if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    die('File not found');
}

// Check file extension
$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions)) {
    http_response_code(403);
    die('File type not allowed');
}

// Set appropriate content type
$contentTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'pdf' => 'application/pdf'
];

header('Content-Type: ' . ($contentTypes[$extension] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: private, max-age=3600');

// Output file
readfile($fullPath);
exit;
