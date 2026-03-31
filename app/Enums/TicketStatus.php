<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketStatus: string
{
    case PENDING = 'pending';
    case WAITING = 'waiting';
    case CALLED = 'called';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';
    case TRANSFERRED = 'transferred';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::WAITING => 'En espera',
            self::CALLED => 'Llamado',
            self::IN_PROGRESS => 'En atención',
            self::COMPLETED => 'Completado',
            self::CANCELLED => 'Cancelado',
            self::NO_SHOW => 'No se presentó',
            self::TRANSFERRED => 'Transferido',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::WAITING => 'yellow',
            self::CALLED => 'blue',
            self::IN_PROGRESS => 'indigo',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
            self::NO_SHOW => 'orange',
            self::TRANSFERRED => 'purple',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::WAITING, self::CALLED, self::IN_PROGRESS]);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::NO_SHOW]);
    }

    /**
     * Valid transitions from this status.
     *
     * @return array<TicketStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::WAITING, self::CANCELLED],
            self::WAITING => [self::CALLED, self::CANCELLED, self::TRANSFERRED],
            self::CALLED => [self::IN_PROGRESS, self::NO_SHOW, self::CANCELLED, self::WAITING],
            self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED, self::TRANSFERRED],
            self::COMPLETED, self::CANCELLED, self::NO_SHOW => [],
            self::TRANSFERRED => [self::WAITING],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions());
    }
}
