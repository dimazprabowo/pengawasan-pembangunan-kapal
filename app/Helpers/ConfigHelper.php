<?php

namespace App\Helpers;

use App\Models\SystemConfiguration;

class ConfigHelper
{
    public static function get(string $key, $default = null)
    {
        return SystemConfiguration::get($key, $default);
    }

    public static function isRegistrationOpen(): bool
    {
        $deadline = self::getRegistrationDeadline();

        return $deadline !== null && \Carbon\Carbon::now()->lt($deadline);
    }

    public static function getRegistrationDeadline(): ?\Carbon\Carbon
    {
        $value = SystemConfiguration::get('registration.deadline');

        if (empty($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getFormattedRegistrationDeadline(string $format = 'd F Y, H:i'): ?string
    {
        $deadline = self::getRegistrationDeadline();

        return $deadline ? $deadline->translatedFormat($format) : null;
    }

    public static function getRegistrationDaysRemaining(): ?int
    {
        $deadline = self::getRegistrationDeadline();
        if (! $deadline) {
            return null;
        }

        $days = \Carbon\Carbon::now()->diffInDays($deadline, false);

        return max(0, (int) $days);
    }

    public static function shouldShowRegistrationCountdown(): bool
    {
        $days = self::getRegistrationDaysRemaining();

        return $days !== null && $days <= 7;
    }

    public static function getRegistrationClosedMessage(): string
    {
        return SystemConfiguration::get('registration.closed_message', 'Pendaftaran telah ditutup.');
    }

    public static function getAppName(): string
    {
        return SystemConfiguration::get('app.name', config('app.name', 'SIMPRO'));
    }
}
