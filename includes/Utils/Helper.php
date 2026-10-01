<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Global Utility Helpers
 */
class Helper {
    
    /**
     * Format currency
     */
    public static function formatCurrency($amount): string {
        return number_format((float)$amount, 2, '.', ' ') . ' ₴';
    }

    /**
     * Format date
     */
    public static function formatDate($date, string $format = 'd.m.Y H:i'): string {
        if (!$date) return '';
        return date($format, is_numeric($date) ? (int)$date : strtotime($date));
    }

    /**
     * Format and normalize phone number to +380XXXXXXXXX
     */
    public static function formatPhone(?string $phone): ?string {
        if (!$phone) return null;
        
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        
        // If it starts with + and has 13 chars, it's likely already fine
        if (str_starts_with($cleaned, '+380') && strlen($cleaned) === 13) {
            return $cleaned;
        }

        // Strip the plus to analyze the digits
        $digits = preg_replace('/\D/', '', $phone);
        
        if (strlen($digits) === 10) {
            // E.g. 0501234567 -> +380501234567
            return '+38' . $digits;
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '380')) {
            // E.g. 380501234567 -> +380501234567
            return '+' . $digits;
        } elseif (strlen($digits) > 10) {
            // Some other international number
            return '+' . $digits;
        }
        
        // Return original if we can't safely format it
        return $phone;
    }

    /**
     * Mask phone number, showing only last 4 digits
     * Example: +380501234567 → *** *** ** 72
     */
    public static function maskPhone(?string $phone): string {
        if (!$phone) return '—';
        $digits = preg_replace('/\D/', '', $phone);
        $len = strlen($digits);
        if ($len <= 4) return str_repeat('*', $len);
        $visible = substr($digits, -2);
        return str_repeat('*', $len - 2) . $visible;
    }

    /**
     * Display phone based on current user role
     * Directors and managers see full phone, others see masked version
     */
    public static function displayPhone(?string $phone): string {
        if (\App\Auth::hasAnyRole(['director', 'manager'])) {
            return htmlspecialchars($phone ?? '');
        }
        return self::maskPhone($phone);
    }

    /**
     * Get badge class based on status or priority
     */
    public static function getBadgeClass(string $type, string $value): string {
        $classes = [
            'status' => [
                'new' => 'bg-info',
                'in_progress' => 'bg-warning',
                'ready' => 'bg-teal',
                'closed' => 'bg-success',
                // Task statuses
                'created' => 'bg-info',
                'accepted' => 'bg-primary',
                'completed' => 'bg-success',
                // Purchase request statuses
                'pending' => 'bg-warning',
                'ordered' => 'bg-primary',
                'received' => 'bg-info',
            ],
            'priority' => [
                'low' => 'bg-success',
                'medium' => 'bg-warning',
                'high' => 'bg-danger',
                'urgent' => 'bg-dark'
            ]
        ];

        return $classes[$type][$value] ?? 'bg-secondary';
    }

    /**
     * Generate unique random number/string
     */
    public static function generateNumber(string $prefix, int $length = 5): string {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random = '';
        for ($i = 0; $i < $length; $i++) {
            $random .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $prefix . '-' . $random;
    }

    /**
     * Convert empty strings to null
     */
    public static function emptyToNull($value) {
        return (isset($value) && (string)$value !== '') ? $value : null;
    }

    /**
     * Generate a unique order number
     */
    public static function generateOrderNumber(): string {
        return self::generateNumber('ORD');
    }

    /**
     * Generate unique purchase request number
     */
    public static function generatePurchaseRequestNumber(): string {
        return 'PUR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
    /**
     * Process and compress image
     * Resizes if larger than maxWidth, converts to WebP if supported, otherwise JPEG
     */
    public static function processImage(string $sourcePath, string $targetPath, int $maxWidth = 1920, int $quality = 80): bool {
        if (!extension_loaded('gd')) {
            return move_uploaded_file($sourcePath, $targetPath);
        }

        $info = getimagesize($sourcePath);
        if (!$info) return false;

        [$width, $height, $type] = $info;

        // Load image
        switch ($type) {
            case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($sourcePath); break;
            case IMAGETYPE_PNG:  $src = imagecreatefrompng($sourcePath); break;
            case IMAGETYPE_WEBP: $src = imagecreatefromwebp($sourcePath); break;
            default: return false;
        }

        if (!$src) return false;

        // Calculate new dimensions
        if ($width > $maxWidth) {
            $ratio = $maxWidth / $width;
            $newWidth = $maxWidth;
            $newHeight = (int)($height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG/WebP if we might keep them (though we prefer WebP/JPEG)
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save
        $success = false;
        $extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

        if ($extension === 'webp' && function_exists('imagewebp')) {
            $success = imagewebp($dst, $targetPath, $quality);
        } else {
            // Default to JPEG if no webp support or specified
            // Note: If original was PNG, we convert to JPEG to save space
            $success = imagejpeg($dst, $targetPath, $quality);
        }

        imagedestroy($src);
        imagedestroy($dst);

        return $success;
    }

    /**
     * Basic asset minification (CSS/JS)
     */
    public static function minify(string $code, string $type = 'css'): string {
        if ($type === 'css') {
            // Remove comments, newlines, and extra spaces
            $code = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $code);
            $code = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $code);
        } elseif ($type === 'js') {
            // Very basic JS compression (remove newlines and tabs)
            // Advanced JS minification is risky without a real parser
            $code = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $code);
            $code = preg_replace('/ {2,}/', ' ', $code);
        }
        return trim($code);
    }
}
