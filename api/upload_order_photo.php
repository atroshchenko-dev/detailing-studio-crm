<?php

/**
 * API: Upload Order Photo
 * Handles photo uploads for orders (before/after/process)
 */

require_once __DIR__ . '/../config/config.php';

use App\Auth;

header('Content-Type: application/json; charset=utf-8');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
\App\Csrf::verifyApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $photoType = $_POST['photo_type'] ?? 'before';
    $caption = $_POST['caption'] ?? '';

    if ($orderId <= 0) {
        throw new Exception('Invalid order ID');
    }

    if (!in_array($photoType, ['before', 'after', 'process'])) {
        throw new Exception('Invalid photo type');
    }

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['photo'];
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    // Server-side MIME type validation (finfo, not client-supplied type)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);
    if (!in_array($detectedMime, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, WEBP allowed');
    }

    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Max 10MB');
    }

    // Create uploads directory if not exists
    $uploadDir = __DIR__ . '/../uploads/orders/' . $orderId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename. Force .webp for processed images
    $fileName = $photoType . '_' . time() . '_' . uniqid() . '.webp';
    $filePath = $uploadDir . '/' . $fileName;

    // Process and Compress Image
    if (!\App\Utils\Helper::processImage($file['tmp_name'], $filePath, 1920, 80)) {
        // Fallback if processing fails (e.g. GD not installed)
        // Use safe extension based on detected MIME, NOT user-supplied filename
        $safeExtensions = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $extension = $safeExtensions[$detectedMime] ?? 'jpg';
        $fileName = $photoType . '_' . time() . '_' . uniqid() . '.' . $extension;
        $filePath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save file');
        }
    }

    // Upload to Google Drive
    $googleDriveId = null;
    try {
        $googleDriveId = \App\GoogleIntegration::uploadOrderPhoto($orderId, $filePath, $file['name'], $photoType);
    } catch (Exception $ge) {
        error_log("Google Drive upload failed for order $orderId: " . $ge->getMessage());
    }

    // Save to database
    $stmt = db()->prepare("
        INSERT INTO order_photos 
        (order_id, photo_type, file_path, file_name, file_size, google_drive_id, uploaded_by, caption) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $relPath = '/uploads/orders/' . $orderId . '/' . $fileName;
    $userId = Auth::getUserId();

    $stmt->execute([
        $orderId,
        $photoType,
        $relPath,
        $file['name'],
        $file['size'],
        $googleDriveId,
        $userId,
        $caption
    ]);

    $photoId = db()->lastInsertId();

    echo json_encode([
        'success' => true,
        'photo_id' => $photoId,
        'file_path' => $relPath,
        'google_drive_id' => $googleDriveId,
        'message' => 'Фото завантажено ' . ($googleDriveId ? 'та синхронізовано з Google Drive' : 'локально (помилка Google Drive)')
    ]);
} catch (Exception $e) {
    error_log("upload_order_photo API error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Помилка завантаження файлу']);
}
