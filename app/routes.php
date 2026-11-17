<?php
declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\LegalController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\EventsController;
use App\Controllers\LibraryController;
use App\Controllers\ManageController;
use App\Controllers\PublicController;
use App\Controllers\StudioController;

/** @var App\Core\Router $router */

$router->get('/', [PublicController::class, 'home']);
$router->get('/termos', [LegalController::class, 'terms']);
$router->get('/privacidade', [LegalController::class, 'privacy']);
$router->get('/direitos-autorais', [LegalController::class, 'copyright']);
$router->get('/contato', [LegalController::class, 'contact']);
// Novo aceite: só exige login (sem a checagem de termos, senão entraria em laço).
$router->get('/aceitar-termos', [LegalController::class, 'acceptForm'], ['login']);
$router->post('/aceitar-termos', [LegalController::class, 'accept'], ['login']);
$router->get('/np/{slug:[a-z0-9-]+}.{fmt:json|txt}', [PublicController::class, 'nowPlaying']);

$router->group('', ['guest'], static function ($r): void {
    $r->get('/entrar', [AuthController::class, 'loginForm']);
    $r->post('/entrar', [AuthController::class, 'login']);
    $r->get('/cadastro', [AuthController::class, 'signupForm']);
    $r->post('/cadastro', [AuthController::class, 'signup']);
});
$router->post('/sair', [AuthController::class, 'logout'], ['auth']);

$router->group('', ['auth', 'station'], static function ($r): void {
    $r->get('/estudio', [StudioController::class, 'index']);
    $r->get('/media/{id}', [StudioController::class, 'media']);

    $r->get('/biblioteca', [LibraryController::class, 'index']);
    $r->post('/biblioteca/pastas', [LibraryController::class, 'createFolder']);
    $r->post('/biblioteca/pastas/{id}', [LibraryController::class, 'updateFolder'], ['manager']);
    $r->post('/biblioteca/pastas/{id}/excluir', [LibraryController::class, 'deleteFolder'], ['manager']);
    $r->post('/biblioteca/midias/{id}', [LibraryController::class, 'updateMedia']);
    $r->post('/biblioteca/midias/excluir', [LibraryController::class, 'deleteMedia'], ['manager']);

    $r->get('/listas', [ManageController::class, 'playlists']);
    $r->post('/listas', [ManageController::class, 'createPlaylist']);
    $r->post('/listas/{id}', [ManageController::class, 'updatePlaylist']);
    $r->post('/listas/{id}/excluir', [ManageController::class, 'deletePlaylist'], ['manager']);

    $r->get('/eventos', [EventsController::class, 'index']);
    $r->get('/eventos/novo', [EventsController::class, 'form'], ['manager']);
    $r->get('/eventos/{id}', [EventsController::class, 'form'], ['manager']);
    $r->post('/eventos/salvar', [EventsController::class, 'save'], ['manager']);
    $r->post('/eventos/acao', [EventsController::class, 'bulk'], ['manager']);
    $r->post('/eventos/grades', [EventsController::class, 'saveSet'], ['manager']);
    $r->post('/eventos/grades/{id}/ativar', [EventsController::class, 'activateSet'], ['manager']);
    $r->post('/eventos/grades/{id}/excluir', [EventsController::class, 'deleteSet'], ['manager']);

    $r->get('/vinhetas', [ManageController::class, 'jingles']);
    $r->post('/vinhetas', [ManageController::class, 'saveJingles'], ['manager']);
    $r->get('/sweepers', [ManageController::class, 'sweepers']);
    $r->post('/sweepers', [ManageController::class, 'addSweeper'], ['manager']);
    $r->post('/sweepers/excluir', [ManageController::class, 'deleteSweepers'], ['manager']);

    $r->get('/registro', [ManageController::class, 'log']);
    $r->get('/opcoes', [ManageController::class, 'options'], ['manager']);
    $r->post('/opcoes', [ManageController::class, 'saveOptions'], ['manager']);
    $r->get('/usuarios', [ManageController::class, 'users'], ['manager']);
    $r->post('/usuarios', [ManageController::class, 'saveUser'], ['manager']);
    $r->post('/usuarios/{id}/excluir', [ManageController::class, 'deleteUser'], ['manager']);
    $r->get('/auditoria', [ManageController::class, 'audit'], ['manager']);

    $r->get('/api/bootstrap', [ApiController::class, 'bootstrap']);
    $r->get('/api/library', [ApiController::class, 'library']);
    $r->post('/api/upload', [ApiController::class, 'upload']);
    $r->get('/api/playlists/{id}', [ApiController::class, 'playlist']);
    $r->post('/api/playlists', [ApiController::class, 'savePlaylist']);
    $r->post('/api/playlists/{id}/rotation-next', [ApiController::class, 'rotationNext']);
    $r->get('/api/random/{folder_id}', [ApiController::class, 'random']);
    $r->get('/api/tth/{kind:time|temperature|humidity}', [ApiController::class, 'tth']);
    $r->get('/api/events', [ApiController::class, 'events']);
    // Também usado pelo comando "Carregar grade" tocado por um operador.
    $r->post('/api/events/active', [ApiController::class, 'activateEvents']);
    $r->post('/api/jingles', [ApiController::class, 'saveJinglePage'], ['manager']);
    $r->post('/api/log', [ApiController::class, 'log']);
    $r->post('/api/nowplaying', [ApiController::class, 'nowPlaying']);
    $r->post('/api/heartbeat', [ApiController::class, 'heartbeat']);
    $r->post('/api/media/{id}/duration', [ApiController::class, 'mediaDuration']);
    $r->post('/api/state', [ApiController::class, 'saveState']);
});

// Minha conta e direitos do titular (LGPD art. 18): vale também para o superadmin, que não tem rádio.
$router->group('/conta', ['auth'], static function ($r): void {
    $r->get('', [AccountController::class, 'index']);
    $r->post('/perfil', [AccountController::class, 'profile']);
    $r->post('/senha', [AccountController::class, 'password']);
    $r->get('/exportar', [AccountController::class, 'export']);
    $r->post('/excluir', [AccountController::class, 'delete']);
    $r->post('/encerramento/cancelar', [AccountController::class, 'cancelClosing']);
});

$router->group('/admin', ['auth', 'super'], static function ($r): void {
    $r->get('', [AdminController::class, 'index']);
    $r->post('/radios/{id}', [AdminController::class, 'updateStation']);
    $r->post('/radios/{id}/abrir', [AdminController::class, 'openStation']);
    $r->post('/radios/fechar', [AdminController::class, 'closeStation']);
    $r->post('/planos/{id}', [AdminController::class, 'updatePlan']);
});
