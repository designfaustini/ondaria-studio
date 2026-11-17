<section class="hero">
  <div>
    <p class="eyebrow">Automação de rádio no navegador</p>
    <h1>Sua rádio no ar, sem instalar nada.</h1>
    <p class="lead">Lista com crossfade, eventos agendados, vinhetas no teclado e hora certa. Tudo salvo na nuvem e acessível de qualquer computador.</p>
    <div class="actions">
      <a class="btn btn-primary btn-large" href="<?= e(url('/cadastro')) ?>">Criar minha rádio grátis</a>
      <a class="btn btn-ghost btn-large" href="#como-funciona">Como funciona</a>
    </div>
    <p class="hint">Grátis para sempre no plano básico. Sem cartão de crédito.</p>
  </div>
  <div class="hero-console" aria-hidden="true">
    <div class="hc-onair"><span class="dot"></span> NO AR <b>Manhã de Sol</b><i>03:12</i></div>
    <div class="hc-meter"><span style="width:72%"></span></div>
    <div class="hc-meter"><span style="width:64%"></span></div>
    <ol class="hc-list"><li class="on">Banda Teste — Manhã de Sol</li><li class="next">Hora certa</li><li>Vinheta Prefixo</li><li>Coral Digital — Onda Azul</li></ol>
    <div class="hc-pads"><?php for ($i = 1; $i <= 9; $i++): ?><span><?= $i ?></span><?php endfor ?></div>
  </div>
</section>

<section id="como-funciona" aria-labelledby="como-t">
  <h2 class="section-title" id="como-t">Em 3 passos</h2>
  <ol class="steps-how">
    <li><h3>Envie suas músicas</h3><p>Arraste os arquivos. Título, artista e duração são lidos sozinhos.</p></li>
    <li><h3>Monte a lista e a grade</h3><p>Arraste para a lista e agende hora certa, comerciais e programas.</p></li>
    <li><h3>Aperte tocar</h3><p>O estúdio cuida das transições e dispara os eventos na hora certa.</p></li>
  </ol>
</section>

<section aria-labelledby="rec-t">
  <h2 class="section-title" id="rec-t">O que vem junto</h2>
  <div class="features">
    <article><h3>Lista de execução</h3><p>Modos normal, repetir, embaralhar, aleatório e manual, com "parar após a atual".</p></article>
    <article><h3>Crossfade</h3><p>Sobreposição por tempo ou pela detecção do fim da música, ajustável por arquivo.</p></article>
    <article><h3>Eventos</h3><p>Diários, de hora em hora ou em horas escolhidas, com espera e prioridade.</p></article>
    <article><h3>Hora, temperatura e umidade</h3><p>Locuções automáticas com o clima da sua cidade.</p></article>
    <article><h3>Vinhetas e sweepers</h3><p>90 vinhetas nas teclas 1–9 e locuções por cima das músicas.</p></article>
    <article><h3>Relatórios</h3><p>Registro do que tocou, exportado em TXT ou CSV para anunciantes.</p></article>
  </div>
</section>

<section aria-labelledby="planos-t">
  <h2 class="section-title" id="planos-t">Planos</h2>
  <div class="plan-grid">
    <?php foreach ($plans as $i => $p): ?>
      <article class="plan <?= $i === 1 ? 'featured' : '' ?>">
        <h3><?= e($p['name']) ?><?= $i === 1 ? ' <span class="tag on">mais escolhido</span>' : '' ?></h3>
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
  <p class="hint">Toda rádio começa no plano Grátis. A troca de plano é feita com o suporte.</p>
</section>

<section class="center-card wide" style="text-align:center">
  <h2>Pronto para colocar a rádio no ar?</h2>
  <p class="muted">Leva menos de 1 minuto.</p>
  <a class="btn btn-primary btn-large btn-block" href="<?= e(url('/cadastro')) ?>">Criar minha rádio grátis</a>
</section>
