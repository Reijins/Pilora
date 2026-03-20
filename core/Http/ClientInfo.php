<?php
declare(strict_types=1);

namespace Core\Http;

final class ClientInfo
{
    public static function ipAddress(): string
    {
        // En prod: gérer correctement les proxy via une config de trusted proxies.
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($forwardedFor) && $forwardedFor !== '') {
            // Format: client, proxy1, proxy2...
            $first = trim(explode(',', $forwardedFor)[0] ?? '');
            if ($first !== '') {
                return $first;
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function userAgent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return is_string($ua) && $ua !== '' ? $ua : null;
    }
}

