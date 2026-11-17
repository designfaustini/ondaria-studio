<?php $manager = \App\Core\Auth::isManager(); ?>
<div class="page-head"><h1>Vinhetas</h1></div>
<div class="tabs">
  <?php foreach ($pages as $p): ?>
    <a class="<?= (int) $p['page_no'] === $page ? 'active' : '' ?>" href="<?= e(url('/vinhetas?pagina=' . $p['page_no'])) ?>"><?= (int) $p['page_no'] + 1 ?>. <?= e($p['name']) ?></a>
  <?php endforeach ?>
</div>
<form method="post" action="<?= e(url('/vinhetas')) ?>" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="page" value="<?= $page ?>">
  <label>Nome da página <input name="name" value="<?= e($pageName) ?>" maxlength="60" <?= $manager ? '' : 'disabled' ?>></label>
  <table class="table">
    <thead><tr><th>Tecla</th><th>Áudio</th><th>Rótulo</th><th>Modo</th></tr></thead>
    <tbody>
    <?php for ($slot = 0; $slot < 9; $slot++): $b = $buttons[$slot]; $src = $b ? (($b['item']['type'] ?? '') === 'track' ? (string) $b['item']['media_id'] : $b['item']['type']) : ''; ?>
      <tr>
        <td><kbd><?= $slot + 1 ?></kbd></td>
        <td>
          <select name="source[<?= $slot ?>]" <?= $manager ? '' : 'disabled' ?>>
            <option value="">(vazio)</option>
            <optgroup label="Locuções"><option value="time" <?= $src === 'time' ? 'selected' : '' ?>>Hora certa</option><option value="temperature" <?= $src === 'temperature' ? 'selected' : '' ?>>Temperatura</option><option value="humidity" <?= $src === 'humidity' ? 'selected' : '' ?>>Umidade</option></optgroup>
            <?php $last = null; foreach ($media as $m): if ($m['folder'] !== $last): ?><?= $last !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($m['folder']) ?>"><?php $last = $m['folder']; endif ?>
              <option value="<?= (int) $m['id'] ?>" <?= $src === (string) $m['id'] ? 'selected' : '' ?>><?= e($m['filename']) ?></option>
            <?php endforeach ?><?= $last !== null ? '</optgroup>' : '' ?>
          </select>
        </td>
        <td><input name="label[<?= $slot ?>]" value="<?= e($b['label'] ?? '') ?>" maxlength="24" <?= $manager ? '' : 'disabled' ?>></td>
        <td>
          <select name="mode[<?= $slot ?>]" <?= $manager ? '' : 'disabled' ?>>
            <?php foreach (['normal' => 'Normal', 'loop' => 'Loop (repete até clicar de novo)', 'sweeper' => 'Sweeper (abaixa a música)'] as $k => $l): ?>
              <option value="<?= $k ?>" <?= ($b['mode'] ?? 'normal') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach ?>
          </select>
        </td>
      </tr>
    <?php endfor ?>
    </tbody>
  </table>
  <?php if ($manager): ?><button class="btn btn-primary">Salvar página</button><?php endif ?>
  <p class="hint">No estúdio, as teclas 1 a 9 disparam as vinhetas da página escolhida. Também é possível arrastar um arquivo da biblioteca para um botão.</p>
</form>
