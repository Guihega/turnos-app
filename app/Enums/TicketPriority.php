<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketPriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';
    case VIP = 'vip';

    public function weight(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::NORMAL => 5,
            self::HIGH => 10,
            self::URGENT => 20,
            self::VIP => 50,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Baja',
            self::NORMAL => 'Normal',
            self::HIGH => 'Alta',
            self::URGENT => 'Urgente',
            self::VIP => 'VIP',
        };
    }
}
