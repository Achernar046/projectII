<?php
/** File-backed revocation records for JWTs until their expiry time. */
class TokenRevocationStore
{
    public static function revoke(string $token, int $expiresAt): void
    {
        self::cleanupExpired();
        $path = self::pathFor($token);
        $handle = @fopen($path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Token revocation storage is unavailable.');
        }

        try {
            rewind($handle);
            ftruncate($handle, 0);
            $value = (string)$expiresAt;
            if (fwrite($handle, $value) !== strlen($value)) {
                throw new RuntimeException('Token revocation storage could not be updated.');
            }
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function isRevoked(string $token): bool
    {
        $path = self::pathFor($token);
        if (!file_exists($path)) {
            return false;
        }

        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Token revocation storage is unavailable.');
        }

        if (!flock($handle, LOCK_SH)) {
            fclose($handle);
            throw new RuntimeException('Token revocation storage is unavailable.');
        }
        $raw = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        if ($raw === false || !ctype_digit(trim($raw))) {
            throw new RuntimeException('Token revocation storage is invalid.');
        }

        $expiresAt = (int)trim($raw);
        if ($expiresAt <= time()) {
            @unlink($path);
            return false;
        }
        return true;
    }

    private static function pathFor(string $token): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'northwind-bms-revoked-' . hash('sha256', $token) . '.txt';
    }

    private static function cleanupExpired(): void
    {
        $pattern = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'northwind-bms-revoked-*.txt';
        $olderThan = time() - 172800;
        foreach (glob($pattern) ?: [] as $path) {
            $modifiedAt = @filemtime($path);
            if ($modifiedAt !== false && $modifiedAt < $olderThan) {
                @unlink($path);
            }
        }
    }
}
