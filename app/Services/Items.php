<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Valida os itens de listas, eventos, vinhetas e sweepers enviados pelo estúdio. */
final class Items
{
    public const TYPES = [
        'track' => 'Faixa',
        'stop' => 'Comando parar',
        'play' => 'Comando tocar',
        'time' => 'Hora certa',
        'temperature' => 'Temperatura',
        'humidity' => 'Umidade',
        'pause' => 'Pausa',
        'random' => 'Faixa aleatória',
        'stream' => 'Rádio online',
        'satellite' => 'Satélite (entrada externa por tempo)',
        'satcon' => 'Conectar satélite',
        'satdis' => 'Desconectar satélite',
        'postit' => 'Lembrete (post-it)',
        'playlist' => 'Carregar lista',
        'rotation' => 'Rotação',
        'sequence' => 'Sequência',
        'events' => 'Carregar grade de eventos',
    ];

    public static function sanitizeList(mixed $items, int $stationId, int $max = 5000): array
    {
        if (!is_array($items)) {
            return [];
        }
        $items = array_slice(array_values(array_filter($items, 'is_array')), 0, $max);
        $mediaIds = array_unique(array_map('intval', array_column(array_filter($items, static fn ($i) => ($i['type'] ?? '') === 'track'), 'media_id')));
        $media = [];
        // A rádio pode usar os próprios arquivos e os da demonstração.
        $owners = DemoLibrary::readable($stationId);
        $inOwners = implode(',', array_fill(0, count($owners), '?'));
        foreach (array_chunk($mediaIds, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            foreach (DB::all("SELECT * FROM media WHERE station_id IN ($inOwners) AND id IN ($in)", [...$owners, ...$chunk]) as $m) {
                $media[(int) $m['id']] = $m;
            }
        }
        $refs = self::refs($stationId);
        $out = [];
        foreach ($items as $i) {
            $clean = self::sanitize($i, $media, $refs);
            if ($clean !== null) {
                $out[] = $clean;
            }
        }
        return $out;
    }

    public static function sanitizeOne(mixed $item, int $stationId): ?array
    {
        return self::sanitizeList([$item], $stationId, 1)[0] ?? null;
    }

    private static function refs(int $sid): array
    {
        $owners = DemoLibrary::readable($sid);
        return [
            'folders' => array_column(DB::all('SELECT id, name FROM folders WHERE station_id IN (' . implode(',', array_fill(0, count($owners), '?')) . ')', $owners), 'name', 'id'),
            'playlists' => array_column(DB::all('SELECT id, name FROM playlists WHERE station_id = ?', [$sid]), 'name', 'id'),
            'sets' => array_column(DB::all('SELECT id, name FROM event_sets WHERE station_id = ?', [$sid]), 'name', 'id'),
        ];
    }

    private static function sanitize(array $i, array $media, array $refs): ?array
    {
        $type = (string) ($i['type'] ?? '');
        if (!isset(self::TYPES[$type])) {
            return null;
        }
        $seconds = static fn (int $max) => max(0, min($max, (int) ($i['seconds'] ?? 0)));
        switch ($type) {
            case 'track':
                $m = $media[(int) ($i['media_id'] ?? 0)] ?? null;
                return $m ? Media::toItem($m) : null;
            case 'pause':
                return ['type' => 'pause', 'seconds' => $seconds(3600), 'title' => 'Pausa de ' . $seconds(3600) . ' s'];
            case 'satellite':
                return ['type' => 'satellite', 'seconds' => $seconds(86400), 'title' => 'Satélite por ' . format_duration($seconds(86400) * 1000)];
            case 'stream':
                $url = (string) ($i['url'] ?? '');
                if (!preg_match('#^https?://[^\s"<>]+$#i', $url) || strlen($url) > 500) {
                    return null;
                }
                return ['type' => 'stream', 'url' => $url, 'seconds' => $seconds(86400), 'title' => mb_substr((string) ($i['title'] ?? '') ?: 'Rádio online', 0, 120)];
            case 'postit':
                $text = mb_substr((string) ($i['text'] ?? ''), 0, 5000);
                return ['type' => 'postit', 'text' => $text, 'title' => 'Lembrete: ' . mb_substr(preg_replace('/\s+/', ' ', $text) ?? '', 0, 60)];
            case 'random':
                $fid = (int) ($i['folder_id'] ?? 0);
                return isset($refs['folders'][$fid]) ? ['type' => 'random', 'folder_id' => $fid, 'title' => 'Aleatória: ' . $refs['folders'][$fid]] : null;
            case 'playlist':
            case 'rotation':
            case 'sequence':
                $pid = (int) ($i['playlist_id'] ?? 0);
                return isset($refs['playlists'][$pid]) ? ['type' => $type, 'playlist_id' => $pid, 'title' => self::TYPES[$type] . ': ' . $refs['playlists'][$pid]] : null;
            case 'events':
                $esid = (int) ($i['event_set_id'] ?? 0);
                return isset($refs['sets'][$esid]) ? ['type' => 'events', 'event_set_id' => $esid, 'title' => 'Grade: ' . $refs['sets'][$esid]] : null;
            default:
                return ['type' => $type, 'title' => self::TYPES[$type]];
        }
    }
}
