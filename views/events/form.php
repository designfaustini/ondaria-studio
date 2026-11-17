<?php
$it = $event['item'];
$start = (string) $event['start_at'];
$exp = $event['expires_at'] ? (string) $event['expires_at'] : date('Y-m-d 23:59:59', strtotime('+30 days'));
$days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$type = $it['type'] ?? 'time';
// Tipos mais usados primeiro; o resto num grupo.
$common = ['time' => 'Hora certa', 'track' => 'Um arquivo (vinheta, comercial, programa)', 'random' => 'Uma música aleatória de uma pasta', 'sequence' => 'Uma sequência (bloco comercial)'];
$others = array_diff_key($types, $common);
?>
<div class="page-head">
  <div>
    <h1><?= e($title) ?></h1>
    <p>Diga o que tocar e quando. O resto já vem preenchido com o mais comum.</p>
  </div>
</div>

<form method="post" action="<?= e(url('/eventos/salvar')) ?>" class="card form" id="eventform" style="max-width:760px">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">

  <fieldset>
    <legend>1. O que tocar</legend>
    <div class="field">
      <label for="item_type">Tipo</label>
      <select name="item_type" id="item_type">
        <?php foreach ($common as $k => $label): ?><option value="<?= e($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
        <optgroup label="Outros">
          <?php foreach ($others as $k => $label): ?><option value="<?= e($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
        </optgroup>
      </select>
    </div>
    <div class="field" data-for="track">
      <label for="media_id">Arquivo</label>
      <?php if ($media): ?>
      <select id="media_id" name="media_id" required>
        <?php $last = null; foreach ($media as $m): if ($m['folder'] !== $last): ?><?= $last !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($m['folder']) ?>"><?php $last = $m['folder']; endif ?>
          <option value="<?= (int) $m['id'] ?>" <?= (int) ($it['media_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['filename']) ?></option>
        <?php endforeach ?><?= $last !== null ? '</optgroup>' : '' ?>
      </select>
      <?php else: ?><p class="notice">Nenhum arquivo ainda. <a href="<?= e(url('/biblioteca')) ?>">Envie na Biblioteca</a>.</p><?php endif ?>
    </div>
    <div class="field" data-for="random"><label for="folder_id">Pasta (inclui subpastas)</label><select id="folder_id" name="folder_id"><?php foreach ($folders as $f): if ($f['kind'] === 'normal'): ?><option value="<?= (int) $f['id'] ?>" <?= (int) ($it['folder_id'] ?? 0) === (int) $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endif; endforeach ?></select></div>
    <div class="field" data-for="playlist rotation sequence">
      <label for="playlist_id">Lista</label>
      <?php if ($playlists): ?>
      <select id="playlist_id" name="playlist_id"><?php foreach ($playlists as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) ($it['playlist_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e(['list' => 'Lista', 'rotation' => 'Rotação', 'sequence' => 'Sequência'][$p['kind']]) ?>: <?= e($p['name']) ?></option><?php endforeach ?></select>
      <?php else: ?><p class="notice">Nenhuma lista salva. <a href="<?= e(url('/listas')) ?>">Crie em Listas</a>.</p><?php endif ?>
    </div>
    <div class="field" data-for="events"><label for="target_set_id">Grade que vai entrar no ar</label><select id="target_set_id" name="target_set_id"><?php foreach ($sets as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) ($it['event_set_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></div>
    <div class="field" data-for="stream"><label for="url">Endereço da rádio online</label><input id="url" name="url" type="url" required value="<?= e($it['url'] ?? '') ?>" placeholder="https://servidor:8000/stream"><small>Use o endereço do áudio, não o de um arquivo .m3u ou .pls.</small></div>
    <div class="field" data-for="stream"><label for="stream_title">Nome da rádio online</label><input id="stream_title" name="stream_title" value="<?= e($type === 'stream' ? ($it['title'] ?? '') : '') ?>" placeholder="Ex.: Rede Nacional"></div>
    <div class="field" data-for="pause stream satellite"><label for="seconds">Duração (segundos)</label><input id="seconds" type="number" name="seconds" min="1" max="86400" required value="<?= (int) ($it['seconds'] ?? 60) ?>"></div>
    <div class="field" data-for="postit"><label for="text">Texto do lembrete</label><textarea id="text" name="text" rows="3" required maxlength="5000"><?= e($it['text'] ?? '') ?></textarea></div>
  </fieldset>

  <fieldset>
    <legend>2. Quando</legend>
    <div class="radio-cards" role="radiogroup" aria-label="Repetição">
      <?php foreach (['hourly' => ['De hora em hora', 'Toca toda hora, no minuto escolhido abaixo.'], 'daily' => ['Todo dia', 'Uma vez por dia, no horário abaixo.'], 'hours' => ['Em horas escolhidas', 'Marque as horas; usa o minuto do horário abaixo.'], 'once' => ['Só uma vez', 'Na data e hora abaixo.']] as $k => [$label, $help]): ?>
        <label><input type="radio" name="periodicity" value="<?= $k ?>" <?= $event['periodicity'] === $k ? 'checked' : '' ?>><span><b><?= e($label) ?></b><small><?= e($help) ?></small></span></label>
      <?php endforeach ?>
    </div>
    <div class="field" id="hoursbox">
      <span class="label">Horas</span>
      <div class="choice-grid">
        <?php for ($h = 0; $h < 24; $h++): ?>
          <label><input type="checkbox" name="hours[]" value="<?= $h ?>" <?= $event['hours_mask'] & (1 << $h) ? 'checked' : '' ?>><span><?= sprintf('%02dh', $h) ?></span></label>
        <?php endfor ?>
      </div>
      <p class="field-error" id="hours-err" hidden>Marque pelo menos uma hora.</p>
    </div>
    <div class="grid3">
      <div class="field"><label for="start_date">A partir de</label><input id="start_date" type="date" name="start_date" value="<?= e(substr($start, 0, 10)) ?>" required></div>
      <div class="field"><label for="start_time">Horário</label><input id="start_time" type="time" step="1" name="start_time" value="<?= e(substr($start, 11, 8)) ?>" required></div>
    </div>
    <div class="field">
      <span class="label">Dias da semana</span>
      <div class="choice-grid">
        <?php foreach ($days as $i => $d): ?>
          <label><input type="checkbox" name="weekdays[]" value="<?= $i ?>" <?= $event['weekdays_mask'] & (1 << $i) ? 'checked' : '' ?>><span><?= $d ?></span></label>
        <?php endforeach ?>
      </div>
    </div>
  </fieldset>

  <details class="advanced" <?= $event['id'] && ($event['wait_mode'] === 'immediate' || $event['priority'] === 'high' || $event['expires_at'] || !$event['enabled']) ? 'open' : '' ?>>
    <summary>Avançado: espera, prioridade, validade, nome e grade</summary>
    <fieldset>
      <div class="radio-cards" role="radiogroup" aria-label="Quando chegar a hora">
        <label><input type="radio" name="wait_mode" value="delayed" <?= $event['wait_mode'] !== 'immediate' ? 'checked' : '' ?>><span><b>Esperar a música acabar</b> <span class="tag on">recomendado</span><small>Não corta nada no ar.</small></span></label>
        <label><input type="radio" name="wait_mode" value="immediate" <?= $event['wait_mode'] === 'immediate' ? 'checked' : '' ?>><span><b>Tocar na hora exata</b><small>Interrompe o que estiver tocando (ex.: rede, jornal).</small></span></label>
      </div>
      <div class="grid3">
        <div class="field"><label for="max_wait_min">Espera máxima (minutos)</label><input id="max_wait_min" name="max_wait_min" type="number" min="0" max="1440" step="0.5" value="<?= e(rtrim(rtrim(number_format($event['max_wait_s'] / 60, 2, '.', ''), '0'), '.')) ?>"><small>Se atrasar mais que isso, não toca.</small></div>
        <div class="field"><label for="priority">Prioridade</label><select id="priority" name="priority"><option value="low" <?= $event['priority'] !== 'high' ? 'selected' : '' ?>>Normal</option><option value="high" <?= $event['priority'] === 'high' ? 'selected' : '' ?>>Alta</option></select><small>Eventos normais são descartados se outro de prioridade alta estiver tocando.</small></div>
      </div>
      <label class="check"><input type="checkbox" name="expires" value="1" <?= $event['expires_at'] ? 'checked' : '' ?>> <span>Tem data para acabar</span></label>
      <div class="grid3" id="expbox">
        <div class="field"><label for="expires_date">Até o dia</label><input id="expires_date" type="date" name="expires_date" value="<?= e(substr($exp, 0, 10)) ?>" required></div>
        <div class="field"><label for="expires_time">Hora</label><input id="expires_time" type="time" step="1" name="expires_time" value="<?= e(substr($exp, 11, 8)) ?>" required></div>
      </div>
      <div class="grid3">
        <div class="field"><label for="name">Nome do evento</label><input id="name" name="name" value="<?= e($event['name']) ?>" maxlength="160" placeholder="Automático, pelo que vai tocar"></div>
        <div class="field"><label for="event_set_id">Grade</label><select id="event_set_id" name="event_set_id"><?php foreach ($sets as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $event['event_set_id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach ?></select></div>
      </div>
      <label class="check"><input type="checkbox" name="enabled" value="1" <?= $event['enabled'] ? 'checked' : '' ?>> <span>Evento ativo</span></label>
    </fieldset>
  </details>

  <div class="actions sticky">
    <a class="btn btn-ghost" href="<?= e(url('/eventos?grade=' . $event['event_set_id'])) ?>">Cancelar</a>
    <span class="spacer"></span>
    <?php if ($event['id']): ?>
      <button form="delform" class="btn btn-danger" data-confirm="Excluir este evento? Isso não pode ser desfeito.">Excluir</button>
    <?php endif ?>
    <button class="btn btn-primary" data-busy="Salvando…">Salvar evento</button>
  </div>
</form>
<?php if ($event['id']): ?>
<form method="post" action="<?= e(url('/eventos/acao')) ?>" id="delform" hidden><?= csrf_field() ?><input type="hidden" name="grade" value="<?= (int) $event['event_set_id'] ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="ids[]" value="<?= (int) $event['id'] ?>"></form>
<?php endif ?>
