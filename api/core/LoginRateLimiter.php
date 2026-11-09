<?php
/**
 * Small file-backed login limiter for shared hosting without a cache service.
 */
class LoginRateLimiter
{
    private const WINDOW_SECONDS = 900;

    public static function isLimited(string $key, int $maxAttempts): bool
    {
        return self::withAttempts($key, static function (array &$attempts, int $now) use ($maxAttempts): bool {
            return count($attempts) >= $maxAttempts;
        });
    }

    public static function recordFailure(string $key): void
    {
        self::withAttempts($key, static function (array &$attempts, int $now): void {
            $attempts[] = $now;
        });
    }

    public static function clear(string $key): void
    {
        self::withAttempts($key, static function (array &$attempts, int $now): void {
            $attempts = [];
        });
    }

    private static function withAttempts(string $key, callable $callback)
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        if (mt_rand(1, 64) === 1) {
            self::cleanupExpired($directory);
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'northwind-bms-login-' . hash('sha256', $key) . '.json';
        $handle = @fopen($path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Login protection storage is unavailable.');
        }

        rewind($handle);
        $raw = stream_get_contents($handle);
        $stored = json_decode($raw === false ? '' : $raw, true);
        if ($raw === false || ($raw !== '' && !is_array($stored))) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new RuntimeException('Login protection storage is invalid.');
        }
        $now = time();
        $attempts = [];
        foreach ($stored ?: [] as $timestamp) {
            if (!is_int($timestamp)) {
                flock($handle, LOCK_UN);
                fclose($handle);
                throw new RuntimeException('Login protection storage is invalid.');
            }
            if ($timestamp > $now - self::WINDOW_SECONDS) {
                $attempts[] = $timestamp;
            }
        }

        try {
            $result = $callback($attempts, $now);
            rewind($handle);
            ftruncate($handle, 0);
            $encoded = json_encode($attempts);
            $written = fwrite($handle, $encoded);
            if ($written !== strlen($encoded)) {
                throw new RuntimeException('Login protection storage could not be updated.');
            }
            fflush($handle);
            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function cleanupExpired(string $directory): void
    {
        $olderThan = time() - 86400;
        foreach (glob($directory . DIRECTORY_SEPARATOR . 'northwind-bms-login-*.json') ?: [] as $path) {
            $modifiedAt = @filemtime($path);
            if ($modifiedAt !== false && $modifiedAt < $olderThan) {
                @unlink($path);
            }
        }
    }
}
