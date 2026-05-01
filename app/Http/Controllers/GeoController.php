<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoController extends Controller
{
    /**
     * Obtener estados/provincias de un país.
     * Cache: 30 días (las divisiones administrativas no cambian frecuentemente).
     * GeoNames: children of country geonameId → admin1 divisions.
     */
    public function states(string $country): JsonResponse
    {
        $username = config('services.geonames.username');

        if (! $username) {
            return response()->json(['error' => 'GeoNames no configurado'], 503);
        }

        $country = strtoupper($country);
        $cacheKey = "geo:states:{$country}";

        $states = Cache::remember($cacheKey, 60 * 60 * 24 * 30, function () use ($country, $username) {
            try {
                // Primero obtener el geonameId del país
                $countryResponse = Http::timeout(10)->get('http://api.geonames.org/countryInfoJSON', [
                    'country' => $country,
                    'username' => $username,
                ]);

                if (! $countryResponse->successful()) {
                    return null;
                }

                $countryData = $countryResponse->json();
                $geonameId = $countryData['geonames'][0]['geonameId'] ?? null;

                if (! $geonameId) {
                    return null;
                }

                // Obtener las divisiones administrativas nivel 1 (estados/provincias)
                $response = Http::timeout(10)->get('http://api.geonames.org/childrenJSON', [
                    'geonameId' => $geonameId,
                    'username' => $username,
                    'maxRows' => 100,
                ]);

                if (! $response->successful()) {
                    return null;
                }

                $data = $response->json();
                $geonames = $data['geonames'] ?? [];

                return collect($geonames)
                    ->map(fn ($g) => [
                        'id' => $g['geonameId'],
                        'name' => $g['name'] ?? $g['toponymName'] ?? '',
                        'code' => $g['adminCode1'] ?? '',
                    ])
                    ->sortBy('name')
                    ->values()
                    ->toArray();
            } catch (\Exception $e) {
                return null;
            }
        });

        if ($states === null) {
            return response()->json(['error' => 'No se pudieron obtener los estados'], 503);
        }

        return response()->json($states);
    }

    /**
     * Obtener ciudades de un estado/provincia.
     * Cache: 30 días.
     * Usa el geonameId del estado para obtener sus hijos (ciudades).
     */
    public function cities(string $country, int $stateGeonameId): JsonResponse
    {
        $username = config('services.geonames.username');

        if (! $username) {
            return response()->json(['error' => 'GeoNames no configurado'], 503);
        }

        $cacheKey = "geo:cities:{$country}:{$stateGeonameId}";

        $cities = Cache::remember($cacheKey, 60 * 60 * 24 * 30, function () use ($stateGeonameId, $username) {
            try {
                $response = Http::timeout(10)->get('http://api.geonames.org/childrenJSON', [
                    'geonameId' => $stateGeonameId,
                    'username' => $username,
                    'maxRows' => 500,
                ]);

                if (! $response->successful()) {
                    return null;
                }

                $data = $response->json();
                $geonames = $data['geonames'] ?? [];

                return collect($geonames)
                    ->map(fn ($g) => [
                        'id' => $g['geonameId'],
                        'name' => $g['name'] ?? $g['toponymName'] ?? '',
                        'lat' => $g['lat'] ?? null,
                        'lng' => $g['lng'] ?? null,
                    ])
                    ->sortBy('name')
                    ->values()
                    ->toArray();
            } catch (\Exception $e) {
                return null;
            }
        });

        if ($cities === null) {
            return response()->json(['error' => 'No se pudieron obtener las ciudades'], 503);
        }

        return response()->json($cities);
    }

    /**
     * Buscar ciudades por texto (autocompletado).
     * Cache: 7 días por query.
     */
    public function search(string $country): JsonResponse
    {
        $username = config('services.geonames.username');

        if (! $username) {
            return response()->json(['error' => 'GeoNames no configurado'], 503);
        }

        $query = request('q', '');
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $country = strtoupper($country);
        $cacheKey = "geo:search:{$country}:".md5($query);

        $results = Cache::remember($cacheKey, 60 * 60 * 24 * 7, function () use ($country, $query, $username) {
            try {
                $response = Http::timeout(10)->get('http://api.geonames.org/searchJSON', [
                    'q' => $query,
                    'country' => $country,
                    'featureClass' => 'P', // Populated places
                    'maxRows' => 15,
                    'username' => $username,
                    'orderby' => 'relevance',
                ]);

                if (! $response->successful()) {
                    return [];
                }

                $data = $response->json();

                return collect($data['geonames'] ?? [])
                    ->map(fn ($g) => [
                        'name' => $g['name'] ?? '',
                        'state' => $g['adminName1'] ?? '',
                        'lat' => $g['lat'] ?? null,
                        'lng' => $g['lng'] ?? null,
                        'label' => ($g['name'] ?? '').', '.($g['adminName1'] ?? ''),
                    ])
                    ->toArray();
            } catch (\Exception $e) {
                return [];
            }
        });

        return response()->json($results);
    }
}
