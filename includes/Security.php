<?php

declare(strict_types=1);

namespace App;

/**
 * Безпека HTTP-заголовків та XSS-захист
 *
 * Встановлює CSP, X-Frame-Options, HSTS та інші заголовки.
 * Надає функцію e() для HTML-ескейпінгу.
 *
 * @package App
 */
class Security
{
    /**
     * Send security headers
     */
    public static function sendHeaders()
    {
        if (headers_sent()) return;

        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // X-XSS-Protection removed — deprecated, can introduce vulnerabilities in modern browsers

        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions policy — restrict browser features not needed
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // Content Security Policy (Optimized for DetalStudio)
        // NOTE: 'unsafe-inline' required until inline scripts/styles are refactored to external files
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://*.jsdelivr.net https://cdn.datatables.net https://code.jquery.com; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://*.jsdelivr.net https://fonts.googleapis.com https://cdn.datatables.net; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net https://*.jsdelivr.net; ";
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "connect-src 'self' https:; ";
        $csp .= "frame-src 'self';";

        header("Content-Security-Policy: $csp");
    }
}

/**
 * Global XSS escape helper for use in views.
 * Usage: <?= e($variable) ?> instead of <?= htmlspecialchars($variable) ?>
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
