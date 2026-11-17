<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Services\Legal;
use App\Core\Security;
use App\Core\Session;
use App\Services\Stations;

final class AuthController
{
    public function loginForm(): string
    {
        return view('auth/login', ['title' => 'Entrar'], 'public');
    }

    public function login(): void
    {
        $email = mb_strtolower((string) input('email', ''));
        // Achado F4: o limite por conta é contado junto com o IP, para um anônimo não travar o login de outra pessoa.
        if (!Security::throttle('login:' . Security::clientIp(), 20, 900) || !Security::throttle('login:' . $email . '|' . Security::clientIp(), 8, 900)) {
            field_errors(['password' => 'Muitas tentativas seguidas. Por segurança, espere 15 minutos e tente de novo.'], $_POST);
            redirect('/entrar');
        }
        if (!Auth::attempt($email, (string) ($_POST['password'] ?? ''))) {
            field_errors(['password' => 'E-mail ou senha não conferem. Confira os dois e tente de novo.'], $_POST);
            redirect('/entrar');
        }
        $intended = (string) Session::get('intended', '');
        Session::forget('intended');
        if (Auth::user()['role'] === 'superadmin') {
            redirect('/admin');
        }
        // O endereço guardado já contém APP_BASE_PATH; tira para o redirect não repetir a base.
        $base = rtrim((string) env('APP_BASE_PATH', ''), '/');
        if ($base !== '' && str_starts_with($intended, $base)) {
            $intended = substr($intended, strlen($base)) ?: '/';
        }
        redirect(str_starts_with($intended, '/') && !str_starts_with($intended, '//') ? $intended : '/estudio');
    }

    public function signupForm(): string
    {
        $plans = DB::all('SELECT * FROM plans WHERE active = 1 ORDER BY price_cents');
        return view('auth/signup', ['title' => 'Criar rádio', 'plans' => $plans, 'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::AMERICA | \DateTimeZone::EUROPE | \DateTimeZone::AFRICA | \DateTimeZone::ATLANTIC)], 'public');
    }

    public function signup(): void
    {
        remember_input($_POST);
        if (env('SIGNUP_ENABLED', 'true') !== 'true') {
            flash('error', 'Cadastros fechados no momento.');
            redirect('/cadastro');
        }
        if (!Security::throttle('signup:' . Security::clientIp(), 5, 3600)) {
            flash('error', 'Muitos cadastros a partir deste endereço. Tente mais tarde.');
            redirect('/cadastro');
        }
        $station = trim((string) input('station', ''));
        $name = trim((string) input('name', ''));
        $email = mb_strtolower(trim((string) input('email', '')));
        $pass = (string) ($_POST['password'] ?? '');
        $tz = (string) input('timezone', 'America/Sao_Paulo');
        $errors = [];
        if (mb_strlen($station) < 2 || mb_strlen($station) > 120) $errors['station'] = 'Digite o nome da rádio (de 2 a 120 caracteres).';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors['name'] = 'Digite seu nome.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Digite um e-mail válido, como nome@dominio.com.br.';
        if (strlen($pass) < 10) $errors['password'] = 'Use pelo menos 10 caracteres na senha.';
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) $tz = 'America/Sao_Paulo';
        if (!isset($errors['email']) && DB::value('SELECT 1 FROM users WHERE email = ?', [$email])) $errors['email'] = 'Este e-mail já tem conta. Entre com ele ou use outro e-mail.';
        if (empty($_POST['terms'])) $errors['terms'] = 'Para continuar, aceite os termos.';
        if ($errors) {
            field_errors($errors, $_POST);
            redirect('/cadastro');
        }
        // Toda rádio começa no plano gratuito; a troca de plano é feita pelo administrador da plataforma.
        $planId = (int) DB::value("SELECT id FROM plans WHERE slug = 'gratis'") ?: (int) DB::value('SELECT MIN(id) FROM plans');
        $userId = DB::transaction(static function () use ($station, $planId, $tz, $name, $email, $pass): int {
            $sid = Stations::create($station, $planId, $tz);
            return DB::insert('users', ['station_id' => $sid, 'name' => $name, 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => 'owner',
                // Prova do aceite (LGPD art. 8º e CDC): versão dos termos e data/hora.
                'terms_version' => Legal::termsVersion(), 'terms_accepted_at' => now()]);
        });
        Auth::login($userId);
        Audit::access('cadastro', $userId);
        Audit::record('station.create', 'station', $station);
        flash('success', "Pronto, a {$station} foi criada. Agora envie as músicas: arraste os arquivos para a área abaixo.");
        redirect('/biblioteca');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/entrar');
    }
}
