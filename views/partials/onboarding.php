<?php
$st = station();
if (!$st || !\App\Core\Auth::isManager()) {
    return;
}
$steps = \App\Services\Onboarding::steps((int) $st['id']);
$done = count(array_filter($steps, fn ($s) => $s['done']));
if ($done === count($steps)) {
    return;
}
$next = array_values(array_filter($steps, fn ($s) => !$s['done']))[0];
?>
<aside class="onboarding card" data-onboarding aria-label="Primeiros passos">
  <div class="ob-head">
    <b>Primeiros passos: <?= $done ?> de <?= count($steps) ?></b>
    <button type="button" class="btn btn-ghost btn-icon" data-onboarding-hide aria-label="Ocultar primeiros passos" title="Ocultar">×</button>
  </div>
  <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?= count($steps) ?>" aria-valuenow="<?= $done ?>"><i style="width:<?= round($done / count($steps) * 100) ?>%"></i></div>
  <ol class="ob-steps">
    <?php foreach ($steps as $s): ?>
      <li class="<?= $s['done'] ? 'done' : '' ?>"><?= $s['done'] ? '✓' : '○' ?> <?= e($s['label']) ?></li>
    <?php endforeach ?>
  </ol>
  <a class="ob-next" href="<?= e(url($next['href'])) ?>">Próximo passo: <?= e($next['label']) ?> →</a>
</aside>
