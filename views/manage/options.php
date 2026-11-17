<?php
$s = $settings;
$chk = fn (string $k, string $label, string $help = '') => '<label class="check"><input type="checkbox" name="' . $k . '" value="1"' . ($s[$k] ? ' checked' : '') . '> <span>' . e($label) . ($help !== '' ? '<small class="muted" style="display:block">' . e($help) . '</small>' : '') . '</span></label>';
$num = fn (string $k, string $label, string $step, string $min, string $max, string $help = '') => '<div class="field"><label for="o-' . $k . '">' . e($label) . '</label><input id="o-' . $k . '" type="number" name="' . $k . '" value="' . e($s[$k]) . '" step="' . $step . '" min="' . $min . '" max="' . $max . '" inputmode="decimal" required>' . ($help !== '' ? '<small>' . e($help) . '</small>' : '') . '</div>';
$range = fn (string $k, string $label, string $help = '') => '<div class="field"><label for="o-' . $k . '">' . e($label) . '</label><div class="range-row"><input id="o-' . $k . '" type="range" name="' . $k . '" min="0" max="1" step="0.05" value="' . e($s[$k]) . '" data-out="out-' . $k . '"><output class="range-out" id="out-' . $k . '"></output></div>' . ($help !== '' ? '<small>' . e($help) . '</small>' : '') . '</div>';
?>
<div class="page-head">
  <div>
    <h1>Opções da rádio</h1>
    <p>Os ajustes mais usados estão abertos; os técnicos ficam em "Avançado". O estúdio aberto aplica as mudanças em até 1 minuto.</p>
  </div>
</div>

