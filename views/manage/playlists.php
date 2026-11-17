<?php $kinds = ['list' => 'Listas', 'rotation' => 'Rotações', 'sequence' => 'Sequências']; $help = [
    'list' => 'Lista de execução normal. Dentro de outra lista, um item "Carregar lista" troca a lista no ar.',
    'rotation' => 'A cada vez que é chamada, toca o próximo item e volta ao começo no fim (ex.: vinhetas que se alternam).',
    'sequence' => 'Toca todos os itens em sequência quando chamada (ex.: bloco comercial).',
]; ?>
<div class="page-head"><h1>Listas, rotações e sequências</h1></div>
<form method="post" action="<?= e(url('/listas')) ?>" class="card row gap">
  <?= csrf_field() ?>
  <input name="name" placeholder="Nome" required maxlength="120">
  <select name="kind"><option value="list">Lista</option><option value="rotation">Rotação</option><option value="sequence">Sequência</option></select>
  <button class="btn btn-primary">Criar</button>
  <span class="muted small">O conteúdo é montado no estúdio: Arquivo › Abrir.</span>
</form>
<?php foreach ($kinds as $k => $label): ?>
  <section class="card">
    <h2><?= e($label) ?></h2>
    <p class="muted small"><?= e($help[$k]) ?></p>
    <table class="table">
      <thead><tr><th>Nome</th><th>Itens</th><th>Duração</th><th>Alterada</th><th></th></tr></thead>
      <tbody>
      <?php foreach (array_filter($playlists, fn ($p) => $p['kind'] === $k) as $p): ?>
        <tr id="p<?= (int) $p['id'] ?>">
          <td>
            <form method="post" action="<?= e(url('/listas/' . $p['id'])) ?>" class="row gap"><?= csrf_field() ?><input name="name" value="<?= e($p['name']) ?>" maxlength="120"><button class="btn btn-small">Renomear</button></form>
          </td>
          <td><?= (int) $p['count'] ?></td>
          <td><?= e(format_duration((int) $p['duration_ms'])) ?></td>
          <td><?= e(date('d/m/Y H:i', strtotime((string) $p['updated_at']))) ?></td>
          <td class="row gap">
            <a class="btn btn-small" href="<?= e(url('/estudio#lista=' . $p['id'])) ?>">Abrir no estúdio</a>
            <?php if (\App\Core\Auth::isManager()): ?>
            <form method="post" action="<?= e(url('/listas/' . $p['id'] . '/excluir')) ?>" data-confirm="Excluir “<?= e($p['name']) ?>”?"><?= csrf_field() ?><button class="btn btn-small btn-danger">Excluir</button></form>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </section>
<?php endforeach ?>
