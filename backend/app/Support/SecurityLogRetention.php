<?php

namespace App\Support;

final class SecurityLogRetention
{
    public static function prune(?string $directory = null): void
    {
        $directory ??= storage_path('logs');
        $cutoff = now()->startOfDay()->subDays(29)->format('Y-m-d');
        foreach (glob($directory.DIRECTORY_SEPARATOR.'security-*.jsonl') ?: [] as $path) {
            if (is_link($path) || ! is_file($path)) {
                continue;
            }
            if (preg_match('/^security-(\d{4}-\d{2}-\d{2})\.jsonl$/D', basename($path), $match) && $match[1] < $cutoff) {
                unlink($path);
            }
        }
    }
}
