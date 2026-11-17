<?php
$st = station();
$u = user();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$nav = $st ? [
    ['/estudio', 'Estúdio'],
    ['/biblioteca', 'Biblioteca'],
    ['/listas', 'Listas'],
    ['/eventos', 'Eventos'],
    ['/vinhetas', 'Vinhetas'],
    ['/sweepers', 'Sweepers'],
    ['/registro', 'Registro'],
] : [];
if ($st && \App\Core\Auth::isManager()) {
    $nav[] = ['/opcoes', 'Opções'];
    $nav[] = ['/usuarios', 'Usuários'];
}
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
<header class="app-header">
  <a class="brand" href="<?= e(url($st ? '/estudio' : '/admin')) ?>"><img src="<?= e(asset('img/icon.svg')) ?>" alt="" width="24" height="24"> <?= e($st['name'] ?? app_name()) ?></a>
  <nav class="app-nav">
    <?php foreach ($nav as [$href, $label]): ?>
      <a href="<?= e(url($href)) ?>" class="<?= str_starts_with((string) $path, url($href)) ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach ?>
    <?php if (($u['role'] ?? '') === 'superadmin'): ?>
      <a href="<?= e(url('/admin')) ?>" class="<?= str_starts_with((string) $path, url('/admin')) ? 'active' : '' ?>">Plataforma</a>
    <?php endif ?>
  </nav>
  <div class="app-user">
    <?php if (($u['role'] ?? '') === 'superadmin' && $st): ?>
      <form method="post" action="<?= e(url('/admin/radios/fechar')) ?>"><?= csrf_field() ?><button class="btn btn-small">Sair da rádio</button></form>
    <?php endif ?>
    <a href="<?= e(url('/conta')) ?>"><?= e($u['name'] ?? '') ?></a>
    <form method="post" action="<?= e(url('/sair')) ?>"><?= csrf_field() ?><button class="link">Sair</button></form>
  </div>
</header>
<main class="app-main">
  <?= \App\Core\View::partial('partials/flash') ?>
  <?= $content ?>
</main>
<script src="<?= e(asset('js/manage.js')) ?>" defer></script>
</body>
</html>
