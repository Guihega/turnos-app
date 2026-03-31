<?php

declare(strict_types=1);

namespace App\Enums;

enum AppointmentStatus: string
{
    case SCHEDULED = 'scheduled';
    case CONFIRMED = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';
    case RESCHEDULED = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Agendado',
            self::CONFIRMED => 'Confirmado',
            self::CHECKED_IN => 'Registrado',
            self::IN_PROGRESS => 'En atención',
            self::COMPLETED => 'Completado',
            self::CANCELLED => 'Cancelado',
            self::NO_SHOW => 'No se presentó',
            self::RESCHEDULED => 'Reagendado',
        };
    }
}
