<?php
$periodLabel = static function (array $e): string {
    $time = substr((string) $e['start_at'], 11, 8);
    return match ($e['periodicity']) {
        'once' => 'Uma vez, em ' . date('d/m/Y \à\s H:i', strtotime((string) $e['start_at'])),
        'hourly' => 'De hora em hora, no minuto ' . substr($time, 3, 2),
        'hours' => 'Às ' . implode(', ', array_map(fn ($h) => sprintf('%02d:%s', $h, substr($time, 3, 2)), array_filter(range(0, 23), fn ($h) => $e['hours_mask'] & (1 << $h)))),
        default => 'Todo dia às ' . substr($time, 0, 5),
    };
};
$days = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
$dayNames = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];
$manager = \App\Core\Auth::isManager();
$current = array_values(array_filter($sets, fn ($s) => (int) $s['id'] === $setId))[0] ?? null;
$expired = count(array_filter($events, fn ($e) => $e['expired']));
?>
<div class="page-head">
  <div>
    <h1>Eventos</h1>
    <p>O que toca sozinho, na hora marcada. Disparados pelo estúdio principal aberto no computador da rádio.</p>
  </div>
  <?php if ($manager): ?><a class="btn btn-primary" href="<?= e(url('/eventos/novo?grade=' . $setId)) ?>">+ Novo evento</a><?php endif ?>
</div>

