<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'organization',
        'sector',
        'size',
        'message',
        'ip',
        'user_agent',
        'referrer',
        'status',
        'notes',
        'contacted_at',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
    ];

    /**
     * Sectores válidos para validación y exposición en UI.
     */
    public const SECTORS = ['salud', 'finanzas', 'gobierno', 'comercio', 'otro'];

    /**
     * Tamaños de operación válidos.
     */
    public const SIZES = ['1', '2-5', '6-20', '20+'];

    /**
     * Estados de lead en pipeline interno.
     */
    public const STATUSES = ['new', 'contacted', 'qualified', 'discarded'];

    /**
     * Label legible del sector para mails y admin.
     */
    public function sectorLabel(): string
    {
        return match ($this->sector) {
            'salud' => 'Clínica u hospital',
            'finanzas' => 'Banco o institución financiera',
            'gobierno' => 'Oficina de gobierno',
            'comercio' => 'Comercio o servicios',
            'otro' => 'Otro',
            default => $this->sector,
        };
    }

    /**
     * Label legible del tamaño de operación.
     */
    public function sizeLabel(): string
    {
        return match ($this->size) {
            '1' => 'Una sucursal o ubicación',
            '2-5' => 'De 2 a 5 sucursales',
            '6-20' => 'De 6 a 20 sucursales',
            '20+' => 'Más de 20 sucursales',
            default => $this->size,
        };
    }
}
