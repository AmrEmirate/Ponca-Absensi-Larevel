<?php

namespace App\Services;

class GeoService
{
    /**
     * Menghitung jarak antara dua koordinat GPS menggunakan Rumus Haversine.
     *
     * @param float $lat1 Latitude titik 1
     * @param float $lon1 Longitude titik 1
     * @param float $lat2 Latitude titik 2
     * @param float $lon2 Longitude titik 2
     * @return float Jarak dalam meter
     */
    public static function getDistanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371e3; // Radius bumi dalam meter
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lon2 - $lon1);

        $a = sin($dp / 2) * sin($dp / 2) +
             cos($p1) * cos($p2) *
             sin($dl / 2) * sin($dl / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }

    /**
     * Mendapatkan deskripsi alamat dari koordinat GPS.
     */
    public static function getAddressFromCoords(float $lat, float $lng): string
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 3.0]);
            $response = $client->get("https://nominatim.openstreetmap.org/reverse", [
                'query' => [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                ],
                'headers' => [
                    'User-Agent' => 'PoncaFoodApp/1.0',
                ]
            ]);
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                if (!empty($data['display_name'])) {
                    return $data['display_name'];
                }
            }
        } catch (\Throwable $e) {
            // Fallback silently if network or timeout occurs
        }
        return sprintf("Koordinat GPS (%.6f, %.6f)", $lat, $lng);
    }
}
