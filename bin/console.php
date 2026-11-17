<?php
declare(strict_types=1);

/*
 * Comandos:
 *   php bin/console.php migrate                       cria as tabelas, aplica as migrações e registra os áudios de demonstração
 *   php bin/console.php demo:sync                     relê a pasta audios/ (áudios de demonstração para todas as rádios)
 *   php bin/console.php superadmin email@x.com "Nome"  cria/atualiza o administrador da plataforma (pede a senha)
 *   php bin/console.php demo                           cria a rádio de demonstração (só fora de produção)
 *   php bin/console.php cleanup                        retenção de dados, uploads incompletos e rádios encerradas (cron diário)
 */

// Achado N2: este arquivo nunca responde pela web, mesmo se o .htaccess não for respeitado.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Services\Media;
use App\Services\Stations;

$cmd = $argv[1] ?? '';

$runSql = static function (string $sql): void {
    if (DB::driver() === 'sqlite') {
        $sql = DB::mysqlToSqlite($sql);
    }
    $sql = (string) preg_replace('/^--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        DB::pdo()->exec($stmt);
    }
};

switch ($cmd) {
    case 'migrate':
        $hasPlans = DB::driver() === 'sqlite'
            ? (bool) DB::value("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'plans'")
            : (bool) DB::value("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'plans'");
        if (!$hasPlans) {
            $runSql((string) file_get_contents(BASE_PATH . '/database/schema.sql'));
            echo "Tabelas base criadas.\n";
        }
        DB::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(120) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL)');
        $done = array_column(DB::all('SELECT name FROM schema_migrations'), 'name');
        foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            $runSql((string) file_get_contents($file));
            DB::insert('schema_migrations', ['name' => $name, 'applied_at' => now()]);
            echo "Migração aplicada: $name\n";
        }
        echo "Banco atualizado.\n";
        // fall through: já registra os áudios de demonstração

    case 'demo:sync':
        $stats = \App\Services\DemoLibrary::sync(true);
        echo $stats === null
            ? "Pasta de demonstração não encontrada (" . \App\Services\DemoLibrary::dir() . ").\n"
            : "Áudios de demonstração: {$stats['files']} arquivo(s) · {$stats['added']} novo(s), {$stats['updated']} atualizado(s), {$stats['removed']} removido(s).\n";
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
        if (env('APP_ENV') === 'production') {
            exit("A rádio de demonstração não pode ser criada em produção (APP_ENV=production).\n");
        }
        $email = 'demo@ondaria.test';
        if (DB::value('SELECT 1 FROM users WHERE email = ?', [$email])) {
            exit("A rádio de demonstração já existe.\n");
        }
        $pass = getenv('DEMO_PASSWORD') ?: bin2hex(random_bytes(6));
        $plan = (int) DB::value("SELECT id FROM plans WHERE slug = 'pro'");
        $sid = Stations::create('Rádio Demonstração', $plan);
        DB::insert('users', ['station_id' => $sid, 'name' => 'Operador Demo', 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => 'owner',
            'terms_version' => \App\Services\Legal::termsVersion(), 'terms_accepted_at' => now()]);
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
        // Política de retenção (docs/LGPD.md). Prazos configuráveis no .env.
        $ago = static fn (int $days) => date('Y-m-d H:i:s', time() - $days * 86400);
        foreach (glob(STORAGE_PATH . '/tmp/*/*.part') ?: [] as $f) {
            if (filemtime($f) < time() - Media::PART_TTL) {
                unlink($f);
            }
        }
        $n = [
            'registro de execução' => DB::run('DELETE FROM play_log WHERE played_at < ?', [$ago((int) env('LOG_RETENTION_DAYS', '730'))])->rowCount(),
            'registros de acesso (Marco Civil)' => DB::run('DELETE FROM access_log WHERE created_at < ?', [$ago((int) env('ACCESS_LOG_DAYS', '180'))])->rowCount(),
            'auditoria' => DB::run('DELETE FROM audit_log WHERE created_at < ?', [$ago((int) env('AUDIT_RETENTION_DAYS', '365'))])->rowCount(),
            'tentativas de login' => DB::run('DELETE FROM login_attempts WHERE attempted_at < ?', [$ago(1)])->rowCount(),
        ];
        // Rádios encerradas pelo dono: apagadas de vez depois da carência.
        foreach (DB::all("SELECT id, name FROM stations WHERE status = 'closing' AND closing_at IS NOT NULL AND closing_at < ?", [now()]) as $st) {
            $dir = STORAGE_PATH . '/media/' . (int) $st['id'];
            foreach (glob($dir . '/*') ?: [] as $f) {
                unlink($f);
            }
            @rmdir($dir);
            DB::run('DELETE FROM stations WHERE id = ?', [$st['id']]);
            echo "Rádio encerrada e apagada: {$st['name']} (#{$st['id']})\n";
        }
        // Rotação do log de erros do PHP (guarda 90 dias).
        $errLog = STORAGE_PATH . '/logs/php-error.log';
        if (is_file($errLog) && filesize($errLog) > 5 * 1024 * 1024) {
            rename($errLog, STORAGE_PATH . '/logs/php-error-' . date('Ymd-His') . '.log');
        }
        foreach (glob(STORAGE_PATH . '/logs/php-error-*.log') ?: [] as $f) {
            if (filemtime($f) < time() - 90 * 86400) {
                unlink($f);
            }
        }
        foreach ($n as $what => $count) {
            echo "$what: $count apagado(s)\n";
        }
        echo "Limpeza concluída.\n";
        break;

    default:
        echo "Comandos: migrate | demo:sync | superadmin <email> <nome> | demo | cleanup\n";
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
