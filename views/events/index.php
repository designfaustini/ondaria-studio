<?php
$periodLabel = static function (array $e): string {
    $time = substr((string) $e['start_at'], 11, 8);
    return match ($e['periodicity']) {
        'once' => 'Uma vez em ' . date('d/m/Y H:i:s', strtotime((string) $e['start_at'])),
        'hourly' => 'De hora em hora, aos ' . substr($time, 3) . ' min',
        'hours' => 'Nas horas ' . implode(', ', array_filter(range(0, 23), fn ($h) => $e['hours_mask'] & (1 << $h))) . ' (min ' . substr($time, 3, 2) . ')',
        default => 'Diário às ' . $time,
    };
};
$days = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];
$manager = \App\Core\Auth::isManager();
?>
<div class="page-head">
  <h1>Eventos</h1>
  <?php if ($manager): ?><a class="btn btn-primary" href="<?= e(url('/eventos/novo?grade=' . $setId)) ?>">Novo evento</a><?php endif ?>
</div>

<div class="tabs">
  <?php foreach ($sets as $s): ?>
    <a class="<?= (int) $s['id'] === $setId ? 'active' : '' ?>" href="<?= e(url('/eventos?grade=' . $s['id'])) ?>"><?= e($s['name']) ?> <small>(<?= (int) $s['total'] ?>)</small><?= (int) $s['id'] === $activeSet ? ' <span class="tag on">ativa</span>' : '' ?></a>
  <?php endforeach ?>
</div>

<?php if ($manager): ?>
<div class="row gap">
  <?php if ($setId !== $activeSet): ?>
    <form method="post" action="<?= e(url('/eventos/grades/' . $setId . '/ativar')) ?>"><?= csrf_field() ?><button class="btn btn-small">Ativar esta grade</button></form>
    <form method="post" action="<?= e(url('/eventos/grades/' . $setId . '/excluir')) ?>" data-confirm="Excluir esta grade e seus eventos?"><?= csrf_field() ?><button class="btn btn-small btn-danger">Excluir grade</button></form>
  <?php endif ?>
  <details class="inline"><summary class="btn btn-small">Renomear</summary>
    <form method="post" action="<?= e(url('/eventos/grades')) ?>" class="form compact"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $setId ?>"><input name="name" required placeholder="Novo nome"><button class="btn btn-small">Salvar</button></form>
  </details>
  <details class="inline"><summary class="btn btn-small">Nova grade</summary>
    <form method="post" action="<?= e(url('/eventos/grades')) ?>" class="form compact"><?= csrf_field() ?><input name="name" required placeholder="Ex.: Grade de domingo"><button class="btn btn-small">Criar</button></form>
  </details>
  <form method="post" action="<?= e(url('/eventos/acao')) ?>"><?= csrf_field() ?><input type="hidden" name="grade" value="<?= $setId ?>"><input type="hidden" name="action" value="delete_expired"><button class="btn btn-small">Excluir expirados</button></form>
</div>
<?php endif ?>

<form method="post" action="<?= e(url('/eventos/acao')) ?>" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="grade" value="<?= $setId ?>">
  <table class="table">
    <thead><tr><th><input type="checkbox" data-checkall></th><th>Evento</th><th>Quando</th><th>Dias</th><th>Espera</th><th>Prioridade</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($events as $e): ?>
      <tr class="<?= $e['expired'] ? 'expired' : '' ?> <?= $e['enabled'] ? '' : 'disabled' ?>">
        <td><input type="checkbox" name="ids[]" value="<?= (int) $e['id'] ?>"></td>
        <td><b><?= e($e['name']) ?></b><br><small class="muted"><?= e($e['item']['title'] ?? '') ?></small></td>
        <td><?= e($periodLabel($e)) ?><?= $e['expires_at'] ? '<br><small class="muted">expira ' . e(date('d/m/Y H:i', strtotime((string) $e['expires_at']))) . '</small>' : '' ?></td>
        <td class="days"><?php foreach ($days as $i => $d): ?><span class="<?= $e['weekdays_mask'] & (1 << $i) ? 'on' : '' ?>"><?= $d ?></span><?php endforeach ?></td>
        <td><?= $e['wait_mode'] === 'immediate' ? 'Imediato' : 'Espera fim (máx. ' . round($e['max_wait_s'] / 60, 1) . ' min)' ?></td>
        <td><?= $e['priority'] === 'high' ? 'Alta' : 'Baixa' ?></td>
        <td><?php if ($manager): ?><a class="btn btn-small" href="<?= e(url('/eventos/' . $e['id'])) ?>">Editar</a><?php endif ?></td>
      </tr>
    <?php endforeach ?>
    <?php if (!$events): ?><tr><td colspan="7" class="muted">Nenhum evento nesta grade.</td></tr><?php endif ?>
    </tbody>
  </table>
  <?php if ($manager && $events): ?>
  <div class="row gap">
    <button name="action" value="enable" class="btn btn-small">Ativar</button>
    <button name="action" value="disable" class="btn btn-small">Desativar</button>
    <select name="target_set"><?php foreach ($sets as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach ?></select>
    <button name="action" value="copy" class="btn btn-small">Copiar para a grade</button>
    <button name="action" value="delete" class="btn btn-small btn-danger" data-confirm-click="Excluir os eventos selecionados?">Excluir</button>
  </div>
  <?php endif ?>
</form>
<p class="hint">Os eventos são disparados pelo estúdio principal aberto no navegador. Mantenha uma aba do estúdio aberta no computador da rádio.</p>
