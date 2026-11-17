<?php
$kinds = ['list' => 'Listas', 'rotation' => 'Rotações', 'sequence' => 'Sequências'];
$help = [
    'list' => 'Lista de execução. Dentro de outra lista, o item "Carregar lista" troca a lista no ar.',
    'rotation' => 'A cada vez que é chamada, toca o próximo item; no fim volta ao começo (ex.: vinhetas que se alternam).',
    'sequence' => 'Toca todos os itens de uma vez quando chamada (ex.: bloco comercial).',
];
$manager = \App\Core\Auth::isManager();
?>
<div class="page-head">
  <div>
    <h1>Listas</h1>
    <p>Listas salvas, rotações e sequências. O conteúdo é montado no estúdio, arrastando da biblioteca.</p>
  </div>
  <button type="button" class="btn btn-primary" data-open="dlg-new">+ Criar lista</button>
</div>

<?php foreach ($kinds as $k => $label): $rows = array_filter($playlists, fn ($p) => $p['kind'] === $k); ?>
  <section class="card stack-sm" aria-labelledby="h-<?= $k ?>">
    <div>
      <h2 id="h-<?= $k ?>"><?= e($label) ?> <small class="muted"><?= count($rows) ?></small></h2>
      <p class="muted small"><?= e($help[$k]) ?></p>
    </div>
    <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="table table-responsive">
        <thead><tr><th>Nome</th><th>Itens</th><th>Duração</th><th>Alterada em</th><th class="actions-col"><span class="sr-only">Ações</span></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): ?>
          <tr id="p<?= (int) $p['id'] ?>">
            <td><a href="<?= e(url('/estudio#lista=' . $p['id'])) ?>" title="Abrir no estúdio"><b><?= e($p['name']) ?></b></a></td>
            <td><?= (int) $p['count'] ?></td>
            <td><?= e(format_duration((int) $p['duration_ms'])) ?></td>
            <td><?= e(date('d/m/Y H:i', strtotime((string) $p['updated_at']))) ?></td>
            <td class="actions-col">
              <a class="btn btn-small" href="<?= e(url('/estudio#lista=' . $p['id'])) ?>">Abrir no estúdio</a>
              <details class="dd dd-right" style="display:inline-block">
                <summary class="btn btn-small btn-icon" aria-label="Mais ações para <?= e($p['name']) ?>">⋯</summary>
                <div class="dd-menu">
                  <button type="button" data-open="dlg-rename" data-action="<?= e(url('/listas/' . $p['id'])) ?>" data-fill='<?= e(json_encode(['name' => $p['name']])) ?>'>Renomear</button>
                  <?php if ($manager): ?>
                    <form method="post" action="<?= e(url('/listas/' . $p['id'] . '/excluir')) ?>"><?= csrf_field() ?><button class="danger" data-confirm="Excluir “<?= e($p['name']) ?>”? Eventos e listas que a usam vão pular este item. Isso não pode ser desfeito.">Excluir…</button></form>
                  <?php endif ?>
                </div>
              </details>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="empty-state"><b>Nenhuma ainda.</b>Use "Criar lista" e escolha o tipo.</p>
    <?php endif ?>
  </section>
<?php endforeach ?>

<dialog class="modal" id="dlg-new" aria-labelledby="dn-t">
  <form method="post" action="<?= e(url('/listas')) ?>" class="form">
    <?= csrf_field() ?>
    <h2 id="dn-t">Criar lista</h2>
    <div class="field"><label for="dn-name">Nome</label><input id="dn-name" name="name" required maxlength="120" placeholder="Ex.: Manhã de segunda"></div>
    <div class="radio-cards" role="radiogroup" aria-label="Tipo">
      <label><input type="radio" name="kind" value="list" checked><span><b>Lista</b><small>Para tocar no ar, em ordem.</small></span></label>
      <label><input type="radio" name="kind" value="sequence"><span><b>Sequência</b><small>Um bloco que toca inteiro (ex.: comerciais).</small></span></label>
      <label><input type="radio" name="kind" value="rotation"><span><b>Rotação</b><small>Toca um item por vez, alternando.</small></span></label>
    </div>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Cancelar</button><button class="btn btn-primary">Criar e abrir no estúdio</button></div>
  </form>
</dialog>
<dialog class="modal" id="dlg-rename" aria-labelledby="dr-t">
  <form method="post" class="form">
    <?= csrf_field() ?>
    <h2 id="dr-t">Renomear</h2>
    <div class="field"><label for="dr-name">Nome</label><input id="dr-name" name="name" required maxlength="120"></div>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Cancelar</button><button class="btn btn-primary">Salvar</button></div>
  </form>
</dialog>
