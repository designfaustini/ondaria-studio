<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? app_name()) ?></title>
<link rel="icon" href="<?= e(asset('img/icon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="public">
<header class="pub-header">
  <a class="brand" href="<?= e(url('/')) ?>"><img src="<?= e(asset('img/icon.svg')) ?>" alt="" width="28" height="28"> <?= e(app_name()) ?></a>
  <nav>
    <?php if (user()): ?>
      <a class="btn" href="<?= e(url('/estudio')) ?>">Abrir estúdio</a>
    <?php else: ?>
      <a href="<?= e(url('/entrar')) ?>">Entrar</a>
      <a class="btn btn-primary" href="<?= e(url('/cadastro')) ?>">Criar rádio</a>
    <?php endif ?>
  </nav>
</header>
<main class="pub-main">
  <?= \App\Core\View::partial('partials/flash') ?>
  <?= $content ?>
</main>
<footer class="pub-footer">© <?= date('Y') ?> <?= e(app_name()) ?></footer>
</body>
</html>
