<?php
$qs = fn (array $extra) => url('/registro?' . http_build_query(array_merge(['modo' => $mode, 'de' => $from, 'ate' => $to, 'q' => $q], $extra)));
$today = date('Y-m-d');
$presets = [
    'Hoje' => [$today, $today],
    'Ontem' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
    'Últimos 7 dias' => [date('Y-m-d', strtotime('-6 days')), $today],
    'Este mês' => [date('Y-m-01'), $today],
    'Mês passado' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
];
$errors = count(array_filter($rows, fn ($r) => $r['status'] === 'error'));
?>
<div class="page-head">
  <div>
    <h1>Registro de execução</h1>
    <p>O que tocou e quando. Use para conferir a programação e gerar relatórios para anunciantes.</p>
  </div>
</div>

<form method="get" action="<?= e(url('/registro')) ?>" class="card form" id="logform">
  <div class="preset-chips" aria-label="Períodos rápidos">
    <?php foreach ($presets as $label => [$d1, $d2]): ?>
      <a href="<?= e($qs(['de' => $d1, 'ate' => $d2])) ?>" <?= $from === $d1 && $to === $d2 ? 'aria-current="true"' : '' ?>><?= e($label) ?></a>
    <?php endforeach ?>
  </div>
  <div class="grid3">
    <div class="field"><label for="modo">Mostrar</label>
      <select id="modo" name="modo">
        <option value="tudo" <?= $mode === 'tudo' ? 'selected' : '' ?>>Tudo o que tocou</option>
        <option value="arquivo" <?= $mode === 'arquivo' ? 'selected' : '' ?>>Só um arquivo (ex.: um comercial)</option>
        <option value="erros" <?= $mode === 'erros' ? 'selected' : '' ?>>Só o que deu erro</option>
      </select></div>
    <div class="field" id="qfield"><label for="q">Nome do arquivo</label><div class="search"><input id="q" type="search" name="q" value="<?= e($q) ?>" placeholder="ex.: padaria" data-msg-required="Digite parte do nome do arquivo."></div></div>
    <div class="field"><label for="de">De</label><input id="de" type="date" name="de" value="<?= e($from) ?>" max="<?= $today ?>" required></div>
    <div class="field"><label for="ate">Até</label><input id="ate" type="date" name="ate" value="<?= e($to) ?>" max="<?= $today ?>" required></div>
  </div>
  <div class="actions">
    <button class="btn btn-primary" data-busy="Buscando…">Buscar</button>
    <details class="dd">
      <summary class="btn">Exportar relatório <span aria-hidden="true">▾</span></summary>
      <div class="dd-menu">
        <a href="<?= e($qs(['exportar' => 'txt'])) ?>">Texto (.txt) — para enviar ao anunciante</a>
        <a href="<?= e($qs(['exportar' => 'csv'])) ?>">Planilha (.csv) — abre no Excel</a>
      </div>
    </details>
  </div>
</form>

<section class="card stack-sm" aria-live="polite">
  <p class="muted small"><b><?= number_format(count($rows), 0, ',', '.') ?></b> registro(s) de <?= e(date('d/m/Y', strtotime($from))) ?> a <?= e(date('d/m/Y', strtotime($to))) ?><?= $errors ? " · <span style=\"color:var(--err)\">$errors com erro</span>" : '' ?><?= count($rows) >= 5000 ? ' — mostrando os 5.000 primeiros; exporte para ver todos' : '' ?>.</p>
  <?php if ($rows): ?>
  <div class="table-wrap">
    <table class="table dense">
      <thead><tr><th>Data e hora</th><th>Origem</th><th>Título</th><th>Situação</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="<?= $r['status'] === 'error' ? 'err' : '' ?>">
          <td style="white-space:nowrap"><?= e(date('d/m/Y H:i:s', strtotime((string) $r['played_at']))) ?></td>
          <td><?= e($r['source']) ?></td>
          <td><?= e($r['title']) ?></td>
          <td><?= $r['status'] === 'error' ? '⚠ ' . e($r['detail']) : '✓ Tocou' ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="empty-state"><b>Nada neste período.</b>Escolha outro período acima. O registro começa a partir do primeiro play no estúdio.</p>
  <?php endif ?>
</section>
