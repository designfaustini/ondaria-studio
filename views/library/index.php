<?php
$tree = [];
foreach ($folders as $f) {
    $tree[(int) $f['parent_id']][] = $f;
}
$renderTree = function (int $parent, int $depth) use (&$renderTree, $tree, $folder): void {
    foreach ($tree[$parent] ?? [] as $f) {
        $active = $folder && (int) $folder['id'] === (int) $f['id'];
        echo '<li><a style="padding-left:' . (8 + $depth * 14) . 'px" class="' . ($active ? 'active' : '') . '" href="' . e(url('/biblioteca?pasta=' . $f['id'])) . '">'
            . e($f['name']) . ($f['kind'] !== 'normal' ? ' <span class="tag">' . e($f['kind'] === 'time' ? 'hora' : ($f['kind'] === 'temperature' ? 'temp' : 'umid')) . '</span>' : '')
            . ' <small>' . (int) $f['files'] . '</small></a></li>';
        $renderTree((int) $f['id'], $depth + 1);
    }
};
$pct = $quota > 0 ? min(100, $used / $quota * 100) : 0;
?>
<div class="page-head">
  <h1>Biblioteca</h1>
  <div class="quota" title="<?= e(format_bytes($used)) ?> de <?= e(format_bytes($quota)) ?>">
    <span>Espaço: <?= e(format_bytes($used)) ?> de <?= e(format_bytes($quota)) ?></span>
    <div class="bar"><i style="width:<?= number_format($pct, 1, '.', '') ?>%"></i></div>
  </div>
</div>

