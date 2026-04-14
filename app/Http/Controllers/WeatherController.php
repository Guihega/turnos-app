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
     * Cache: 30 minutos por branch para no abusar del API gratuito.
     * El plan gratuito de OpenWeatherMap permite 1,000 llamadas/día.
     */
    public function show(Branch $branch): JsonResponse
    {
        $apiKey = config('services.openweathermap.key');

        if (!$apiKey) {
            return response()->json(['error' => 'API key no configurada'], 503);
        }

        $cacheKey = "weather:branch:{$branch->id}";

        $weather = Cache::remember($cacheKey, 1800, function () use ($branch, $apiKey) {
            // Determinar la ciudad: branch.city → tenant timezone → default
            $city = $this->resolveCity($branch);

            if (!$city) {
                return null;
            }

            try {
                $response = Http::timeout(5)->get('https://api.openweathermap.org/data/2.5/weather', [
                    'q'     => $city,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'lang'  => 'es',
                ]);

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
                    'city'        => $data['name'] ?? $city,
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
     * Resolver la ciudad para la consulta del clima.
     * Prioridad: branch.city → branch.address → tenant default.
     */
    private function resolveCity(Branch $branch): ?string
    {
        // 1. Ciudad de la branch
        if (!empty($branch->city)) {
            $city = $branch->city;
            if (!empty($branch->state)) {
                $city .= ",{$branch->state}";
            }
            return $city . ',MX';
        }

        // 2. Extraer ciudad de la dirección
        if (!empty($branch->address)) {
            // Intentar usar la dirección como query
            return $branch->address . ',MX';
        }

        // 3. Default basado en timezone del tenant
        $branch->loadMissing('tenant');
        $tz = $branch->tenant?->timezone ?? 'America/Mexico_City';

        $tzCityMap = [
            'America/Mexico_City' => 'Ciudad de Mexico,MX',
            'America/Monterrey'   => 'Monterrey,MX',
            'America/Cancun'      => 'Cancun,MX',
            'America/Tijuana'     => 'Tijuana,MX',
            'America/Merida'      => 'Merida,MX',
            'America/Hermosillo'  => 'Hermosillo,MX',
            'America/Chihuahua'   => 'Chihuahua,MX',
            'America/Mazatlan'    => 'Mazatlan,MX',
        ];

        return $tzCityMap[$tz] ?? 'Ciudad de Mexico,MX';
    }
}
