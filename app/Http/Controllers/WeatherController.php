<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    /**
     * Obtener datos del clima para una sucursal.
     * Cache: 30 minutos por branch.
     *
     * Prioridad de ubicación:
     * 1. Coordenadas (lat/lon) — más preciso
     * 2. Ciudad + País — buena precisión
     * 3. Fallback por timezone — aproximado
     */
    public function show(Branch $branch): JsonResponse
    {
        $apiKey = config('services.openweathermap.key');

        if (!$apiKey) {
            return response()->json(['error' => 'API key no configurada'], 503);
        }

        $cacheKey = "weather:branch:{$branch->id}";

        $weather = Cache::remember($cacheKey, 1800, function () use ($branch, $apiKey) {
            try {
                $params = $this->resolveLocation($branch);
                $params['appid'] = $apiKey;
                $params['units'] = 'metric';
                $params['lang']  = 'es';

                $response = Http::timeout(5)->get(
                    'https://api.openweathermap.org/data/2.5/weather',
                    $params
                );

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();

                return [
                    'temp'        => round($data['main']['temp'] ?? 0),
                    'feels_like'  => round($data['main']['feels_like'] ?? 0),
                    'humidity'    => $data['main']['humidity'] ?? 0,
                    'description' => ucfirst($data['weather'][0]['description'] ?? ''),
                    'icon'        => $data['weather'][0]['icon'] ?? '01d',
                    'city'        => $data['name'] ?? $branch->city ?? '',
                ];
            } catch (\Exception $e) {
                return null;
            }
        });

        if (!$weather) {
            return response()->json(['error' => 'No se pudo obtener el clima'], 503);
        }

        return response()->json($weather);
    }

    /**
     * Resolver los parámetros de ubicación para la API.
     *
     * Prioridad:
     * 1. lat/lon si la branch tiene coordenadas
     * 2. q=city,country si tiene ciudad y país
     * 3. q=city si solo tiene ciudad
     * 4. fallback por timezone
     */
    private function resolveLocation(Branch $branch): array
    {
        // 1. Coordenadas — más preciso
        if (!empty($branch->latitude) && !empty($branch->longitude)) {
            return [
                'lat' => $branch->latitude,
                'lon' => $branch->longitude,
            ];
        }

        // 2. Ciudad + País
        if (!empty($branch->city)) {
            $query = $branch->city;

            if (!empty($branch->state)) {
                $query .= ",{$branch->state}";
            }

            if (!empty($branch->country)) {
                $query .= ",{$branch->country}";
            }

            return ['q' => $query];
        }

        // 3. Dirección como fallback
        if (!empty($branch->address)) {
            $country = $branch->country ?? '';
            return ['q' => $branch->address . ($country ? ",{$country}" : '')];
        }

        // 4. Fallback por timezone
        $branch->loadMissing('tenant');
        $tz = $branch->timezone ?? $branch->tenant?->timezone ?? 'America/Mexico_City';

        return ['q' => $this->cityFromTimezone($tz)];
    }

    /**
     * Mapeo de timezones a ciudades representativas.
     * Se usa solo como último recurso cuando la branch no tiene ubicación.
     */
    private function cityFromTimezone(string $timezone): string
    {
        $map = [
            // México
            'America/Mexico_City' => 'Ciudad de Mexico,MX',
            'America/Monterrey'   => 'Monterrey,MX',
            'America/Cancun'      => 'Cancun,MX',
            'America/Tijuana'     => 'Tijuana,MX',
            'America/Merida'      => 'Merida,MX',
            'America/Hermosillo'  => 'Hermosillo,MX',
            'America/Chihuahua'   => 'Chihuahua,MX',
            'America/Mazatlan'    => 'Mazatlan,MX',
            // Centroamérica
            'America/Guatemala'      => 'Ciudad de Guatemala,GT',
            'America/Costa_Rica'     => 'San Jose,CR',
            'America/Panama'         => 'Ciudad de Panama,PA',
            'America/El_Salvador'    => 'San Salvador,SV',
            'America/Tegucigalpa'    => 'Tegucigalpa,HN',
            'America/Managua'        => 'Managua,NI',
            'America/Santo_Domingo'  => 'Santo Domingo,DO',
            // Sudamérica
            'America/Bogota'                  => 'Bogota,CO',
            'America/Lima'                    => 'Lima,PE',
            'America/Santiago'                => 'Santiago,CL',
            'America/Argentina/Buenos_Aires'  => 'Buenos Aires,AR',
            'America/Guayaquil'               => 'Guayaquil,EC',
            'America/La_Paz'                  => 'La Paz,BO',
            'America/Asuncion'                => 'Asuncion,PY',
            'America/Montevideo'              => 'Montevideo,UY',
            'America/Caracas'                 => 'Caracas,VE',
            'America/Sao_Paulo'               => 'Sao Paulo,BR',
            // Norteamérica
            'America/New_York'    => 'New York,US',
            'America/Chicago'     => 'Chicago,US',
            'America/Denver'      => 'Denver,US',
            'America/Los_Angeles' => 'Los Angeles,US',
            // Europa
            'Europe/Madrid' => 'Madrid,ES',
            'Europe/London' => 'London,GB',
        ];

        return $map[$timezone] ?? 'Ciudad de Mexico,MX';
    }
}
