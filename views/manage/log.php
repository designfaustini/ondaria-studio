<?php $qs = fn (string $fmt) => url('/registro?' . http_build_query(['modo' => $mode, 'de' => $from, 'ate' => $to, 'q' => $q, 'exportar' => $fmt])); ?>
<div class="page-head"><h1>Registro de execução</h1></div>
<form method="get" action="<?= e(url('/registro')) ?>" class="card row gap wrap">
  <label>Buscar
    <select name="modo">
      <option value="tudo" <?= $mode === 'tudo' ? 'selected' : '' ?>>Tudo</option>
      <option value="arquivo" <?= $mode === 'arquivo' ? 'selected' : '' ?>>Um arquivo</option>
      <option value="erros" <?= $mode === 'erros' ? 'selected' : '' ?>>Somente erros</option>
    </select>
  </label>
  <label>Nome do arquivo <input name="q" value="<?= e($q) ?>" placeholder="ex.: comercial padaria"></label>
  <label>De <input type="date" name="de" value="<?= e($from) ?>"></label>
  <label>Até <input type="date" name="ate" value="<?= e($to) ?>"></label>
  <button class="btn btn-primary">Buscar</button>
  <a class="btn" href="<?= e($qs('txt')) ?>">Exportar TXT</a>
  <a class="btn" href="<?= e($qs('csv')) ?>">Exportar CSV</a>
</form>
<div class="card">
  <p class="muted small"><?= count($rows) ?> registro(s)<?= count($rows) >= 5000 ? ' — mostrando os primeiros 5000; exporte para ver todos' : '' ?>.</p>
  <table class="table dense">
    <thead><tr><th>Data e hora</th><th>Origem</th><th>Tipo</th><th>Título</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="<?= $r['status'] === 'error' ? 'err' : '' ?>">
        <td><?= e(date('d/m/Y H:i:s', strtotime((string) $r['played_at']))) ?></td>
        <td><?= e($r['source']) ?></td>
        <td><?= e($r['kind']) ?></td>
        <td><?= e($r['title']) ?></td>
        <td><?= $r['status'] === 'error' ? 'Erro: ' . e($r['detail']) : 'OK' ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
