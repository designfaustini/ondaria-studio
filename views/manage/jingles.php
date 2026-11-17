<?php
$manager = \App\Core\Auth::isManager();
$dis = $manager ? '' : ' disabled';
$filled = count(array_filter($buttons));
?>
<div class="page-head">
  <div>
    <h1>Vinhetas</h1>
    <p>Ligue um áudio a cada tecla de 1 a 9. No estúdio, é só apertar a tecla.</p>
  </div>
</div>

<form method="post" action="<?= e(url('/vinhetas')) ?>" class="card form">
  <?= csrf_field() ?>
  <input type="hidden" name="page" value="<?= $page ?>">
  <nav class="tabs" aria-label="Páginas de vinhetas">
    <?php foreach ($pages as $p): ?>
      <a <?= (int) $p['page_no'] === $page ? 'aria-current="page"' : '' ?> href="<?= e(url('/vinhetas?pagina=' . $p['page_no'])) ?>"><?= (int) $p['page_no'] + 1 ?>. <?= e($p['name']) ?></a>
    <?php endforeach ?>
  </nav>
  <?php if (!$manager): ?><p class="notice">Só administradores mudam as vinhetas. Você pode usá-las no estúdio.</p><?php endif ?>
  <div class="field" style="max-width:360px">
    <label for="pname">Nome desta página</label>
    <input id="pname" name="name" value="<?= e($pageName) ?>" maxlength="60"<?= $dis ?>>
    <small><?= $filled ?> de 9 teclas preenchidas</small>
  </div>
  <div class="table-wrap">
    <table class="table table-responsive">
      <thead><tr><th>Tecla</th><th>Áudio</th><th>Rótulo no botão</th><th>Como toca</th></tr></thead>
      <tbody>
      <?php for ($slot = 0; $slot < 9; $slot++): $b = $buttons[$slot]; $src = $b ? (($b['item']['type'] ?? '') === 'track' ? (string) $b['item']['media_id'] : $b['item']['type']) : ''; ?>
        <tr>
          <td><kbd style="font-size:1.1rem"><?= $slot + 1 ?></kbd></td>
          <td>
            <label class="sr-only" for="src<?= $slot ?>">Áudio da tecla <?= $slot + 1 ?></label>
            <select id="src<?= $slot ?>" name="source[<?= $slot ?>]"<?= $dis ?>>
              <option value="">Vazia</option>
              <optgroup label="Locuções automáticas"><option value="time" <?= $src === 'time' ? 'selected' : '' ?>>Hora certa</option><option value="temperature" <?= $src === 'temperature' ? 'selected' : '' ?>>Temperatura</option><option value="humidity" <?= $src === 'humidity' ? 'selected' : '' ?>>Umidade</option></optgroup>
              <?php $last = null; foreach ($media as $m): if ($m['folder'] !== $last): ?><?= $last !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($m['folder']) ?>"><?php $last = $m['folder']; endif ?>
                <option value="<?= (int) $m['id'] ?>" <?= $src === (string) $m['id'] ? 'selected' : '' ?>><?= e($m['filename']) ?></option>
              <?php endforeach ?><?= $last !== null ? '</optgroup>' : '' ?>
            </select>
          </td>
          <td><label class="sr-only" for="lb<?= $slot ?>">Rótulo da tecla <?= $slot + 1 ?></label><input id="lb<?= $slot ?>" name="label[<?= $slot ?>]" value="<?= e($b['label'] ?? '') ?>" maxlength="24" placeholder="Automático"<?= $dis ?>></td>
          <td>
            <label class="sr-only" for="md<?= $slot ?>">Como a tecla <?= $slot + 1 ?> toca</label>
            <select id="md<?= $slot ?>" name="mode[<?= $slot ?>]"<?= $dis ?>>
              <?php foreach (['normal' => 'Normal', 'sweeper' => 'Por cima (abaixa a música)', 'loop' => 'Repetindo até apertar de novo'] as $k => $l): ?>
                <option value="<?= $k ?>" <?= ($b['mode'] ?? 'normal') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
              <?php endforeach ?>
            </select>
          </td>
        </tr>
      <?php endfor ?>
      </tbody>
    </table>
  </div>
  <?php if ($manager): ?>
  <div class="actions sticky">
    <span class="muted small">Dica: no estúdio, arraste um arquivo da biblioteca direto para o botão.</span>
    <span class="spacer"></span>
    <button class="btn btn-primary" data-busy="Salvando…">Salvar página</button>
  </div>
  <?php endif ?>
</form>
