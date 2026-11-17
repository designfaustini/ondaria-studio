<?php
$st = station();
$u = user();
$manager = \App\Core\Auth::isManager();
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$is = fn (string $href) => str_starts_with($path, url($href));
// Lei de Hick: só as 3 áreas mais usadas ficam soltas; o resto vai para "Mais".
$primary = $st ? [['/estudio', 'Estúdio'], ['/biblioteca', 'Biblioteca'], ['/eventos', 'Eventos']] : [];
$more = $st ? [['/listas', 'Listas'], ['/vinhetas', 'Vinhetas'], ['/sweepers', 'Sweepers'], ['/registro', 'Registro de execução']] : [];
if ($st && $manager) {
    $more[] = ['/opcoes', 'Opções da rádio'];
    $more[] = ['/usuarios', 'Usuários'];
    $more[] = ['/auditoria', 'Auditoria'];
}
$moreActive = array_filter($more, fn ($m) => $is($m[0]));
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($title ?? '') . ' · ' . app_name()) ?></title>
<link rel="icon" href="<?= e(asset('img/icon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<meta name="csrf" content="<?= e(\App\Core\Csrf::token()) ?>">
<meta name="base" content="<?= e(rtrim(url('/'), '/')) ?>">
</head>
<body class="app">
<a class="skip" href="#conteudo">Pular para o conteúdo</a>
<header class="app-header">
  <a class="brand" href="<?= e(url($st ? '/estudio' : '/admin')) ?>" title="Início"><img src="<?= e(asset('img/icon.svg')) ?>" alt="" width="28" height="28"> <span><?= e($st['name'] ?? app_name()) ?></span></a>
  <nav class="app-nav" aria-label="Principal">
    <?php foreach ($primary as [$href, $label]): ?>
      <a href="<?= e(url($href)) ?>" <?= $is($href) ? 'aria-current="page" class="active"' : '' ?>><?= e($label) ?></a>
    <?php endforeach ?>
    <?php if ($more): ?>
      <details class="dd">
        <summary class="<?= $moreActive ? 'active' : '' ?>">Mais<?= $moreActive ? ': ' . e(array_values($moreActive)[0][1]) : '' ?> <span aria-hidden="true">▾</span></summary>
        <div class="dd-menu">
          <?php foreach ($more as [$href, $label]): ?>
            <a href="<?= e(url($href)) ?>" <?= $is($href) ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
          <?php endforeach ?>
        </div>
      </details>
    <?php endif ?>
    <?php if (($u['role'] ?? '') === 'superadmin'): ?>
      <a href="<?= e(url('/admin')) ?>" <?= $is('/admin') ? 'aria-current="page" class="active"' : '' ?>>Plataforma</a>
    <?php endif ?>
  </nav>
  <details class="dd dd-right user-menu">
    <summary><span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) ($u['name'] ?? '?'), 0, 1))) ?></span><span class="uname"><?= e($u['name'] ?? '') ?></span> <span aria-hidden="true">▾</span></summary>
    <div class="dd-menu">
      <a href="<?= e(url('/conta')) ?>">Minha conta</a>
      <?php if (($u['role'] ?? '') === 'superadmin' && $st): ?>
        <form method="post" action="<?= e(url('/admin/radios/fechar')) ?>"><?= csrf_field() ?><button>Sair da rádio <?= e($st['name']) ?></button></form>
      <?php endif ?>
      <form method="post" action="<?= e(url('/sair')) ?>"><?= csrf_field() ?><button>Sair</button></form>
    </div>
  </details>
</header>
<main class="app-main" id="conteudo">
  <?php if ($st && $st['status'] === 'closing'): ?>
    <div class="flash flash-warn" role="status"><span class="flash-icon" aria-hidden="true">!</span>
      <div><p><b>Esta rádio será apagada em <?= e(date('d/m/Y', strtotime((string) $st['closing_at']))) ?>.</b> Baixe seus dados antes dessa data, se precisar.</p>
      <?php if (($u['role'] ?? '') === 'owner'): ?><a href="<?= e(url('/conta#excluir')) ?>">Baixar dados ou cancelar o encerramento</a><?php endif ?></div></div>
  <?php endif ?>
  <?= \App\Core\View::partial('partials/flash') ?>
  <?= \App\Core\View::partial('partials/onboarding') ?>
  <?= $content ?>
</main>
<?= \App\Core\View::partial('partials/footer') ?>
<script src="<?= e(asset('js/manage.js')) ?>" defer></script>
</body>
</html>
