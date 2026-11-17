<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
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
        if (!Security::throttle('login:' . Security::clientIp(), 20, 900) || !Security::throttle('login:' . $email, 8, 900)) {
            flash('error', 'Muitas tentativas. Aguarde 15 minutos.');
            redirect('/entrar');
        }
        if (!Auth::attempt($email, (string) ($_POST['password'] ?? ''))) {
            remember_input($_POST);
            flash('error', 'E-mail ou senha incorretos.');
            redirect('/entrar');
        }
        $intended = (string) Session::get('intended', '');
        Session::forget('intended');
        if (Auth::user()['role'] === 'superadmin') {
            redirect('/admin');
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
        if (mb_strlen($station) < 2 || mb_strlen($station) > 120) $errors[] = 'Informe o nome da rádio.';
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors[] = 'Informe seu nome.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
        if (strlen($pass) < 10) $errors[] = 'A senha precisa ter pelo menos 10 caracteres.';
        if (!in_array($tz, \DateTimeZone::listIdentifiers(), true)) $tz = 'America/Sao_Paulo';
        if (!$errors && DB::value('SELECT 1 FROM users WHERE email = ?', [$email])) $errors[] = 'Já existe uma conta com este e-mail.';
        if (empty($_POST['terms'])) $errors[] = 'Aceite os termos de uso.';
        if ($errors) {
            foreach ($errors as $err) {
                flash('error', $err);
            }
            redirect('/cadastro');
        }
        // Toda rádio começa no plano gratuito; a troca de plano é feita pelo administrador da plataforma.
        $planId = (int) DB::value("SELECT id FROM plans WHERE slug = 'gratis'") ?: (int) DB::value('SELECT MIN(id) FROM plans');
        $userId = DB::transaction(static function () use ($station, $planId, $tz, $name, $email, $pass): int {
            $sid = Stations::create($station, $planId, $tz);
            return DB::insert('users', ['station_id' => $sid, 'name' => $name, 'email' => $email, 'password_hash' => password_hash($pass, PASSWORD_DEFAULT), 'role' => 'owner']);
        });
        Auth::login($userId);
        flash('success', 'Rádio criada! Envie suas músicas na Biblioteca e monte a primeira lista.');
        redirect('/biblioteca');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/entrar');
    }
}