<div class="lib">
  <aside class="lib-tree card">
    <ul class="tree"><?php $renderTree(0, 0) ?></ul>
    <details>
      <summary>Nova pasta</summary>
      <form method="post" action="<?= e(url('/biblioteca/pastas')) ?>" class="form compact">
        <?= csrf_field() ?>
        <input name="name" placeholder="Nome da pasta" required maxlength="120">
        <select name="parent_id"><option value="">(raiz)</option><?php foreach ($folders as $f): ?><option value="<?= (int) $f['id'] ?>" <?= $folder && $folder['id'] == $f['id'] ? 'selected' : '' ?>>dentro de: <?= e($f['name']) ?></option><?php endforeach ?></select>
        <select name="kind"><?php foreach ($kinds as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach ?></select>
        <button class="btn btn-small">Criar</button>
      </form>
    </details>
  </aside>

  <section class="lib-files">
    <?php if ($folder): ?>
      <div class="card">
        <div class="row between">
          <h2><?= e($folder['name']) ?> <?php if ($folder['kind'] !== 'normal'): ?><span class="tag"><?= e($kinds[$folder['kind']]) ?></span><?php endif ?></h2>
          <?php if (\App\Core\Auth::isManager()): ?>
          <details class="inline">
            <summary class="btn btn-small">Editar pasta</summary>
            <form method="post" action="<?= e(url('/biblioteca/pastas/' . $folder['id'])) ?>" class="form compact">
              <?= csrf_field() ?>
              <input name="name" value="<?= e($folder['name']) ?>" required>
              <select name="kind"><?php foreach ($kinds as $k => $label): ?><option value="<?= e($k) ?>" <?= $folder['kind'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select>
              <button class="btn btn-small">Salvar</button>
            </form>
            <form method="post" action="<?= e(url('/biblioteca/pastas/' . $folder['id'] . '/excluir')) ?>" data-confirm="Excluir a pasta, as subpastas e TODOS os arquivos dentro delas? Não dá para desfazer.">
              <?= csrf_field() ?><button class="btn btn-small btn-danger">Excluir pasta</button>
            </form>
          </details>
          <?php endif ?>
        </div>

        <div class="dropzone" id="dropzone" data-folder="<?= (int) $folder['id'] ?>" data-accept="<?= e(implode(',', array_map(fn ($x) => '.' . $x, $extensions))) ?>">
          <p><b>Arraste arquivos de áudio para cá</b> ou <label class="link">escolha no computador<input type="file" id="fileinput" multiple accept="<?= e(implode(',', array_map(fn ($x) => '.' . $x, $extensions))) ?>,audio/*" hidden></label></p>
          <p class="muted small">MP3, OGG, WAV, FLAC, M4A/AAC e WEBM. WMA não toca em navegadores — converta antes. Use "Nome~2.mp3" para sobreposição de 2 s.</p>
          <ul id="uploads" class="uploads"></ul>
        </div>
        <?php if ($folder['kind'] === 'time'): ?><p class="hint">Nomes esperados: <code>HRS00</code>…<code>HRS23</code> (hora), <code>HRS14_O</code> (hora cheia, "em ponto") e <code>MIN00</code>…<code>MIN59</code>.</p><?php endif ?>
        <?php if ($folder['kind'] === 'temperature'): ?><p class="hint">Nomes esperados: <code>TMP025</code> = 25 graus, <code>TMPN003</code> = 3 graus negativos.</p><?php endif ?>
        <?php if ($folder['kind'] === 'humidity'): ?><p class="hint">Nomes esperados: <code>HUM000</code>…<code>HUM100</code>.</p><?php endif ?>

        <form method="post" action="<?= e(url('/biblioteca/midias/excluir')) ?>" id="bulkform" data-confirm="Excluir os arquivos selecionados?">
          <?= csrf_field() ?>
          <table class="table">
            <thead><tr><th><input type="checkbox" data-checkall></th><th>Arquivo</th><th>Título / artista</th><th>Duração</th><th>Tamanho</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($media as $m): ?>
              <tr>
                <td><input type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>"></td>
                <td><button type="button" class="link play-preview" data-src="<?= e(url('/media/' . $m['id'])) ?>" title="Ouvir">▶</button> <?= e($m['filename']) ?><?= $m['overlap_s'] !== null ? ' <span class="tag">~' . e($m['overlap_s'] + 0) . 's</span>' : '' ?></td>
                <td><?= e($m['title'] ?? '') ?><br><small class="muted"><?= e($m['artist'] ?? '') ?></small></td>
                <td><?= e(format_duration((int) $m['duration_ms'])) ?></td>
                <td><?= e(format_bytes((int) $m['size_bytes'])) ?></td>
                <td><button type="button" class="btn btn-small" data-edit='<?= e(json_encode(['id' => $m['id'], 'filename' => $m['filename'], 'title' => $m['title'], 'artist' => $m['artist'], 'overlap_s' => $m['overlap_s'], 'folder_id' => $m['folder_id']])) ?>'>Editar</button></td>
              </tr>
            <?php endforeach ?>
            <?php if (!$media): ?><tr><td colspan="6" class="muted">Pasta vazia.</td></tr><?php endif ?>
            </tbody>
          </table>
          <?php if ($media && \App\Core\Auth::isManager()): ?><button class="btn btn-small btn-danger">Excluir selecionados</button><?php endif ?>
        </form>
      </div>
    <?php endif ?>
  </section>
</div>

<dialog id="editdlg">
  <form method="post" class="form" id="editform">
    <?= csrf_field() ?>
    <h3>Editar arquivo</h3>
    <label>Nome do arquivo <input name="filename" required></label>
    <label>Título <input name="title"></label>
    <label>Artista <input name="artist"></label>
    <label>Sobreposição própria (segundos, vazio = padrão) <input name="overlap_s" inputmode="decimal"></label>
    <label>Pasta <select name="folder_id"><?php foreach ($folders as $f): ?><option value="<?= (int) $f['id'] ?>"><?= e($f['name']) ?></option><?php endforeach ?></select></label>
    <div class="row"><button class="btn btn-primary">Salvar</button><button type="button" class="btn" data-close>Cancelar</button></div>
  </form>
</dialog>
<audio id="preview" preload="none"></audio>
