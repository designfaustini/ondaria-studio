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
    <div class="station"><img src="<?= e(asset('img/icon.svg')) ?>" alt="" width="22" height="22"> <b id="stname"></b> <span id="leader" class="pill"></span></div>
    <nav class="menu">
      <?= $link('/biblioteca', 'Biblioteca') ?><?= $link('/listas', 'Listas') ?><?= $link('/eventos', 'Eventos') ?><?= $link('/vinhetas', 'Vinhetas') ?><?= $link('/sweepers', 'Sweepers') ?><?= $link('/registro', 'Registro') ?>
      <?php if ($manager): ?><?= $link('/opcoes', 'Opções') ?><?= $link('/usuarios', 'Usuários') ?><?php endif ?>
    </nav>
    <div class="clock"><span id="date"></span> <b id="clock">--:--:--</b> <span id="weather"></span></div>
    <button id="btnStream" class="pill" hidden>Transmitir</button>
    <button id="btnDevices" title="Placas de som e entradas">Dispositivos</button>
    <form method="post" action="<?= e(url('/sair')) ?>" id="logoutform"><?= csrf_field() ?><button title="Sair">Sair</button></form>
  </header>

  <div class="toolbar" role="toolbar" aria-label="Lista">
    <button data-act="new" title="Nova lista">Nova</button>
    <button data-act="open" title="Abrir lista">Abrir</button>
    <button data-act="save" title="Salvar (Ctrl+S)">Salvar</button>
    <button data-act="saveas">Salvar como</button>
    <span class="sep"></span>
    <button data-act="remove" title="Remover selecionados (Delete)">Remover</button>
    <button data-act="copy" title="Copiar (Ctrl+C)">Copiar</button>
    <button data-act="paste" title="Colar (Ctrl+V)">Colar</button>
    <button data-act="find" title="Buscar na lista (Ctrl+F)">Buscar</button>
    <span class="sep"></span>
    <label class="mode">Modo
      <select id="mode">
        <option value="normal">Normal</option><option value="repeat">Repetir</option><option value="shuffle">Embaralhar</option><option value="random">Aleatório</option><option value="manual">Manual</option>
      </select>
    </label>
    <button data-toggle="autodelete" title="Apagar cada faixa da lista depois de tocar">Apagar ao tocar</button>
    <button data-toggle="loop" title="Repetir a faixa atual">Loop</button>
    <button data-toggle="sweepers" class="on" title="Tocar sweepers sobre as músicas">Sweepers</button>
    <span class="sep"></span>
    <div class="dropdown">
      <button data-act="insertmenu">Inserir ▾</button>
      <div class="dropmenu" id="insertmenu" hidden>
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
        <hr>
        <button data-act="shuffle">Embaralhar a lista</button>
        <button data-act="sellength">Duração da seleção</button>
        <button data-act="updlength">Atualizar duração da seleção</button>
      </div>
    </div>
    <span class="sep"></span>
    <span id="auxbtns"></span>
  </div>

  <main class="grid">
    <section class="list-pane">
      <div class="list-head"><b id="listname">Lista</b><span id="dirty" hidden>•</span><input id="findbox" type="search" placeholder="Buscar na lista…" hidden></div>
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
            <button id="btnTime" title="Hora certa">🕑</button><button id="btnTemp" title="Temperatura">🌡</button><button id="btnHum" title="Umidade">💧</button>
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
          <button id="evPlay" title="Tocar eventos pendentes">▶</button>
          <button id="evDiscard" title="Descartar pendentes">✕</button>
          <button id="evToggle" class="on" title="Ativar/desativar eventos">Ativos</button>
          <a href="<?= e(url('/eventos')) ?>" target="_blank" rel="noopener" title="Editar eventos">⏰</a>
        </div>
        <ul id="evlist" class="evlist"></ul>
      </section>

      <section class="library">
        <div class="pane-head"><b>Biblioteca</b><a href="<?= e(url('/biblioteca')) ?>" target="_blank" rel="noopener">enviar arquivos</a></div>
        <div class="lib-filter">
          <select id="libfolder"></select>
          <input id="libq" type="search" placeholder="Buscar (aceita *)…">
        </div>
        <ul id="liblist" class="liblist"></ul>
        <p class="hint">Arraste para a lista, para uma vinheta ou para um player auxiliar. Duplo clique adiciona após a seleção.</p>
      </section>
    </aside>

    <section class="aux-pane" id="auxpane" hidden></section>
  </main>

  <footer class="bottom">
    <div class="transport">
      <button id="btnPlay" class="play" title="Tocar">▶</button>
      <button id="btnPause" title="Pausar">❚❚</button>
      <button id="btnStop" title="Parar">■</button>
      <button id="btnNext" title="Próxima (para a atual e toca a marcada em verde)">⏭</button>
      <button id="btnRew" title="Voltar 30 s">⏪</button>
      <button id="btnFwd" title="Avançar 30 s">⏩</button>
      <button id="btnStopAfter" title="Parar depois da faixa atual">Parar após</button>
      <button id="btnTalk" title="Talk over: abaixa a música e abre o microfone">Talk over</button>
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
