<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? app_name()) ?></title>
<link rel="icon" href="<?= e(asset('img/icon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('css/studio.css')) ?>">
<meta name="csrf" content="<?= e(\App\Core\Csrf::token()) ?>">
<meta name="base" content="<?= e(rtrim(url('/'), '/')) ?>">
</head>
<body class="studio">
<?= $content ?>
<script type="module" src="<?= e(asset('js/studio.js')) ?>"></script>
</body>
</html>
