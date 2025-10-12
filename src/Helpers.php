<?php

/**
 * Utility helper functions
 * 
 * Contains timezone management, relative time formatting,
 * atomic JSON writing, and YouTube URL parsing utilities.
 */
class Helpers
{
    /**
     * Set default timezone from environment configuration
     */
    public static function setDefaultTimezone(): void
    {
        $timezone = Env::get('TIMEZONE', 'America/New_York');
        
        try {
            date_default_timezone_set($timezone);
        } catch (Exception $e) {
            // Fallback to safe timezone if configured one is invalid
            date_default_timezone_set('America/New_York');
        }
    }
    
    /**
     * Format timestamp as relative time (e.g., "5 minutes ago", "2 hours ago")
     * 
     * @param DateTimeInterface $timestamp The timestamp to format
     * @param DateTimeInterface|null $now Reference time (defaults to now)
     */
    public static function relativeTime(DateTimeInterface $timestamp, ?DateTimeInterface $now = null): string
    {
        if ($now === null) {
            $now = new DateTime();
        }
        
        $diff = $now->getTimestamp() - $timestamp->getTimestamp();
        
        if ($diff < 60) {
            return 'just now';
        }
        
        $intervals = [
            31536000 => 'year',
            2592000 => 'month',  
            604800 => 'week',
            86400 => 'day',
            3600 => 'hour',
            60 => 'minute'
        ];
        
        foreach ($intervals as $seconds => $label) {
            $count = intval($diff / $seconds);
            if ($count >= 1) {
                $plural = $count > 1 ? 's' : '';
                if ($label === 'day' && $count === 1) {
                    return 'yesterday';
                }
                return "{$count} {$label}{$plural} ago";
            }
        }
        
        return 'just now';
    }
    
    /**
     * Atomically write JSON data to file
     * 
     * Writes to a temporary file first, then renames to target path
     * to ensure atomic operation and prevent partial reads.
     * 
     * @param string $path Target file path
     * @param array $data Data to encode as JSON
     * @throws RuntimeException if write fails
     */
    public static function atomicWriteJson(string $path, array $data): void
    {
        $directory = dirname($path);
        
        // Ensure directory exists
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new RuntimeException("Failed to create directory: {$directory}");
            }
        }
        
        // Generate temporary file in same directory
        $tempPath = $directory . '/' . uniqid('tmp_', true) . '.json';
        
        // Encode JSON with consistent formatting
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('JSON encoding failed: ' . json_last_error_msg());
        }
        
        // Write to temporary file
        if (file_put_contents($tempPath, $json, LOCK_EX) === false) {
            throw new RuntimeException("Failed to write temporary file: {$tempPath}");
        }
        
        // Atomic rename to final location
        if (!rename($tempPath, $path)) {
            unlink($tempPath); // Clean up temp file
            throw new RuntimeException("Failed to rename temporary file to: {$path}");
        }
    }
    
    /**
     * Convert YouTube URLs to embed-safe nocookie URLs
     * 
     * Handles common YouTube URL formats:
     * - https://www.youtube.com/watch?v=VIDEO_ID
     * - https://youtu.be/VIDEO_ID
     * - https://youtube.com/watch?v=VIDEO_ID
     * 
     * @param string $url YouTube URL
     * @return string|null Embed URL or null if not a valid YouTube URL
     */
    public static function youtubeEmbedUrl(string $url): ?string
    {
        // Extract video ID from various YouTube URL formats
        $patterns = [
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
            '/youtu\.be\/([a-zA-Z0-9_-]+)/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $videoId = $matches[1];
                return "https://www.youtube-nocookie.com/embed/{$videoId}";
            }
        }
        
        return null;
    }
    
    /**
     * Escape HTML for safe output
     */
    public static function escapeHtml(?string $text): string
    {
        if ($text === null) {
            return '';
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generate current Unix timestamp
     */
    public static function timestamp(): int
    {
        return time();
    }
}