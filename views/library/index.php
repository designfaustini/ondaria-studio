<?php
// Pastas da demonstração (compartilhadas) não podem ser editadas, nem recebem envios.
$manager = \App\Core\Auth::isManager();
$canEdit = $manager && !$readonly;
$kindTag = ['time' => 'hora', 'temperature' => 'temp.', 'humidity' => 'umid.'];
$renderTree = function (array $list) use ($folder, $kindTag): void {
    $tree = [];
    foreach ($list as $f) {
        $tree[(int) $f['parent_id']][] = $f;
    }
    $walk = function (int $parent, int $depth) use (&$walk, $tree, $folder, $kindTag): void {
        foreach ($tree[$parent] ?? [] as $f) {
            $active = $folder && (int) $folder['id'] === (int) $f['id'];
            echo '<li><a style="padding-left:' . (10 + $depth * 16) . 'px" ' . ($active ? 'aria-current="page"' : '') . ' href="' . e(url('/biblioteca?pasta=' . $f['id'])) . '">'
                . e($f['name']) . (isset($kindTag[$f['kind']]) ? ' <span class="tag">' . $kindTag[$f['kind']] . '</span>' : '')
                . ' <small>' . (int) $f['files'] . '</small></a></li>';
            $walk((int) $f['id'], $depth + 1);
        }
    };
    $walk(0, 0);
};
$pct = $quota > 0 ? min(100, $used / $quota * 100) : 0;
$accept = implode(',', array_map(fn ($x) => '.' . $x, $extensions));
?>
<div class="page-head">
  <div>
    <h1>Biblioteca</h1>
    <p>Envie os áudios da rádio e organize em pastas.</p>
  </div>
  <div class="quota">
    <span>Espaço usado: <b><?= e(format_bytes($used)) ?></b> de <?= e(format_bytes($quota)) ?></span>
    <div class="bar" role="progressbar" aria-label="Espaço usado" aria-valuenow="<?= round($pct) ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= number_format($pct, 1, '.', '') ?>%"></i></div>
  </div>
</div>

<?php if ($sent > 0 && $folder): ?>
  <div class="flash flash-success" role="status">
    <span class="flash-icon" aria-hidden="true">✓</span>
    <div><p><b><?= $sent ?> arquivo(s) enviado(s) para “<?= e($folder['name']) ?>”.</b> Título, artista e duração foram lidos automaticamente.</p>
    <a href="<?= e(url('/estudio')) ?>">Próximo passo: abrir o estúdio e arrastar as músicas para a lista →</a></div>
  </div>
<?php endif ?>