<section class="card stack-sm">
  <nav class="tabs" aria-label="Grades de eventos">
    <?php foreach ($sets as $s): ?>
      <a <?= (int) $s['id'] === $setId ? 'aria-current="page"' : '' ?> href="<?= e(url('/eventos?grade=' . $s['id'])) ?>"><?= e($s['name']) ?> <small class="muted"><?= (int) $s['total'] ?></small><?= (int) $s['id'] === $activeSet ? ' <span class="tag on">no ar</span>' : '' ?></a>
    <?php endforeach ?>
  </nav>

  <div class="row">
    <?php if ($setId === $activeSet): ?>
      <span class="muted small">Esta é a grade que está valendo agora.</span>
    <?php elseif ($manager): ?>
      <span class="muted small">Esta grade não está no ar.</span>
      <form method="post" action="<?= e(url('/eventos/grades/' . $setId . '/ativar')) ?>"><?= csrf_field() ?><button class="btn btn-small">Colocar esta grade no ar</button></form>
    <?php endif ?>
    <span style="flex:1"></span>
    <?php if ($manager): ?>
    <details class="dd dd-right">
      <summary class="btn btn-small">Mais <span aria-hidden="true">▾</span></summary>
      <div class="dd-menu">
        <button type="button" data-open="dlg-set-new">Nova grade</button>
        <button type="button" data-open="dlg-set-rename">Renomear esta grade</button>
        <?php if ($expired): ?>
          <form method="post" action="<?= e(url('/eventos/acao')) ?>"><?= csrf_field() ?><input type="hidden" name="grade" value="<?= $setId ?>"><input type="hidden" name="action" value="delete_expired"><button data-confirm="Excluir os <?= $expired ?> evento(s) expirado(s)?">Excluir expirados (<?= $expired ?>)</button></form>
        <?php endif ?>
        <?php if ($setId !== $activeSet): ?>
          <hr>
          <form method="post" action="<?= e(url('/eventos/grades/' . $setId . '/excluir')) ?>"><?= csrf_field() ?><button class="danger" data-confirm="Excluir a grade “<?= e($current['name'] ?? '') ?>” e os <?= count($events) ?> evento(s) dela? Isso não pode ser desfeito.">Excluir esta grade…</button></form>
        <?php endif ?>
      </div>
    </details>
    <?php endif ?>
  </div>

  <?php if ($events): ?>
  <form method="post" action="<?= e(url('/eventos/acao')) ?>" data-selectable>
    <?= csrf_field() ?>
    <input type="hidden" name="grade" value="<?= $setId ?>">
    <div class="table-wrap">
      <table class="table table-responsive">
        <thead><tr><?php if ($manager): ?><th class="check-col"><label><input type="checkbox" data-checkall aria-label="Selecionar todos"></label></th><?php endif ?><th>Evento</th><th>Quando</th><th>Dias</th><th>Comportamento</th><th class="actions-col"><span class="sr-only">Ações</span></th></tr></thead>
        <tbody>
        <?php foreach ($events as $e): ?>
          <tr class="<?= $e['expired'] ? 'expired' : '' ?> <?= $e['enabled'] ? '' : 'disabled' ?>">
            <?php if ($manager): ?><td class="check-col"><label><input type="checkbox" name="ids[]" value="<?= (int) $e['id'] ?>" aria-label="Selecionar <?= e($e['name']) ?>"></label></td><?php endif ?>
            <td><b><?= e($e['name']) ?></b><?= $e['enabled'] ? '' : ' <span class="tag off">desativado</span>' ?><?= $e['expired'] ? ' <span class="tag">expirado</span>' : '' ?><br><small class="muted"><?= e($e['item']['title'] ?? '') ?></small></td>
            <td><?= e($periodLabel($e)) ?><?= $e['expires_at'] && !$e['expired'] ? '<br><small class="muted">até ' . e(date('d/m/Y', strtotime((string) $e['expires_at']))) . '</small>' : '' ?></td>
            <td class="days" aria-label="Dias: <?= e(implode(', ', array_filter($dayNames, fn ($d, $i) => $e['weekdays_mask'] & (1 << $i), ARRAY_FILTER_USE_BOTH))) ?>"><?php foreach ($days as $i => $d): ?><span class="<?= $e['weekdays_mask'] & (1 << $i) ? 'on' : '' ?>" aria-hidden="true"><?= $d ?></span><?php endforeach ?></td>
            <td><?= $e['wait_mode'] === 'immediate' ? 'Interrompe na hora' : 'Espera a música acabar' ?><?= $e['priority'] === 'high' ? ' · prioridade alta' : '' ?></td>
            <td class="actions-col"><?php if ($manager): ?><a class="btn btn-small" href="<?= e(url('/eventos/' . $e['id'])) ?>">Editar</a><?php endif ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php if ($manager): ?>
    <div class="selection-bar" data-selection-bar hidden>
      <b data-count></b>
      <button name="action" value="enable" class="btn btn-small">Ativar</button>
      <button name="action" value="disable" class="btn btn-small">Desativar</button>
      <?php if (count($sets) > 1): ?>
        <label class="sr-only" for="target_set">Copiar para a grade</label>
        <select id="target_set" name="target_set"><?php foreach ($sets as $s): if ((int) $s['id'] !== $setId): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endif; endforeach ?></select>
        <button name="action" value="copy" class="btn btn-small">Copiar</button>
      <?php endif ?>
      <button name="action" value="delete" class="btn btn-small btn-danger" data-confirm-count="Excluir {n} evento(s)? Isso não pode ser desfeito.">Excluir</button>
    </div>
    <?php endif ?>
  </form>
  <?php else: ?>
    <div class="empty-state"><b>Nenhum evento nesta grade.</b>Comece pela hora certa de hora em hora: é o evento mais usado.
      <?php if ($manager): ?><p style="margin-top:16px"><a class="btn" href="<?= e(url('/eventos/novo?grade=' . $setId)) ?>">Criar o primeiro evento</a></p><?php endif ?></div>
  <?php endif ?>
</section>

<?php if ($manager): ?>
<dialog class="modal" id="dlg-set-new" aria-labelledby="dsn-t">
  <form method="post" action="<?= e(url('/eventos/grades')) ?>" class="form">
    <?= csrf_field() ?>
    <h2 id="dsn-t">Nova grade</h2>
    <div class="field"><label for="dsn-name">Nome</label><input id="dsn-name" name="name" required maxlength="120" placeholder="Ex.: Grade de domingo"></div>
    <p class="muted small">Você pode trocar a grade no ar a qualquer momento, ou agendar a troca com o evento "Carregar grade".</p>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Cancelar</button><button class="btn btn-primary">Criar grade</button></div>
  </form>
</dialog>
<dialog class="modal" id="dlg-set-rename" aria-labelledby="dsr-t">
  <form method="post" action="<?= e(url('/eventos/grades')) ?>" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $setId ?>">
    <h2 id="dsr-t">Renomear grade</h2>
    <div class="field"><label for="dsr-name">Nome</label><input id="dsr-name" name="name" required maxlength="120" value="<?= e($current['name'] ?? '') ?>"></div>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Cancelar</button><button class="btn btn-primary">Salvar</button></div>
  </form>
</dialog>
<?php endif ?>
