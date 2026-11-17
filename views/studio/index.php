<?php $manager = \App\Core\Auth::isManager(); $link = fn (string $p, string $l) => '<a href="' . e(url($p)) . '" target="_blank" rel="noopener">' . e($l) . '</a>'; ?>
<div id="boot" class="boot">
  <div>
    <img src="<?= e(asset('img/icon.svg')) ?>" alt="" width="56" height="56">
    <h1><?= e(station()['name']) ?></h1>
    <p id="bootmsg">Carregando o estúdio…</p>
    <button id="bootbtn" class="big" hidden>Entrar no estúdio</button>
    <p class="small">Os navegadores só liberam o som depois de um clique. Mantenha esta aba aberta enquanto a rádio estiver no ar.</p>
  </div>
</div>

<div id="studio" hidden>
  <header class="top">
    <div class="station"><img src="<?= e(asset('img/icon.svg')) ?>" alt="" width="22" height="22"> <b id="stname"></b> <button type="button" id="leader" class="pill"></button></div>
    <div class="clock"><span id="date"></span> <b id="clock">--:--:--</b> <span id="weather"></span></div>
    <span class="spacer"></span>
    <button id="btnStream" class="pill" hidden>Transmitir</button>
    <div class="dropdown">
      <button data-menu="managemenu" aria-haspopup="true" aria-expanded="false">Gerenciar ▾</button>
      <div class="dropmenu dropmenu-right" id="managemenu" hidden>
        <?= $link('/biblioteca', 'Biblioteca (enviar arquivos)') ?><?= $link('/eventos', 'Eventos') ?><?= $link('/listas', 'Listas') ?><?= $link('/vinhetas', 'Vinhetas') ?><?= $link('/sweepers', 'Sweepers') ?><?= $link('/registro', 'Registro de execução') ?>
        <?php if ($manager): ?><hr><?= $link('/opcoes', 'Opções da rádio') ?><?= $link('/usuarios', 'Usuários') ?><?php endif ?>
        <p class="menu-note">Abre em outra aba: o estúdio continua tocando.</p>
      </div>
    </div>
    <div class="dropdown">
      <button data-menu="usermenu" aria-haspopup="true" aria-expanded="false" title="<?= e(user()['name']) ?>"><span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) user()['name'], 0, 1))) ?></span> ▾</button>
      <div class="dropmenu dropmenu-right" id="usermenu" hidden>
        <button id="btnDevices">Placas de som e microfone…</button>
        <?= $link('/conta', 'Minha conta') ?>
        <hr>
        <form method="post" action="<?= e(url('/sair')) ?>" id="logoutform"><?= csrf_field() ?><button>Sair</button></form>
      </div>
    </div>
  </header>

  <div class="toolbar" role="toolbar" aria-label="Lista">
    <button data-act="open" title="Abrir uma lista salva">Abrir</button>
    <button data-act="save" id="btnSave" title="Salvar a lista (Ctrl+S)">Salvar</button>
    <span class="sep"></span>
    <div class="dropdown">
      <button data-menu="insertmenu" aria-haspopup="true" aria-expanded="false">+ Inserir ▾</button>
      <div class="dropmenu" id="insertmenu" hidden>
        <p class="menu-note">Músicas: arraste da biblioteca à direita.</p>
        <button data-insert="time">Hora certa</button>
        <button data-insert="temperature">Temperatura</button>
        <button data-insert="humidity">Umidade</button>
        <button data-insert="stop">Comando parar</button>
        <button data-insert="play">Comando tocar</button>
        <button data-insert="pause">Pausa…</button>
        <button data-insert="random">Faixa aleatória de uma pasta…</button>
        <button data-insert="stream">Rádio online…</button>
        <button data-insert="satellite">Satélite por tempo…</button>
        <button data-insert="satcon">Conectar satélite</button>
        <button data-insert="satdis">Desconectar satélite</button>
        <button data-insert="postit">Lembrete (post-it)…</button>
        <button data-insert="playlist">Carregar lista…</button>
        <button data-insert="rotation">Rotação…</button>
        <button data-insert="sequence">Sequência…</button>
        <button data-insert="events">Carregar grade de eventos…</button>
      </div>
    </div>
    <span class="sep"></span>
    <label class="mode">Modo
      <select id="mode" title="Como a lista avança">
        <option value="normal">Normal (para no fim)</option><option value="repeat">Repetir a lista</option><option value="shuffle">Embaralhar e repetir</option><option value="random">Aleatório</option><option value="manual">Manual (uma por vez)</option>
      </select>
    </label>
    <div class="dropdown">
      <button data-menu="playmenu" aria-haspopup="true" aria-expanded="false">Reprodução <span id="playbadges"></span> ▾</button>
      <div class="dropmenu" id="playmenu" hidden>
        <button data-toggle="sweepers" class="on" role="menuitemcheckbox" aria-checked="true">Tocar sweepers por cima das músicas</button>
        <button data-toggle="loop" role="menuitemcheckbox" aria-checked="false">Repetir a faixa atual (loop)</button>
        <button data-toggle="autodelete" role="menuitemcheckbox" aria-checked="false">Tirar da lista cada faixa que tocar</button>
      </div>
    </div>
    <div class="dropdown">
      <button data-menu="moremenu" aria-haspopup="true" aria-expanded="false">Mais ▾</button>
      <div class="dropmenu" id="moremenu" hidden>
        <button data-act="new">Nova lista vazia</button>
        <button data-act="saveas">Salvar como…</button>
        <button data-act="find">Buscar na lista <kbd>Ctrl F</kbd></button>
        <hr>
        <button data-act="shuffle">Embaralhar a lista agora</button>
        <button data-act="updlength">Atualizar duração da seleção</button>
      </div>
    </div>
    <span class="spacer"></span>
    <div class="dropdown">
      <button data-menu="auxmenu" aria-haspopup="true" aria-expanded="false">Players extras ▾</button>
      <div class="dropmenu dropmenu-right" id="auxmenu" hidden><span id="auxbtns"></span></div>
    </div>
  </div>

  <main class="grid">
    <section class="list-pane">
      <div class="list-head">
        <b id="listname">Lista</b>
        <button id="dirty" class="dirty" hidden title="Salvar agora (Ctrl+S)">● Alterações não salvas — salvar</button>
        <span class="spacer"></span>
        <div class="selbar" id="selbar" hidden>
          <span id="selcount"></span>
          <button data-sel="next">Marcar como próxima</button>
          <button data-sel="copy">Copiar</button>
          <button data-sel="paste">Colar abaixo</button>
          <button data-sel="remove" class="danger">Remover</button>
        </div>
        <div class="search" id="findwrap" hidden><input id="findbox" type="search" placeholder="Buscar na lista" aria-label="Buscar na lista"></div>
      </div>
      <div class="list-scroll" id="listwrap"><table class="pl" id="playlist"><thead><tr><th class="n">#</th><th>Título</th><th class="d">Duração</th></tr></thead><tbody></tbody></table></div>
      <div class="list-foot"><span id="listinfo"></span></div>
    </section>

    <aside class="side">
      <section class="onair">
        <div class="now"><span class="lbl">No ar</span><b id="nowtitle">—</b></div>
        <div class="times">
          <div><span class="lbl">Restante</span><b id="remain">--:--</b></div>
          <div><span class="lbl">Termina</span><b id="endat">--:--:--</b></div>
          <div class="tth">
            <button id="btnTime" title="Anunciar a hora agora" aria-label="Anunciar a hora">🕑</button><button id="btnTemp" title="Anunciar a temperatura agora" aria-label="Anunciar a temperatura">🌡</button><button id="btnHum" title="Anunciar a umidade agora" aria-label="Anunciar a umidade">💧</button>
          </div>
        </div>
        <div class="progress"><i id="progress"></i></div>
        <div class="meters"><div><span>E</span><i id="vuL"></i></div><div><span>D</span><i id="vuR"></i></div></div>
        <label class="master">Volume <input type="range" id="master" min="0" max="1.5" step="0.01" value="1"></label>
        <div class="next"><span class="lbl">Próxima</span><b id="nexttitle">—</b></div>
      </section>

      <section class="events">
        <div class="pane-head">
          <b>Próximos eventos</b>
          <button id="evPlay" title="Tocar agora os eventos que estão esperando" aria-label="Tocar eventos pendentes" hidden>▶ Tocar pendentes</button>
          <button id="evDiscard" title="Descartar os eventos que estão esperando" aria-label="Descartar eventos pendentes" hidden>Descartar</button>
          <button id="evToggle" class="on" role="switch" aria-checked="true" title="Ligar ou desligar os eventos">Ligados</button>
          <a href="<?= e(url('/eventos')) ?>" target="_blank" rel="noopener" title="Editar eventos (abre em outra aba)">Editar</a>
        </div>
        <ul id="evlist" class="evlist"></ul>
      </section>

      <section class="library">
        <div class="pane-head"><b>Biblioteca</b><a href="<?= e(url('/biblioteca')) ?>" target="_blank" rel="noopener">enviar arquivos</a></div>
        <div class="lib-filter">
          <label class="sr-only" for="libfolder">Pasta</label><select id="libfolder"></select>
          <div class="search"><label class="sr-only" for="libq">Buscar música</label><input id="libq" type="search" placeholder="Buscar música"></div>
        </div>
        <ul id="liblist" class="liblist"></ul>
        <p class="hint">Arraste para a lista, para uma vinheta ou para um player auxiliar. Duplo clique adiciona após a seleção.</p>
      </section>
    </aside>

    <section class="aux-pane" id="auxpane" hidden></section>
  </main>

  <footer class="bottom">
    <div class="transport">
      <button id="btnPlay" class="play" title="Tocar (a faixa em verde)" aria-label="Tocar">▶ Tocar</button>
      <button id="btnNext" title="Pular: para a atual e toca a marcada em verde" aria-label="Pular para a próxima">⏭</button>
      <button id="btnPause" title="Pausar" aria-label="Pausar" aria-pressed="false">❚❚</button>
      <button id="btnStop" title="Parar" aria-label="Parar">■</button>
      <button id="btnRew" title="Voltar 30 segundos" aria-label="Voltar 30 segundos">−30s</button>
      <button id="btnFwd" title="Avançar 30 segundos" aria-label="Avançar 30 segundos">+30s</button>
      <button id="btnStopAfter" title="Parar quando a faixa atual acabar" aria-pressed="false">Parar após esta</button>
      <button id="btnTalk" title="Abaixa a música e abre o microfone" aria-pressed="false">🎙 Talk over</button>
    </div>
    <div class="jingles">
      <select id="jpage" title="Página de vinhetas"></select>
      <div id="jbuttons" class="jbuttons"></div>
    </div>
  </footer>
</div>

<dialog id="dlg"><form method="dialog" id="dlgform"><h3 id="dlgtitle"></h3><div id="dlgbody"></div><menu><button value="cancel" formnovalidate>Cancelar</button><button value="ok" id="dlgok" class="primary">OK</button></menu></form></dialog>
<div id="postits"></div>
<div id="toasts" aria-live="polite"></div>
<ul id="ctxmenu" class="ctxmenu" hidden></ul>
