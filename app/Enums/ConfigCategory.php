<?php

namespace App\Enums;

enum ConfigCategory: string
{
    case General = 'general';
    case Threshold = 'threshold';
    case Sla = 'sla';
    case Notification = 'notification';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Threshold => 'Threshold',
            self::Sla => 'SLA',
            self::Notification => 'Notification',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [
            $case->value => $case->label(),
        ])->toArray();
    }
}