<div class="lib">
  <nav class="card stack-sm" aria-label="Pastas">
    <span class="label">Da sua rádio</span>
    <ul class="tree"><?php $renderTree($folders) ?></ul>
    <button type="button" class="btn btn-block" data-open="dlg-folder">+ Nova pasta</button>
    <?php if ($demoFolders): ?>
      <hr class="sep">
      <span class="label">Demonstração <span class="tag" title="Áudios de exemplo, iguais para todas as rádios">todas as rádios</span></span>
      <ul class="tree"><?php $renderTree($demoFolders) ?></ul>
    <?php endif ?>
  </nav>

  <?php if ($folder): ?>
  <section class="card stack" aria-labelledby="folder-title">
    <div class="page-head">
      <div>
        <h2 id="folder-title"><?= e($folder['name']) ?></h2>
        <?php if ($folder['kind'] !== 'normal'): ?><p class="small muted"><?= e($kinds[$folder['kind']]) ?></p><?php endif ?>
      </div>
      <?php if ($canEdit): ?>
        <details class="dd dd-right">
          <summary class="btn">Mais <span aria-hidden="true">▾</span></summary>
          <div class="dd-menu">
            <button type="button" data-open="dlg-folder-edit">Renomear ou mudar o tipo</button>
            <hr>
            <form method="post" action="<?= e(url('/biblioteca/pastas/' . $folder['id'] . '/excluir')) ?>">
              <?= csrf_field() ?>
              <button class="danger" data-confirm="Excluir a pasta “<?= e($folder['name']) ?>”, as subpastas e os <?= count($media) ?> arquivo(s) dela? Isso não pode ser desfeito.">Excluir pasta…</button>
            </form>
          </div>
        </details>
      <?php endif ?>
    </div>

    <?php if ($readonly): ?>
      <p class="notice">Áudios de demonstração, iguais para todas as rádios. Você pode ouvir e usar no estúdio, em eventos, vinhetas e sweepers, mas não pode mudar nem apagar. Para enviar seus próprios arquivos, escolha uma pasta em "Da sua rádio".</p>
    <?php else: ?>
    <div class="dropzone" id="dropzone" data-folder="<?= (int) $folder['id'] ?>" data-accept="<?= e($accept) ?>">
      <button type="button" class="btn btn-primary btn-large" id="pickfiles">Enviar arquivos</button>
      <p class="muted">ou arraste os arquivos para cá · MP3, OGG, WAV, FLAC, M4A e WEBM</p>
      <input type="file" id="fileinput" multiple accept="<?= e($accept) ?>,audio/*" hidden>
      <div class="upload-summary" id="upsummary" hidden aria-live="polite">
        <b data-sum-text></b>
        <div class="bar"><i></i></div>
        <ul id="uploads" class="uploads"></ul>
      </div>
    </div>
    <?php endif ?>
    <?php if ($folder['kind'] === 'time' && !$readonly): ?><p class="notice">Dois jeitos de nomear: um arquivo por minuto, <code>HHMM</code> (ex.: <code>1435.mp3</code>), ou a hora e o minuto separados, <code>HRS00</code>…<code>HRS23</code> (com <code>HRS14_O</code> para a hora cheia) e <code>MIN00</code>…<code>MIN59</code>.</p><?php endif ?>
    <?php if ($folder['kind'] === 'temperature'): ?><p class="notice">Nomes esperados: <code>TMP025</code> = 25 graus · <code>TMPN003</code> = 3 graus negativos.</p><?php endif ?>
    <?php if ($folder['kind'] === 'humidity'): ?><p class="notice">Nomes esperados: <code>HUM000</code> a <code>HUM100</code>.</p><?php endif ?>

    <?php if ($media): ?>
    <form method="post" action="<?= e(url('/biblioteca/midias/excluir')) ?>" data-selectable class="stack-sm">
      <?= csrf_field() ?>
      <div class="toolbar">
        <div class="search"><label class="sr-only" for="libfilter">Buscar nesta pasta</label><input id="libfilter" type="search" placeholder="Buscar nesta pasta" data-filter-table="mediatable"></div>
        <span class="muted small"><?= count($media) ?> arquivo(s)</span>
      </div>
      <div class="table-wrap">
        <table class="table table-responsive" id="mediatable">
          <thead><tr><?php if (!$readonly): ?><th class="check-col"><label><input type="checkbox" data-checkall aria-label="Selecionar todos"></label></th><?php endif ?><th>Arquivo</th><th>Título e artista</th><th>Duração</th><th>Tamanho</th><th class="actions-col"><span class="sr-only">Ações</span></th></tr></thead>
          <tbody>
          <?php foreach ($media as $m): $name = (string) $m['filename']; ?>
            <tr data-text="<?= e(mb_strtolower($name . ' ' . $m['title'] . ' ' . $m['artist'])) ?>">
              <?php if (!$readonly): ?><td class="check-col"><label><input type="checkbox" name="ids[]" value="<?= (int) $m['id'] ?>" aria-label="Selecionar <?= e($name) ?>"></label></td><?php endif ?>
              <td><button type="button" class="btn btn-ghost btn-icon play-preview" data-src="<?= e(url('/media/' . $m['id'])) ?>" data-name="<?= e($name) ?>" aria-label="Ouvir <?= e($name) ?>">▶</button> <?= e($name) ?><?= $m['overlap_s'] !== null ? ' <span class="tag" title="Sobreposição própria">~' . e($m['overlap_s'] + 0) . ' s</span>' : '' ?></td>
              <td><?= e($m['title'] ?? '') ?><br><small class="muted"><?= e($m['artist'] ?? '') ?></small></td>
              <td><?= e(format_duration((int) $m['duration_ms'])) ?></td>
              <td><?= e(format_bytes((int) $m['size_bytes'])) ?></td>
              <td class="actions-col"><?php if (!$readonly): ?><button type="button" class="btn btn-small" data-open="dlg-media" data-action="<?= e(url('/biblioteca/midias/' . $m['id'])) ?>" data-fill='<?= e(json_encode(['filename' => $m['filename'], 'title' => $m['title'], 'artist' => $m['artist'], 'overlap_s' => $m['overlap_s'], 'folder_id' => $m['folder_id']])) ?>'>Editar</button><?php endif ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        <p class="empty-state" id="mediatable-empty" hidden><b>Nada encontrado.</b>Tente outra palavra.</p>
      </div>
      <?php if ($canEdit): ?>
      <div class="selection-bar" data-selection-bar hidden>
        <b data-count></b>
        <button class="btn btn-small btn-danger" data-confirm-count="Excluir {n} arquivo(s)? Eles saem também das listas e vinhetas. Isso não pode ser desfeito.">Excluir</button>
      </div>
      <?php endif ?>
    </form>
    <?php else: ?>
      <p class="empty-state"><b>Esta pasta está vazia.</b>Use o botão "Enviar arquivos" acima.</p>
    <?php endif ?>
  </section>
  <?php endif ?>
