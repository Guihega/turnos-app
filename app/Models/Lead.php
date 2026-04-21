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
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
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

    /**
     * Filtrar leads por fuente de origen (utm_source).
     *
     * Ejemplo: Lead::fromSource('facebook')->count()
     */
    public function scopeFromSource($query, string $source)
    {
        return $query->where('utm_source', $source);
    }

    /**
     * Filtrar leads por campaña específica (utm_campaign).
     *
     * Ejemplo: Lead::fromCampaign('lanzamiento-2026-04')->count()
     */
    public function scopeFromCampaign($query, string $campaign)
    {
        return $query->where('utm_campaign', $campaign);
    }
}
