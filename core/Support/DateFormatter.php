<?php
declare(strict_types=1);

namespace Core\Support;

final class DateFormatter
{
    public static function frDate(?string $value, string $fallback = '—'): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        try {
            $dt = new \DateTimeImmutable($value);
            return $dt->format('d/m/Y');
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public static function frDateTime(?string $value, string $fallback = '—'): string
    {
        if ($value === null || trim($value) === '') {
            return $fallback;
        }

        try {
            $dt = new \DateTimeImmutable($value);
            return $dt->format('d/m/Y H:i');
        } catch (\Throwable) {
            return $fallback;
        }
    }
}

