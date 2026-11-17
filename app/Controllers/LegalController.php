<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Session;
use App\Services\Legal;

/** Termos, privacidade, direitos autorais, contato e novo aceite quando os termos mudam. */
final class LegalController
{
    public function terms(): string
    {
        return view('legal/terms', ['title' => 'Termos de uso'], 'public');
    }

    public function privacy(): string
    {
        return view('legal/privacy', ['title' => 'Política de privacidade'], 'public');
    }

    public function copyright(): string
    {
        return view('legal/copyright', ['title' => 'Direitos autorais e remoção de conteúdo'], 'public');
    }

    public function contact(): string
    {
        return view('legal/contact', ['title' => 'Contato e identificação'], 'public');
    }

    public function acceptForm(): string
    {
        return view('legal/accept', ['title' => 'Termos atualizados'], 'public');
    }

    public function accept(): void
    {
        $u = user();
        if (empty($_POST['terms'])) {
            field_errors(['terms' => 'Para continuar usando o sistema, marque que leu e aceita os termos.']);
            redirect('/aceitar-termos');
        }
        DB::update('users', ['terms_version' => Legal::termsVersion(), 'terms_accepted_at' => now()], 'id = ?', [$u['id']]);
        Audit::record('terms.accept', 'user:' . $u['id'], Legal::termsVersion());
        $to = (string) Session::get('intended', '');
        Session::forget('intended');
        flash('success', 'Obrigado. Aceite registrado em ' . date('d/m/Y \à\s H:i') . '.');
        redirect(str_starts_with($to, '/') && !str_starts_with($to, '//') ? $to : ($u['role'] === 'superadmin' ? '/admin' : '/estudio'));
    }
}
