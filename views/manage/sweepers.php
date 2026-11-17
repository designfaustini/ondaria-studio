<?php
$manager = \App\Core\Auth::isManager();
$mediaOptions = function () use ($media): void {
    $last = null;
    foreach ($media as $m) {
        if ($m['folder'] !== $last) {
            echo ($last !== null ? '</optgroup>' : '') . '<optgroup label="' . e($m['folder']) . '">';
            $last = $m['folder'];
        }
        echo '<option value="' . (int) $m['id'] . '">' . e($m['filename']) . '</option>';
    }
    echo $last !== null ? '</optgroup>' : '';
};
?>
<div class="page-head">
  <div>
    <h1>Sweepers</h1>
    <p>Uma locução, vinheta ou a hora certa tocando <b>por cima</b> de uma música, no segundo escolhido. A música abaixa enquanto isso.</p>
  </div>
</div>

<?php if ($manager && $media): ?>
<form method="post" action="<?= e(url('/sweepers')) ?>" class="card form">
  <h2>Adicionar</h2>
  <?= csrf_field() ?>
  <div class="grid3">
    <div class="field"><label for="sw-media">Na música</label><select id="sw-media" name="media_id" required><?php $mediaOptions() ?></select></div>
    <div class="field"><label for="sw-src">Tocar por cima</label>
      <select id="sw-src" name="source" required>
        <optgroup label="Locuções automáticas"><option value="time">Hora certa</option><option value="temperature">Temperatura</option><option value="humidity">Umidade</option></optgroup>
        <?php $mediaOptions() ?>
      </select></div>
    <div class="field"><label for="sw-off">Aos quantos segundos</label><input id="sw-off" name="offset_s" type="number" min="0" max="3600" step="0.5" value="5" required inputmode="decimal"></div>
  </div>
  <div class="actions"><button class="btn btn-primary" data-busy="Adicionando…">Adicionar sweeper</button></div>
</form>
<?php elseif (!$media): ?>
  <p class="notice">Envie áudios na <a href="<?= e(url('/biblioteca')) ?>">Biblioteca</a> para criar sweepers.</p>
<?php endif ?>

<section class="card stack-sm">
  <h2>Sweepers cadastrados <small class="muted"><?= count($sweepers) ?></small></h2>
  <?php if ($sweepers): ?>
  <form method="post" action="<?= e(url('/sweepers/excluir')) ?>" data-selectable>
    <?= csrf_field() ?>
    <div class="table-wrap">
      <table class="table table-responsive">
        <thead><tr><?php if ($manager): ?><th class="check-col"><label><input type="checkbox" data-checkall aria-label="Selecionar todos"></label></th><?php endif ?><th>Música</th><th>Por cima</th><th>Aos</th></tr></thead>
        <tbody>
        <?php foreach ($sweepers as $s): ?>
          <tr><?php if ($manager): ?><td class="check-col"><label><input type="checkbox" name="ids[]" value="<?= (int) $s['id'] ?>" aria-label="Selecionar"></label></td><?php endif ?><td><?= e($s['filename']) ?></td><td><?= e($s['item']['title'] ?? '') ?></td><td><?= e(rtrim(rtrim(number_format((float) $s['offset_s'], 2, ',', ''), '0'), ',')) ?> s</td></tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php if ($manager): ?>
    <div class="selection-bar" data-selection-bar hidden>
      <b data-count></b>
      <button class="btn btn-small btn-danger" data-confirm-count="Excluir {n} sweeper(s)?">Excluir</button>
    </div>
    <?php endif ?>
  </form>
  <?php else: ?>
    <p class="empty-state"><b>Nenhum sweeper ainda.</b>Exemplo: a hora certa aos 8 segundos da introdução de uma música.</p>
  <?php endif ?>
</section>
