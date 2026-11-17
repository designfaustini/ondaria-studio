<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/** Primeiros passos da rádio (mostra o que já foi feito e qual é o próximo). */
final class Onboarding
{
    public static function steps(int $stationId): array
    {
        $music = (int) DB::value("SELECT COUNT(*) FROM media m JOIN folders f ON f.id = m.folder_id WHERE m.station_id = ? AND f.kind = 'normal'", [$stationId]);
        $lists = (int) DB::value("SELECT COUNT(*) FROM playlists WHERE station_id = ? AND items IS NOT NULL AND items <> '[]'", [$stationId]);
        $played = (bool) DB::value('SELECT np_updated_at FROM station_state WHERE station_id = ?', [$stationId]);
        $events = (int) DB::value('SELECT COUNT(*) FROM events WHERE station_id = ?', [$stationId]);
        return [
            ['done' => $music > 0, 'label' => 'Enviar as músicas', 'href' => '/biblioteca'],
            ['done' => $lists > 0, 'label' => 'Montar a primeira lista', 'href' => '/estudio'],
            ['done' => $played, 'label' => 'Tocar no estúdio', 'href' => '/estudio'],
            ['done' => $events > 0, 'label' => 'Agendar um evento (ex.: hora certa)', 'href' => '/eventos/novo'],
        ];
    }
}
