<?php
declare(strict_types=1);

/*
 * Comandos:
 *   php bin/console.php migrate                       cria as tabelas (MySQL ou SQLite) — só em banco vazio
 *   php bin/console.php superadmin email@x.com "Nome"  cria/atualiza o administrador da plataforma (pede a senha)
 *   php bin/console.php demo                           cria a rádio de demonstração com áudios de teste gerados
 *   php bin/console.php cleanup                        apaga uploads incompletos e logs antigos (agende no cron)
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Services\Media;
use App\Services\Stations;

$cmd = $argv[1] ?? '';

switch ($cmd) {
    case 'migrate':
        $sql = (string) file_get_contents(BASE_PATH . '/database/schema.sql');
        if (DB::driver() === 'sqlite') {
            $sql = DB::mysqlToSqlite($sql);
        }
        $sql = preg_replace('/^--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', (string) $sql))) as $stmt) {
            DB::pdo()->exec($stmt);
        }
        echo "Tabelas criadas.\n";
        break;

    case 'superadmin':
        $email = mb_strtolower($argv[2] ?? '');
        $name = $argv[3] ?? 'Administrador';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            exit("Uso: php bin/console.php superadmin email@dominio \"Nome\"\n");
        }
        $pass = getenv('ADMIN_PASSWORD') ?: '';
        if ($pass === '') {
            echo 'Senha (10+ caracteres): ';
            $pass = trim((string) fgets(STDIN));
        }
        if (strlen($pass) < 10) {
            exit("Senha muito curta.\n");
        }
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        if ($id = DB::value('SELECT id FROM users WHERE email = ?', [$email])) {
            DB::update('users', ['password_hash' => $hash, 'role' => 'superadmin', 'station_id' => null, 'active' => 1], 'id = ?', [$id]);
        } else {
            DB::insert('users', ['email' => $email, 'name' => $name, 'password_hash' => $hash, 'role' => 'superadmin']);
        }
        echo "Superadmin pronto: $email\n";
        break;

    case 'demo':
        $email = 'demo@ondaria.test';
        if (DB::value('SELECT 1 FROM users WHERE email = ?', [$email])) {
            exit("A rádio de demonstração já existe.\n");
        }
        $pass = getenv('DEMO_PASSWORD') ?: bin2hex(random_bytes(6));
        $plan = (int) DB::value("SELECT id FROM plans WHERE slug = 'pro'");
        $sid = Stations::create('Rádio Demonstração', $plan);
        DB::insert('users', ['station_id' => $sid, 'name' => 'Operador Demo', 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => 'owner']);
        $folders = array_column(DB::all('SELECT id, kind, name FROM folders WHERE station_id = ?', [$sid]), 'id', 'name');
        $kindFolder = array_column(DB::all('SELECT id, kind FROM folders WHERE station_id = ?', [$sid]), 'id', 'kind');
        $songs = [];
        foreach ([['Banda Teste - Manhã de Sol', 440, 20], ['Coral Digital - Onda Azul', 523, 18], ['Trio Sinusoidal - Frequência', 659, 22], ['DJ Oscilador - Batida Lenta', 392, 16]] as [$name, $freq, $sec]) {
            $songs[] = make($sid, (int) $folders['Músicas'], $name . '.wav', $freq, $sec);
        }
        $jingle = make($sid, (int) $folders['Vinhetas'], 'Vinheta Prefixo.wav', 880, 4);
        make($sid, (int) $folders['Comerciais'], 'Comercial Padaria~1.wav', 330, 8);
        for ($h = 0; $h < 24; $h++) {
            make($sid, (int) $kindFolder['time'], sprintf('HRS%02d.wav', $h), 1000, 1);
            make($sid, (int) $kindFolder['time'], sprintf('HRS%02d_O.wav', $h), 1100, 1);
        }
        for ($m = 0; $m < 60; $m++) {
            make($sid, (int) $kindFolder['time'], sprintf('MIN%02d.wav', $m), 1200, 1);
        }
        for ($t = 0; $t <= 40; $t++) {
            make($sid, (int) $kindFolder['temperature'], sprintf('TMP%03d.wav', $t), 700, 1);
        }
        for ($u = 0; $u <= 100; $u += 1) {
            make($sid, (int) $kindFolder['humidity'], sprintf('HUM%03d.wav', $u), 600, 1);
        }
        $items = array_map([Media::class, 'toItem'], $songs);
        array_splice($items, 2, 0, [['type' => 'time', 'title' => 'Hora certa'], Media::toItem($jingle)]);
        DB::update('playlists', ['items' => json_encode($items, JSON_UNESCAPED_UNICODE)], 'station_id = ?', [$sid]);
        DB::update('jingle_pages', ['name' => 'Principais', 'buttons' => json_encode([
            ['slot' => 0, 'label' => 'Prefixo', 'mode' => 'normal', 'item' => Media::toItem($jingle)],
            ['slot' => 1, 'label' => 'Hora', 'mode' => 'normal', 'item' => ['type' => 'time', 'title' => 'Hora certa']],
            ['slot' => 2, 'label' => 'Sobre', 'mode' => 'sweeper', 'item' => Media::toItem($jingle)],
        ], JSON_UNESCAPED_UNICODE)], 'station_id = ? AND page_no = 0', [$sid]);
        $set = (int) DB::value('SELECT active_event_set_id FROM stations WHERE id = ?', [$sid]);
        DB::insert('events', ['station_id' => $sid, 'event_set_id' => $set, 'name' => 'Hora certa de hora em hora', 'start_at' => date('Y-m-d') . ' 00:00:00',
            'periodicity' => 'hourly', 'item' => json_encode(['type' => 'time', 'title' => 'Hora certa']), 'wait_mode' => 'delayed', 'priority' => 'low', 'max_wait_s' => 300]);
        echo "Rádio demo criada.\n  login: $email\n  senha: $pass\n";
        break;

    case 'cleanup':
        foreach (glob(STORAGE_PATH . '/tmp/*/*.part') ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                unlink($f);
            }
        }
        $days = (int) env('LOG_RETENTION_DAYS', '730');
        DB::run('DELETE FROM play_log WHERE played_at < ?', [date('Y-m-d H:i:s', time() - $days * 86400)]);
        echo "Limpeza concluída.\n";
        break;

    default:
        echo "Comandos: migrate | superadmin <email> <nome> | demo | cleanup\n";
}

/** Gera um WAV mono 22 kHz com um tom (só para testes). */
function make(int $sid, int $folder, string $name, int $freq, int $seconds): array
{
    $rate = 22050;
    $n = $rate * $seconds;
    $data = '';
    for ($i = 0; $i < $n; $i++) {
        $env = min(1, $i / 2000, ($n - $i) / 2000);
        $data .= pack('v', (int) (sin(2 * M_PI * $freq * $i / $rate) * 8000 * $env) & 0xFFFF);
    }
    $wav = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16) . 'data' . pack('V', strlen($data)) . $data;
    $tmp = STORAGE_PATH . '/tmp/demo-' . bin2hex(random_bytes(4));
    file_put_contents($tmp, $wav);
    return Media::ingest($sid, $folder, $tmp, $name, $seconds * 1000);
}
