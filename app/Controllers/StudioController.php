<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Session;
use App\Services\Media;

final class StudioController
{
    public function index(): string
    {
        return view('studio/index', ['title' => 'Estúdio — ' . station()['name']], 'studio');
    }

    public function media(int $id): void
    {
        // A rádio lê os próprios arquivos e os de demonstração; nunca os de outra rádio.
        $m = Media::readable(Auth::stationId(), $id);
        Session::release();
        if (!$m) {
            abort(404);
        }
        Media::stream($m);
    }
}
