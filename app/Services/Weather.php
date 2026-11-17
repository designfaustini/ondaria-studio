<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Temperatura e umidade para as locuções: valores manuais ou Open-Meteo (gratuito, sem chave). */
final class Weather
{
    private const CACHE_MIN = 15;

    public static function current(array $station): array
    {
        $s = Stations::settings($station);
        $unit = $s['tth_units'] === 'F' ? 'F' : 'C';
        if ($s['weather_source'] !== 'openmeteo' || $s['weather_lat'] === '' || $s['weather_lon'] === '') {
            return ['temp' => (int) $s['weather_temp'], 'hum' => (int) $s['weather_hum'], 'unit' => $unit, 'valid' => true, 'source' => 'manual', 'updated_at' => null];
        }
        $sid = (int) $station['id'];
        $row = DB::one('SELECT weather, weather_at FROM station_state WHERE station_id = ?', [$sid]);
        $cached = json_decode((string) ($row['weather'] ?? ''), true);
        $age = $row['weather_at'] ? (time() - strtotime((string) $row['weather_at'])) / 60 : PHP_INT_MAX;
        // Depois de uma falha, espera 5 min antes de tentar de novo (não trava cada requisição por 5 s).
        $failMark = STORAGE_PATH . '/tmp/weather-fail-' . $sid;
        $recentFail = is_file($failMark) && filemtime($failMark) > time() - 300;
        if ((!is_array($cached) || $age > self::CACHE_MIN) && !$recentFail) {
            $fresh = self::fetch((string) $s['weather_lat'], (string) $s['weather_lon']);
            if ($fresh) {
                $cached = $fresh;
                $age = 0;
                DB::update('station_state', ['weather' => json_encode($fresh), 'weather_at' => now()], 'station_id = ?', [$sid]);
                @unlink($failMark);
            } else {
                @touch($failMark);
            }
        }
        if (!is_array($cached)) {
            return ['temp' => null, 'hum' => null, 'unit' => $unit, 'valid' => false, 'source' => 'openmeteo', 'updated_at' => null];
        }
        $temp = $unit === 'F' ? $cached['temp_c'] * 9 / 5 + 32 : $cached['temp_c'];
        return [
            'temp' => (int) round($temp),
            'hum' => (int) round($cached['hum']),
            'unit' => $unit,
            // Igual ao programa original: não anuncia dados velhos (ex.: internet caiu).
            'valid' => $age <= (int) $s['weather_max_age_min'],
            'source' => 'openmeteo',
            'updated_at' => $row['weather_at'] ?? now(),
        ];
    }

    private static function fetch(string $lat, string $lon): ?array
    {
        if (!is_numeric($lat) || !is_numeric($lon)) {
            return null;
        }
        $url = sprintf('https://api.open-meteo.com/v1/forecast?latitude=%.4f&longitude=%.4f&current=temperature_2m,relative_humidity_2m', (float) $lat, (float) $lon);
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'OndariaStudio/1.0'], 'ssl' => ['verify_peer' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        $data = $raw ? json_decode($raw, true) : null;
        if (!isset($data['current']['temperature_2m'], $data['current']['relative_humidity_2m'])) {
            return null;
        }
        return ['temp_c' => (float) $data['current']['temperature_2m'], 'hum' => (float) $data['current']['relative_humidity_2m']];
    }
}
