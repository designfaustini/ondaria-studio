<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class Stations
{
    /** Opções do estúdio (equivalentes às abas de Opções do programa de automação). */
    public const DEFAULTS = [
        // Fundido / sobreposição
        'overlap_s' => 2.0,
        'end_detect' => true,
        'end_threshold_db' => -32,
        'fade_on_overlap' => true,
        'fade_on_stop' => true,
        'fade_stop_ms' => 1500,
        // Talk over e sweepers
        'talkover_level' => 0.25,
        'talkover_ms' => 400,
        'sweeper_duck_level' => 0.35,
        'jingle_duck' => false,
        // Detector de silêncio
        'silence_detect' => false,
        'silence_s' => 10,
        'silence_db' => -55,
        // Geral
        'autostart' => false,
        'dblclick_plays' => false,
        'open_last_list' => true,
        'allow_multi_instance' => false,
        'show_full_name' => false,
        'tags_format' => '%artist% - %title%',
        'confirm_close' => true,
        // Hora, temperatura e umidade
        'tth_units' => 'C',
        'weather_source' => 'manual',   // manual | openmeteo
        'weather_lat' => '',
        'weather_lon' => '',
        'weather_temp' => 25,
        'weather_hum' => 60,
        'weather_max_age_min' => 90,
        'time_12h' => false,
        // Registro
        'log_enabled' => true,
        // Transmissão (WebSocket para Liquidsoap harbor / AzuraCast Web DJ)
        'stream_enabled' => false,
        'stream_url' => '',
        'stream_user' => 'source',
        'stream_pass' => '',
        'stream_bitrate' => 128,
        // Estado do estúdio
        'last_playlist_id' => 0,
    ];

    public static function settings(array $station): array
    {
        $saved = json_decode((string) ($station['settings'] ?? ''), true);
        return array_replace(self::DEFAULTS, is_array($saved) ? array_intersect_key($saved, self::DEFAULTS) : []);
    }

    public static function saveSettings(int $stationId, array $changes): array
    {
        $station = DB::one('SELECT settings FROM stations WHERE id = ?', [$stationId]) ?? [];
        $current = self::settings($station);
        foreach ($changes as $k => $v) {
            if (!array_key_exists($k, self::DEFAULTS)) {
                continue;
            }
            $default = self::DEFAULTS[$k];
            $current[$k] = match (true) {
                is_bool($default) => filter_var($v, FILTER_VALIDATE_BOOLEAN),
                is_int($default) => (int) $v,
                is_float($default) => (float) str_replace(',', '.', (string) $v),
                default => mb_substr(trim((string) $v), 0, 500),
            };
        }
        DB::update('stations', ['settings' => json_encode($current, JSON_UNESCAPED_UNICODE)], 'id = ?', [$stationId]);
        return $current;
    }

    /** Cria a rádio com a estrutura inicial (pastas, eventos, páginas de vinhetas). */
    public static function create(string $name, int $planId, string $timezone = 'America/Sao_Paulo'): int
    {
        return DB::transaction(static function () use ($name, $planId, $timezone): int {
            $base = slugify($name);
            $slug = $base;
            for ($i = 2; DB::value('SELECT 1 FROM stations WHERE slug = ?', [$slug]); $i++) {
                $slug = $base . '-' . $i;
            }
            $id = DB::insert('stations', [
                'slug' => $slug,
                'name' => $name,
                'plan_id' => $planId,
                'timezone' => $timezone,
                'settings' => json_encode(self::DEFAULTS),
                'public_token' => bin2hex(random_bytes(12)),
            ]);
            foreach ([['Músicas', 'normal'], ['Vinhetas', 'normal'], ['Comerciais', 'normal'], ['Programas', 'normal'],
                      ['Hora certa', 'time'], ['Temperatura', 'temperature'], ['Umidade', 'humidity']] as [$folder, $kind]) {
                DB::insert('folders', ['station_id' => $id, 'name' => $folder, 'kind' => $kind]);
            }
            $set = DB::insert('event_sets', ['station_id' => $id, 'name' => 'Grade padrão']);
            DB::update('stations', ['active_event_set_id' => $set], 'id = ?', [$id]);
            for ($p = 0; $p < 10; $p++) {
                DB::insert('jingle_pages', ['station_id' => $id, 'page_no' => $p, 'name' => 'Página ' . ($p + 1), 'buttons' => '[]']);
            }
            DB::insert('playlists', ['station_id' => $id, 'name' => 'Lista principal', 'kind' => 'list', 'items' => '[]']);
            DB::insert('station_state', ['station_id' => $id]);
            return $id;
        });
    }

    public static function usedBytes(int $stationId): int
    {
        return (int) DB::value('SELECT COALESCE(SUM(size_bytes), 0) FROM media WHERE station_id = ?', [$stationId]);
    }
}
