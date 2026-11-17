<?php
$it = $event['item'];
$start = (string) $event['start_at'];
$exp = $event['expires_at'] ? (string) $event['expires_at'] : date('Y-m-d 23:59:59', strtotime('+30 days'));
$days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$type = $it['type'] ?? 'time';
?>
<div class="page-head"><h1><?= e($title) ?></h1><a class="btn" href="<?= e(url('/eventos?grade=' . $event['event_set_id'])) ?>">Voltar</a></div>

<form method="post" action="<?= e(url('/eventos/salvar')) ?>" class="card form grid2" id="eventform">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">

  <fieldset>
    <legend>Identificação</legend>
    <label>Nome (opcional) <input name="name" value="<?= e($event['name']) ?>" maxlength="160"></label>
    <label>Grade <select name="event_set_id"><?php foreach ($sets as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $event['event_set_id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></label>
    <label class="check"><input type="checkbox" name="enabled" value="1" <?= $event['enabled'] ? 'checked' : '' ?>> Evento ativo</label>
  </fieldset>

  <fieldset>
    <legend>Início e expiração</legend>
    <div class="row gap">
      <label>Data <input type="date" name="start_date" value="<?= e(substr($start, 0, 10)) ?>" required></label>
      <label>Hora <input type="time" step="1" name="start_time" value="<?= e(substr($start, 11, 8)) ?>" required></label>
    </div>
    <label class="check"><input type="checkbox" name="expires" value="1" <?= $event['expires_at'] ? 'checked' : '' ?> data-toggle="#expbox"> Expira</label>
    <div class="row gap" id="expbox">
      <label>Data <input type="date" name="expires_date" value="<?= e(substr($exp, 0, 10)) ?>"></label>
      <label>Hora <input type="time" step="1" name="expires_time" value="<?= e(substr($exp, 11, 8)) ?>"></label>
    </div>
  </fieldset>

  <fieldset>
    <legend>Periodicidade</legend>
    <?php foreach (['once' => 'Só uma vez', 'daily' => 'Todo dia, na hora de início', 'hourly' => 'De hora em hora (no minuto de início)', 'hours' => 'Em horas escolhidas (no minuto de início)'] as $k => $label): ?>
      <label class="check"><input type="radio" name="periodicity" value="<?= $k ?>" <?= $event['periodicity'] === $k ? 'checked' : '' ?>> <?= e($label) ?></label>
    <?php endforeach ?>
    <div class="hours" id="hoursbox">
      <?php for ($h = 0; $h < 24; $h++): ?>
        <label><input type="checkbox" name="hours[]" value="<?= $h ?>" <?= $event['hours_mask'] & (1 << $h) ? 'checked' : '' ?>><span><?= sprintf('%02d', $h) ?></span></label>
      <?php endfor ?>
    </div>
    <p class="small muted">Dias da semana</p>
    <div class="hours">
      <?php foreach ($days as $i => $d): ?>
        <label><input type="checkbox" name="weekdays[]" value="<?= $i ?>" <?= $event['weekdays_mask'] & (1 << $i) ? 'checked' : '' ?>><span><?= $d ?></span></label>
      <?php endforeach ?>
    </div>
  </fieldset>

  <fieldset>
    <legend>O que executar</legend>
    <label>Tipo
      <select name="item_type" id="item_type">
        <?php foreach ($types as $k => $label): ?><option value="<?= e($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
      </select>
    </label>
    <label data-for="track">Arquivo
      <select name="media_id">
        <?php $last = null; foreach ($media as $m): if ($m['folder'] !== $last): ?><?= $last !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($m['folder']) ?>"><?php $last = $m['folder']; endif ?>
          <option value="<?= (int) $m['id'] ?>" <?= (int) ($it['media_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['filename']) ?></option>
        <?php endforeach ?><?= $last !== null ? '</optgroup>' : '' ?>
      </select>
    </label>
    <label data-for="random">Pasta <select name="folder_id"><?php foreach ($folders as $f): ?><option value="<?= (int) $f['id'] ?>" <?= (int) ($it['folder_id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach ?></select></label>
    <label data-for="playlist rotation sequence">Lista <select name="playlist_id"><?php foreach ($playlists as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) ($it['playlist_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>[<?= e(['list' => 'lista', 'rotation' => 'rotação', 'sequence' => 'sequência'][$p['kind']]) ?>] <?= e($p['name']) ?></option><?php endforeach ?></select></label>
    <label data-for="events">Grade a carregar <select name="target_set_id"><?php foreach ($sets as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) ($it['event_set_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></label>
    <label data-for="stream">Endereço da rádio online (não use .m3u/.pls) <input name="url" type="url" value="<?= e($it['url'] ?? '') ?>" placeholder="https://servidor:8000/stream"></label>
    <label data-for="stream">Nome da rádio online <input name="stream_title" value="<?= e($type === 'stream' ? ($it['title'] ?? '') : '') ?>"></label>
    <label data-for="pause stream satellite">Duração (segundos) <input type="number" name="seconds" min="0" max="86400" value="<?= (int) ($it['seconds'] ?? 60) ?>"></label>
    <label data-for="postit">Texto do lembrete <textarea name="text" rows="4"><?= e($it['text'] ?? '') ?></textarea></label>
  </fieldset>

  <fieldset>
    <legend>Comportamento</legend>
    <label class="check"><input type="radio" name="wait_mode" value="delayed" <?= $event['wait_mode'] !== 'immediate' ? 'checked' : '' ?>> Espera a faixa atual terminar</label>
    <label class="check"><input type="radio" name="wait_mode" value="immediate" <?= $event['wait_mode'] === 'immediate' ? 'checked' : '' ?>> Imediato (interrompe o que estiver tocando)</label>
    <label>Prioridade
      <select name="priority"><option value="low" <?= $event['priority'] !== 'high' ? 'selected' : '' ?>>Baixa (descartado se outro evento estiver tocando)</option><option value="high" <?= $event['priority'] === 'high' ? 'selected' : '' ?>>Alta</option></select>
    </label>
    <label>Espera máxima (minutos) <input name="max_wait_min" inputmode="decimal" value="<?= e(rtrim(rtrim(number_format($event['max_wait_s'] / 60, 2, '.', ''), '0'), '.')) ?>"></label>
  </fieldset>

  <div class="row gap full"><button class="btn btn-primary">Salvar evento</button><a class="btn" href="<?= e(url('/eventos?grade=' . $event['event_set_id'])) ?>">Cancelar</a></div>
</form>
