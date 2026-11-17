<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? app_name()) ?></title>
<link rel="icon" href="<?= e(asset('img/icon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<meta name="base" content="<?= e(rtrim(url('/'), '/')) ?>">
</head>
<body class="public">
<a class="skip" href="#conteudo">Pular para o conteúdo</a>
<header class="pub-header">
  <a class="brand" href="<?= e(url('/')) ?>" title="Início"><img src="<?= e(asset('img/icon.svg')) ?>" alt="" width="28" height="28"> <span><?= e(app_name()) ?></span></a>
  <nav aria-label="Conta">
    <?php if (user()): ?>
      <a class="btn" href="<?= e(url('/estudio')) ?>">Abrir estúdio</a>
    <?php else: ?>
      <?php /* Botões do topo são secundários: a ação principal fica no conteúdo de cada tela. */ ?>
      <a class="btn btn-ghost" href="<?= e(url('/entrar')) ?>">Entrar</a>
      <a class="btn" href="<?= e(url('/cadastro')) ?>">Criar rádio</a>
    <?php endif ?>
  </nav>
</header>
<main class="pub-main" id="conteudo">
  <?= \App\Core\View::partial('partials/flash') ?>
  <?= $content ?>
</main>
<?= \App\Core\View::partial('partials/footer') ?>
<script src="<?= e(asset('js/manage.js')) ?>" defer></script>
</body>
</html>