<form method="post" action="<?= e(url('/opcoes')) ?>" class="form">
  <?= csrf_field() ?>
  <div class="grid2">
    <fieldset class="card">
      <legend>Geral</legend>
      <div class="field"><label for="o-name">Nome da rádio</label><input id="o-name" name="station_name" value="<?= e($station['name']) ?>" maxlength="120" required autocomplete="organization"></div>
      <?= $chk('autostart', 'Começar a tocar sozinho ao abrir o estúdio') ?>
      <?= $chk('open_last_list', 'Abrir a última lista usada') ?>
      <?= $chk('dblclick_plays', 'Duplo clique toca a faixa na hora', 'Desligado: o duplo clique só marca como próxima (mais seguro).') ?>
      <details class="advanced"><summary>Avançado</summary>
        <div class="stack-sm">
          <div class="field"><label for="o-tz">Fuso horário da rádio</label>
            <select id="o-tz" name="timezone"><?php foreach ($timezones as $tz): ?><option <?= $tz === $station['timezone'] ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach ?></select>
            <small>Usado nos eventos e no registro.</small></div>
          <?= $chk('allow_multi_instance', 'Permitir mais de um estúdio aberto', 'Os estúdios extras ficam sem disparar eventos.') ?>
          <?= $chk('show_full_name', 'Mostrar o nome do arquivo em vez de artista e título') ?>
          <div class="field"><label for="o-tags">Formato do título na lista</label><input id="o-tags" name="tags_format" value="<?= e($s['tags_format']) ?>"><small>Use %artist% e %title%.</small></div>
          <?= $chk('confirm_close', 'Pedir confirmação ao fechar o estúdio tocando') ?>
          <?= $chk('log_enabled', 'Gravar o registro de execução') ?>
        </div>
      </details>
    </fieldset>

    <fieldset class="card">
      <legend>Transição entre músicas</legend>
      <?= $num('overlap_s', 'Sobreposição (segundos)', '0.1', '0', '30', 'Quanto a próxima música começa antes do fim da atual.') ?>
      <?= $chk('end_detect', 'Detectar o fim da música pelo volume', 'Começa a próxima quando a atual fica baixa, evitando finais longos e silenciosos.') ?>
      <details class="advanced"><summary>Avançado</summary>
        <div class="stack-sm">
          <?= $num('end_threshold_db', 'Volume que conta como "fim" (dB)', '1', '-80', '0', 'Mais negativo = espera ficar mais baixo. Padrão: -32.') ?>
          <?= $chk('fade_on_overlap', 'Baixar a música anterior durante a sobreposição') ?>
          <?= $chk('fade_on_stop', 'Baixar suavemente ao parar ou pular') ?>
          <?= $num('fade_stop_ms', 'Duração desse fade (milissegundos)', '100', '0', '10000') ?>
          <?= $chk('silence_detect', 'Pular a música se ela ficar muda', 'Útil para arquivos com silêncio no meio.') ?>
          <?= $num('silence_s', 'Tempo de silêncio para pular (segundos)', '1', '2', '120') ?>
          <?= $num('silence_db', 'Volume que conta como silêncio (dB)', '1', '-90', '-20') ?>
        </div>
      </details>
    </fieldset>

    <fieldset class="card">
      <legend>Talk over e vinhetas</legend>
      <?= $range('talkover_level', 'Volume da música no talk over', 'Com 25%, a voz fica clara e a música continua ao fundo.') ?>
      <?= $range('sweeper_duck_level', 'Volume da música durante sweepers') ?>
      <?= $chk('jingle_duck', 'Abaixar a música também nas vinhetas normais') ?>
      <details class="advanced"><summary>Avançado</summary>
        <?= $num('talkover_ms', 'Velocidade da transição do talk over (milissegundos)', '50', '0', '5000') ?>
      </details>
    </fieldset>

    <fieldset class="card">
      <legend>Hora, temperatura e umidade</legend>
      <div class="radio-cards" role="radiogroup" aria-label="De onde vem o clima">
        <label><input type="radio" name="weather_source" value="openmeteo" <?= $s['weather_source'] === 'openmeteo' ? 'checked' : '' ?>><span><b>Automático pela internet</b> <span class="tag on">recomendado</span><small>Serviço Open-Meteo, gratuito. Atualiza a cada 15 minutos.</small></span></label>
        <label><input type="radio" name="weather_source" value="manual" <?= $s['weather_source'] !== 'openmeteo' ? 'checked' : '' ?>><span><b>Eu digito os valores</b><small>Use se não quiser conexão com o serviço de clima.</small></span></label>
      </div>
      <div class="field">
        <span class="label">Local da rádio (para o clima automático)</span>
        <div class="row"><button type="button" class="btn" data-geo>Usar a localização deste computador</button></div>
        <div class="grid3">
          <div class="field"><label for="o-lat">Latitude</label><input id="o-lat" name="weather_lat" value="<?= e($s['weather_lat']) ?>" placeholder="-19.9208" inputmode="decimal" pattern="-?\d{1,2}(\.\d+)?" title="Número como -19.9208"></div>
          <div class="field"><label for="o-lon">Longitude</label><input id="o-lon" name="weather_lon" value="<?= e($s['weather_lon']) ?>" placeholder="-43.9378" inputmode="decimal" pattern="-?\d{1,3}(\.\d+)?" title="Número como -43.9378"></div>
        </div>
      </div>
      <div class="grid3">
        <?= $num('weather_temp', 'Temperatura manual', '1', '-60', '60') ?>
        <?= $num('weather_hum', 'Umidade manual (%)', '1', '0', '100') ?>
      </div>
      <details class="advanced"><summary>Avançado</summary>
        <div class="stack-sm">
          <div class="field"><label for="o-unit">Unidade</label><select id="o-unit" name="tth_units"><option value="C" <?= $s['tth_units'] === 'C' ? 'selected' : '' ?>>Celsius</option><option value="F" <?= $s['tth_units'] === 'F' ? 'selected' : '' ?>>Fahrenheit</option></select></div>
          <?= $num('weather_max_age_min', 'Não anunciar se os dados tiverem mais de (minutos)', '1', '5', '1440', 'Evita anunciar temperatura errada se a internet cair.') ?>
          <?= $chk('time_12h', 'Relógio de 12 horas na tela do estúdio') ?>
        </div>
      </details>
    </fieldset>

    <fieldset class="card full">
      <legend>Transmissão para o servidor de streaming</legend>
      <?php if (!$station['streaming']): ?>
        <p class="notice">Seu plano não inclui o envio para streaming. O som continua saindo pela placa de som do computador.</p>
      <?php else: ?>
        <?= $chk('stream_enabled', 'Mostrar o botão "Transmitir" no estúdio') ?>
        <details class="advanced" <?= $s['stream_enabled'] ? 'open' : '' ?>><summary>Dados do servidor</summary>
          <div class="stack-sm">
            <p class="muted small">Funciona com <b>AzuraCast (Web DJ)</b> e <b>Liquidsoap</b> (<code>input.harbor</code>). Para Icecast/Shoutcast sem Liquidsoap, use um programa como o BUTT no computador.</p>
            <div class="field"><label for="o-surl">Endereço</label><input id="o-surl" name="stream_url" type="url" value="<?= e($s['stream_url']) ?>" placeholder="wss://radio.seudominio.com/webdj/minha_radio" pattern="wss?://.+" title="Começa com wss://"></div>
            <div class="grid3">
              <div class="field"><label for="o-suser">Usuário</label><input id="o-suser" name="stream_user" value="<?= e($s['stream_user']) ?>" autocomplete="off"></div>
              <div class="field"><label for="o-spass">Senha</label><div class="pw"><input id="o-spass" type="password" name="stream_pass" value="" placeholder="<?= $s['stream_pass'] !== '' ? 'Mantida (deixe vazio)' : '' ?>" autocomplete="new-password"><button type="button" class="btn btn-ghost btn-small" data-pw-toggle aria-pressed="false">Mostrar</button></div></div>
              <?= $num('stream_bitrate', 'Qualidade (kbps)', '16', '32', '320', '128 kbps atende a maioria.') ?>
            </div>
          </div>
        </details>
      <?php endif ?>
    </fieldset>
  </div>

  <div class="actions sticky card" style="position:sticky;bottom:12px">
    <span class="muted small">As mudanças valem para todos os computadores da rádio.</span>
    <span class="spacer"></span>
    <button class="btn btn-primary" data-busy="Salvando…">Salvar opções</button>
  </div>
</form>
