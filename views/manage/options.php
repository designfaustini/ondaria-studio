<?php
$s = $settings;
$chk = fn (string $k) => '<input type="checkbox" name="' . $k . '" value="1"' . ($s[$k] ? ' checked' : '') . '>';
$num = fn (string $k, string $step = '1', string $min = '', string $max = '') => '<input type="number" name="' . $k . '" value="' . e($s[$k]) . '" step="' . $step . '"' . ($min !== '' ? ' min="' . $min . '"' : '') . ($max !== '' ? ' max="' . $max . '"' : '') . '>';
?>
<div class="page-head"><h1>Opções</h1></div>
<form method="post" action="<?= e(url('/opcoes')) ?>" class="form grid2">
  <?= csrf_field() ?>
  <fieldset class="card">
    <legend>Geral</legend>
    <label>Nome da rádio <input name="station_name" value="<?= e($station['name']) ?>" maxlength="120"></label>
    <label>Fuso horário (eventos e registro)
      <select name="timezone"><?php foreach ($timezones as $tz): ?><option <?= $tz === $station['timezone'] ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach ?></select>
    </label>
    <label class="check"><?= $chk('autostart') ?> Começar a tocar ao abrir o estúdio</label>
    <label class="check"><?= $chk('open_last_list') ?> Abrir a última lista usada</label>
    <label class="check"><?= $chk('dblclick_plays') ?> Duplo clique toca a faixa na hora (senão, marca como próxima)</label>
    <label class="check"><?= $chk('allow_multi_instance') ?> Permitir mais de um estúdio aberto (os extras ficam sem eventos)</label>
    <label class="check"><?= $chk('show_full_name') ?> Mostrar nome do arquivo em vez das tags</label>
    <label>Formato das tags <input name="tags_format" value="<?= e($s['tags_format']) ?>"><small class="muted">Use %artist% e %title%</small></label>
    <label class="check"><?= $chk('confirm_close') ?> Pedir confirmação ao fechar o estúdio</label>
    <label class="check"><?= $chk('log_enabled') ?> Gravar registro de execução</label>
  </fieldset>

  <fieldset class="card">
    <legend>Fundido (crossfade)</legend>
    <label>Sobreposição entre faixas (segundos) <?= $num('overlap_s', '0.1', '0', '30') ?></label>
    <label class="check"><?= $chk('end_detect') ?> Detector de fim de música (inicia a próxima quando o volume cai)</label>
    <label>Limiar do detector (dB) <?= $num('end_threshold_db', '1', '-80', '0') ?></label>
    <label class="check"><?= $chk('fade_on_overlap') ?> Baixar a faixa anterior durante a sobreposição</label>
    <label class="check"><?= $chk('fade_on_stop') ?> Fade ao parar ou pular</label>
    <label>Duração do fade ao parar (ms) <?= $num('fade_stop_ms', '100', '0', '10000') ?></label>
  </fieldset>

  <fieldset class="card">
    <legend>Talk over, sweepers e silêncio</legend>
    <label>Volume da música no talk over (0 a 1) <?= $num('talkover_level', '0.05', '0', '1') ?></label>
    <label>Tempo da transição do talk over (ms) <?= $num('talkover_ms', '50', '0', '5000') ?></label>
    <label>Volume da música durante sweepers (0 a 1) <?= $num('sweeper_duck_level', '0.05', '0', '1') ?></label>
    <label class="check"><?= $chk('jingle_duck') ?> Abaixar a música também nas vinhetas normais</label>
    <label class="check"><?= $chk('silence_detect') ?> Detector de silêncio (pula a faixa se ficar muda)</label>
    <label>Tempo de silêncio (s) <?= $num('silence_s', '1', '2', '120') ?></label>
    <label>Limiar de silêncio (dB) <?= $num('silence_db', '1', '-90', '-20') ?></label>
  </fieldset>

  <fieldset class="card">
    <legend>Hora, temperatura e umidade</legend>
    <label class="check"><?= $chk('time_12h') ?> Relógio de 12 horas na tela</label>
    <label>Unidade <select name="tth_units"><option value="C" <?= $s['tth_units'] === 'C' ? 'selected' : '' ?>>Celsius</option><option value="F" <?= $s['tth_units'] === 'F' ? 'selected' : '' ?>>Fahrenheit</option></select></label>
    <label>Fonte do clima <select name="weather_source"><option value="manual" <?= $s['weather_source'] === 'manual' ? 'selected' : '' ?>>Manual</option><option value="openmeteo" <?= $s['weather_source'] === 'openmeteo' ? 'selected' : '' ?>>Automática (Open-Meteo)</option></select></label>
    <div class="row gap"><label>Temperatura manual <?= $num('weather_temp', '1', '-60', '60') ?></label><label>Umidade manual (%) <?= $num('weather_hum', '1', '0', '100') ?></label></div>
    <div class="row gap"><label>Latitude <input name="weather_lat" value="<?= e($s['weather_lat']) ?>" placeholder="-19.92"></label><label>Longitude <input name="weather_lon" value="<?= e($s['weather_lon']) ?>" placeholder="-43.94"></label></div>
    <label>Não anunciar se os dados tiverem mais de (min) <?= $num('weather_max_age_min', '1', '5', '1440') ?></label>
  </fieldset>

  <fieldset class="card full">
    <legend>Transmissão para servidor de streaming</legend>
    <?php if (!$station['streaming']): ?><p class="flash flash-info">Seu plano não inclui envio para streaming. A saída de áudio pela placa de som continua disponível.</p><?php endif ?>
    <p class="muted small">Envia o áudio do estúdio, codificado no navegador (Opus/WebM), para um servidor compatível com o protocolo <i>webcast</i>: <b>AzuraCast (Web DJ)</b> ou <b>Liquidsoap</b> com <code>input.harbor</code>. Exemplo: <code>wss://radio.seudominio.com/webdj/minha_radio</code>. Servidores só Icecast/Shoutcast, sem Liquidsoap, precisam de um encoder externo (ex.: BUTT) capturando a saída de áudio do computador.</p>
    <label class="check"><?= $chk('stream_enabled') ?> Ativar botão "Transmitir" no estúdio</label>
    <label>Endereço (wss://) <input name="stream_url" value="<?= e($s['stream_url']) ?>" placeholder="wss://servidor/webdj/radio"></label>
    <div class="row gap">
      <label>Usuário <input name="stream_user" value="<?= e($s['stream_user']) ?>" autocomplete="off"></label>
      <label>Senha <input type="password" name="stream_pass" value="" placeholder="<?= $s['stream_pass'] !== '' ? '(mantida)' : '' ?>" autocomplete="new-password"></label>
      <label>Taxa (kbps) <?= $num('stream_bitrate', '16', '32', '320') ?></label>
    </div>
  </fieldset>

  <div class="full"><button class="btn btn-primary">Salvar opções</button></div>
</form>
