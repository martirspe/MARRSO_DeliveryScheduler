<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model;

/**
 * Home delivery service levels (Falabella-style).
 */
final class ServiceLevel
{
    public const SCHEDULED = 'scheduled';
    public const EXPRESS_24 = 'express_24';
    public const EXPRESS_180 = 'express_180';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::SCHEDULED,
            self::EXPRESS_24,
            self::EXPRESS_180,
        ];
    }

    public static function isValid(?string $level): bool
    {
        return $level !== null && in_array($level, self::all(), true);
    }

    public static function normalize(?string $level): string
    {
        return self::isValid($level) ? $level : self::SCHEDULED;
    }
}
