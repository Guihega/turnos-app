<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case TENANT_ADMIN = 'tenant_admin';
    case BRANCH_MANAGER = 'branch_manager';
    case OPERATOR = 'operator';
    case RECEPTIONIST = 'receptionist';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::TENANT_ADMIN => 'Administrador',
            self::BRANCH_MANAGER => 'Gerente de Sucursal',
            self::OPERATOR => 'Operador',
            self::RECEPTIONIST => 'Recepcionista',
            self::VIEWER => 'Solo lectura',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::SUPER_ADMIN => 100,
            self::TENANT_ADMIN => 80,
            self::BRANCH_MANAGER => 60,
            self::OPERATOR => 40,
            self::RECEPTIONIST => 30,
            self::VIEWER => 10,
        };
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = match ($this) {
            self::SUPER_ADMIN => ['*'],
            self::TENANT_ADMIN => [
                'branches.*', 'users.*', 'services.*', 'queues.*',
                'reports.*', 'settings.*', 'tickets.*', 'appointments.*',
            ],
            self::BRANCH_MANAGER => [
                'users.view', 'users.create', 'users.edit',
                'services.*', 'queues.*', 'reports.view',
                'tickets.*', 'appointments.*', 'counters.*',
            ],
            self::OPERATOR => [
                'tickets.call', 'tickets.serve', 'tickets.complete',
                'tickets.transfer', 'tickets.view', 'appointments.view',
            ],
            self::RECEPTIONIST => [
                'tickets.create', 'tickets.view', 'appointments.*',
                'customers.create', 'customers.view',
            ],
            self::VIEWER => ['reports.view', 'tickets.view'],
        };

        if (in_array('*', $permissions)) {
            return true;
        }

        foreach ($permissions as $p) {
            if ($p === $permission) {
                return true;
            }
            if (str_ends_with($p, '.*') && str_starts_with($permission, rtrim($p, '.*'))) {
                return true;
            }
        }

        return false;
    }
}
