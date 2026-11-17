<div class="page-head"><h1>Plataforma</h1><span class="muted"><?= count($stations) ?> rádio(s)</span></div>
<section class="card">
  <h2>Rádios</h2>
  <table class="table">
    <thead><tr><th>Rádio</th><th>Dono</th><th>Uso</th><th>Usuários</th><th>Último "no ar"</th><th>Plano e status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($stations as $s): ?>
      <tr>
        <td><b><?= e($s['name']) ?></b><br><small class="muted"><?= e($s['slug']) ?> · desde <?= e(date('d/m/Y', strtotime((string) $s['created_at']))) ?></small></td>
        <td><?= e($s['owner_email'] ?? '—') ?></td>
        <td><?= e(format_bytes((int) $s['used'])) ?> / <?= e(format_bytes($s['storage_mb'] * 1024 * 1024)) ?></td>
        <td><?= (int) $s['users'] ?></td>
        <td><?= $s['np_updated_at'] ? e(date('d/m H:i', strtotime((string) $s['np_updated_at']))) : '—' ?></td>
        <td>
          <form method="post" action="<?= e(url('/admin/radios/' . $s['id'])) ?>" class="row gap">
            <?= csrf_field() ?>
            <select name="plan_id"><?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $s['plan_id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach ?></select>
            <select name="status"><option value="active" <?= $s['status'] === 'active' ? 'selected' : '' ?>>Ativa</option><option value="suspended" <?= $s['status'] === 'suspended' ? 'selected' : '' ?>>Suspensa</option></select>
            <button class="btn btn-small">Salvar</button>
          </form>
        </td>
        <td><form method="post" action="<?= e(url('/admin/radios/' . $s['id'] . '/abrir')) ?>"><?= csrf_field() ?><button class="btn btn-small">Acessar</button></form></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</section>
<section class="card">
  <h2>Planos</h2>
  <?php foreach ($plans as $p): ?>
    <form method="post" action="<?= e(url('/admin/planos/' . $p['id'])) ?>" class="row gap wrap plan-row">
      <?= csrf_field() ?>
      <label>Nome <input name="name" value="<?= e($p['name']) ?>" size="10"></label>
      <label>Preço/mês (R$) <input name="price" value="<?= e(number_format($p['price_cents'] / 100, 2, ',', '')) ?>" size="7"></label>
      <label>Espaço (MB) <input type="number" name="storage_mb" value="<?= (int) $p['storage_mb'] ?>"></label>
      <label>Usuários <input type="number" name="max_users" value="<?= (int) $p['max_users'] ?>" min="1"></label>
      <label>Players aux. <input type="number" name="max_aux_players" value="<?= (int) $p['max_aux_players'] ?>" min="0" max="4"></label>
      <label class="check"><input type="checkbox" name="streaming" value="1" <?= $p['streaming'] ? 'checked' : '' ?>> Streaming</label>
      <label class="check"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> À venda</label>
      <button class="btn btn-small">Salvar</button>
    </form>
  <?php endforeach ?>
  <p class="hint">A cobrança não está integrada: altere o plano de cada rádio aqui depois de receber o pagamento.</p>
</section>
