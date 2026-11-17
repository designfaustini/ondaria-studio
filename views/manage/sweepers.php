<?php $manager = \App\Core\Auth::isManager(); $mediaOptions = function (string $selected = '') use ($media): void {
    $last = null;
    foreach ($media as $m) {
        if ($m['folder'] !== $last) {
            echo ($last !== null ? '</optgroup>' : '') . '<optgroup label="' . e($m['folder']) . '">';
            $last = $m['folder'];
        }
        echo '<option value="' . (int) $m['id'] . '">' . e($m['filename']) . '</option>';
    }
    echo $last !== null ? '</optgroup>' : '';
}; ?>
<div class="page-head"><h1>Sweepers (voicetracking)</h1></div>
<p class="muted">Toca uma locução, vinheta ou a hora certa <b>por cima</b> de uma música, no segundo escolhido, abaixando o volume da música enquanto isso.</p>
<?php if ($manager): ?>
<form method="post" action="<?= e(url('/sweepers')) ?>" class="card form grid3">
  <?= csrf_field() ?>
  <label>Música <select name="media_id" required><?php $mediaOptions() ?></select></label>
  <label>Sweeper
    <select name="source" required>
      <optgroup label="Locuções"><option value="time">Hora certa</option><option value="temperature">Temperatura</option><option value="humidity">Umidade</option></optgroup>
      <?php $mediaOptions() ?>
    </select>
  </label>
  <label>Aos (segundos do início) <input name="offset_s" inputmode="decimal" value="5" required></label>
  <button class="btn btn-primary">Adicionar</button>
</form>
<?php endif ?>
<form method="post" action="<?= e(url('/sweepers/excluir')) ?>" class="card">
  <?= csrf_field() ?>
  <table class="table">
    <thead><tr><th><input type="checkbox" data-checkall></th><th>Música</th><th>Sweeper</th><th>Aos</th></tr></thead>
    <tbody>
    <?php foreach ($sweepers as $s): ?>
      <tr><td><input type="checkbox" name="ids[]" value="<?= (int) $s['id'] ?>"></td><td><?= e($s['filename']) ?></td><td><?= e($s['item']['title'] ?? '') ?></td><td><?= e(rtrim(rtrim(number_format((float) $s['offset_s'], 2, ',', ''), '0'), ',')) ?> s</td></tr>
    <?php endforeach ?>
    <?php if (!$sweepers): ?><tr><td colspan="4" class="muted">Nenhum sweeper.</td></tr><?php endif ?>
    </tbody>
  </table>
  <?php if ($manager && $sweepers): ?>
    <div class="row gap"><button class="btn btn-small btn-danger">Excluir selecionados</button><button class="btn btn-small btn-danger" name="all" value="1" data-confirm-click="Excluir todos os sweepers?">Excluir todos</button></div>
  <?php endif ?>
</form>
