<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\Session;

/** Painel do dono da plataforma: rádios, planos e acesso de suporte. */
final class AdminController
{
    public function index(): string
    {
        $stations = DB::all('SELECT s.id, s.name, s.slug, s.status, s.plan_id, s.created_at, p.name AS plan_name, p.storage_mb,
            (SELECT COALESCE(SUM(size_bytes),0) FROM media m WHERE m.station_id = s.id) AS used,
            (SELECT COUNT(*) FROM users u WHERE u.station_id = s.id) AS users,
            (SELECT u.email FROM users u WHERE u.station_id = s.id AND u.role = \'owner\' LIMIT 1) AS owner_email,
            st.np_updated_at
            FROM stations s JOIN plans p ON p.id = s.plan_id LEFT JOIN station_state st ON st.station_id = s.id
            WHERE s.slug <> ? ORDER BY s.created_at DESC', [\App\Services\DemoLibrary::SLUG]);
        return view('admin/index', ['title' => 'Plataforma', 'stations' => $stations, 'plans' => DB::all('SELECT * FROM plans ORDER BY price_cents')], 'app');
    }

    public function updateStation(int $id): void
    {
        $plan = (int) input('plan_id', 0);
        $status = input('status') === 'suspended' ? 'suspended' : 'active';
        if (!DB::value('SELECT 1 FROM plans WHERE id = ?', [$plan])) {
            abort(422);
        }
        DB::update('stations', ['plan_id' => $plan, 'status' => $status], 'id = ?', [$id]);
        \App\Core\Audit::record('admin.station', 'station:' . $id, "plano $plan, $status", $id);
        flash('success', 'Rádio atualizada: ' . ($status === 'active' ? 'ativa' : 'suspensa') . '.');
        redirect('/admin');
    }

    public function openStation(int $id): void
    {
        if (!DB::value('SELECT 1 FROM stations WHERE id = ? AND slug <> ?', [$id, \App\Services\DemoLibrary::SLUG])) {
            abort(404);
        }
        Session::set('as_station', $id);
        // Transparência: o acesso de suporte aparece na auditoria da própria rádio.
        \App\Core\Audit::record('support.access', 'station:' . $id, null, $id);
        redirect('/estudio');
    }

    public function closeStation(): void
    {
        Session::forget('as_station');
        redirect('/admin');
    }

    public function updatePlan(int $id): void
    {
        DB::update('plans', [
            'name' => mb_substr(trim((string) input('name', 'Plano')), 0, 80),
            'price_cents' => max(0, (int) round((float) str_replace(',', '.', (string) input('price', '0')) * 100)),
            'storage_mb' => max(50, (int) input('storage_mb', 500)),
            'max_users' => max(1, (int) input('max_users', 1)),
            'max_aux_players' => max(0, min(4, (int) input('max_aux_players', 1))),
            'streaming' => input('streaming') ? 1 : 0,
            'active' => input('active') ? 1 : 0,
        ], 'id = ?', [$id]);
        flash('success', 'Plano atualizado.');
        redirect('/admin');
    }
}
