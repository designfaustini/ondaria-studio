<section class="hero">
  <div>
    <p class="eyebrow">Automação de rádio 100% no navegador</p>
    <h1>Sua rádio no ar, sem instalar nada.</h1>
    <p class="lead">Lista de execução com crossfade, eventos agendados, 90 vinhetas no teclado, hora certa, temperatura e umidade, sweepers, players auxiliares e relatório de transmissão para anunciantes. Tudo guardado na nuvem e acessível de qualquer computador.</p>
    <p><a class="btn btn-primary btn-large" href="<?= e(url('/cadastro')) ?>">Criar minha rádio grátis</a> <a class="btn btn-large" href="<?= e(url('/entrar')) ?>">Já tenho conta</a></p>
  </div>
  <div class="hero-console" aria-hidden="true">
    <div class="hc-onair"><span class="dot"></span> NO AR <b>Manhã de Sol</b><i>03:12</i></div>
    <div class="hc-meter"><span style="width:72%"></span></div>
    <div class="hc-meter"><span style="width:64%"></span></div>
    <ol class="hc-list"><li class="on">Banda Teste — Manhã de Sol</li><li class="next">Hora certa</li><li>Vinheta Prefixo</li><li>Coral Digital — Onda Azul</li></ol>
    <div class="hc-pads"><?php for ($i = 1; $i <= 9; $i++): ?><span><?= $i ?></span><?php endfor ?></div>
  </div>
</section>

<section class="features">
  <article><h3>Lista de execução</h3><p>Modos normal, repetir, embaralhar, aleatório e manual. Marcar próxima, parar após a atual, apagar ao tocar e repetir faixa.</p></article>
  <article><h3>Crossfade inteligente</h3><p>Sobreposição por tempo ou por detecção de fim de música, fade no stop e ajuste por arquivo (<code>Nome~2.mp3</code>).</p></article>
  <article><h3>Eventos e grades</h3><p>Diários, de hora em hora ou em horas escolhidas, com espera, prioridade e tempo máximo de atraso.</p></article>
  <article><h3>Hora, temperatura e umidade</h3><p>Locuções a partir dos arquivos HRS/MIN/TMP/HUM, com clima automático pelo Open-Meteo.</p></article>
  <article><h3>Vinhetas e sweepers</h3><p>10 páginas de 9 vinhetas nas teclas 1–9, modos normal, loop e sweeper (abaixa a música).</p></article>
  <article><h3>Transmissão</h3><p>Saída na placa de som escolhida e envio direto para AzuraCast/Liquidsoap. Página "no ar" em JSON para seu site.</p></article>
</section>

<section class="plans">
  <h2>Planos</h2>
  <div class="plan-grid">
    <?php foreach ($plans as $p): ?>
      <article class="plan">
        <h3><?= e($p['name']) ?></h3>
        <p class="price"><?= (int) $p['price_cents'] === 0 ? 'Grátis' : 'R$ ' . number_format($p['price_cents'] / 100, 2, ',', '.') . '<small>/mês</small>' ?></p>
        <ul>
          <li><?= e(format_bytes($p['storage_mb'] * 1024 * 1024)) ?> de áudio</li>
          <li><?= (int) $p['max_users'] ?> usuário(s)</li>
          <li><?= (int) $p['max_aux_players'] ?> player(s) auxiliar(es)</li>
          <li><?= $p['streaming'] ? 'Envio para servidor de streaming' : 'Sem envio para streaming' ?></li>
        </ul>
      </article>
    <?php endforeach ?>
  </div>
</section>
