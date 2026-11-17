<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;

final class PublicController
{
    public function home(): string
    {
        $plans = DB::all('SELECT * FROM plans WHERE active = 1 ORDER BY price_cents');
        return view('public/home', ['plans' => $plans, 'title' => app_name() . ' — automação de rádio no navegador'], 'public');
    }

    /** "Música no ar" para o site da rádio, RDS ou apps (equivale ao arquivo CurrentSong.txt). */
    public function nowPlaying(string $slug, string $fmt): void
    {
        $row = DB::one('SELECT s.name, st.now_playing, st.np_updated_at FROM stations s LEFT JOIN station_state st ON st.station_id = s.id WHERE s.slug = ? AND s.status = ?', [$slug, 'active']);
        if (!$row) {
            abort(404);
        }
        $np = json_decode((string) $row['now_playing'], true) ?: [];
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-cache, max-age=5');
        if ($fmt === 'txt') {
            header('Content-Type: text/plain; charset=utf-8');
            echo trim(($np['artist'] ?? '') !== '' ? $np['artist'] . ' - ' . ($np['title'] ?? '') : ($np['title'] ?? ''));
            return;
        }
        json_response([
            'station' => $row['name'],
            'title' => $np['title'] ?? null,
            'artist' => $np['artist'] ?? null,
            'started_at' => $np['started_at'] ?? null,
            'duration_ms' => $np['duration_ms'] ?? null,
            'next' => $np['next'] ?? null,
            'updated_at' => $row['np_updated_at'],
        ]);
    }
}