</div>

<dialog class="modal" id="dlg-folder" aria-labelledby="dlg-folder-t">
  <form method="post" action="<?= e(url('/biblioteca/pastas')) ?>" class="form">
    <?= csrf_field() ?>
    <h2 id="dlg-folder-t">Nova pasta</h2>
    <div class="field"><label for="nf-name">Nome</label><input id="nf-name" name="name" required maxlength="120" placeholder="Ex.: Sertanejo"></div>
    <div class="field"><label for="nf-parent">Dentro de</label>
      <select id="nf-parent" name="parent_id"><option value="">Nenhuma (pasta principal)</option><?php foreach ($folders as $f): ?><option value="<?= (int) $f['id'] ?>" <?= $folder && $folder['id'] == $f['id'] ? 'selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach ?></select></div>
    <details class="advanced"><summary>Avançado: tipo da pasta</summary>
      <div class="field"><label for="nf-kind">Tipo</label><select id="nf-kind" name="kind"><?php foreach ($kinds as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach ?></select>
      <small>Só mude se for guardar locuções de hora, temperatura ou umidade.</small></div>
    </details>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Cancelar</button><button class="btn btn-primary">Criar pasta</button></div>
  </form>
</dialog>

<?php if ($folder && $manager): ?>
<dialog class="modal" id="dlg-folder-edit" aria-labelledby="dlg-fe-t">
  <form method="post" action="<?= e(url('/biblioteca/pastas/' . $folder['id'])) ?>" class="form">
    <?= csrf_field() ?>
    <h2 id="dlg-fe-t">Editar pasta</h2>
    <div class="field"><label for="fe-name">Nome</label><input id="fe-name" name="name" value="<?= e($folder['name']) ?>" required maxlength="120"></div>
    <div class="field"><label for="fe-kind">Tipo</label><select id="fe-kind" name="kind"><?php foreach ($kinds as $k => $label): ?><option value="<?= e($k) ?>" <?= $folder['kind'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Cancelar</button><button class="btn btn-primary">Salvar</button></div>
  </form>
</dialog>
<?php endif ?>

<dialog class="modal" id="dlg-media" aria-labelledby="dlg-media-t">
  <form method="post" class="form">
    <?= csrf_field() ?>
    <h2 id="dlg-media-t">Editar arquivo</h2>
    <div class="field"><label for="m-title">Título</label><input id="m-title" name="title" maxlength="255"></div>
    <div class="field"><label for="m-artist">Artista</label><input id="m-artist" name="artist" maxlength="255"></div>
    <div class="field"><label for="m-folder">Pasta</label><select id="m-folder" name="folder_id"><?php foreach ($folders as $f): ?><option value="<?= (int) $f['id'] ?>"><?= e($f['name']) ?></option><?php endforeach ?></select></div>
    <details class="advanced"><summary>Avançado</summary>
      <div class="stack-sm">
        <div class="field"><label for="m-file">Nome do arquivo</label><input id="m-file" name="filename" required maxlength="200"></div>
        <div class="field"><label for="m-ov">Sobreposição própria (segundos)</label><input id="m-ov" name="overlap_s" type="number" min="0" max="60" step="0.1" inputmode="decimal"><small>Deixe vazio para usar o padrão da rádio.</small></div>
      </div>
    </details>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Cancelar</button><button class="btn btn-primary">Salvar</button></div>
  </form>
</dialog>
<audio id="preview" preload="none"></audio>
