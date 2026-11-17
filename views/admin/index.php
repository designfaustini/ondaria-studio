<?php $active = count(array_filter($stations, fn ($s) => $s['status'] === 'active')); ?>
<div class="page-head">
  <div>
    <h1>Plataforma</h1>
    <p><?= count($stations) ?> rádio(s) · <?= $active ?> ativa(s)</p>
  </div>
</div>

<section class="card stack-sm">
  <div class="toolbar">
    <h2 style="margin:0">Rádios</h2>
    <div class="search"><label class="sr-only" for="rf">Buscar rádio</label><input id="rf" type="search" placeholder="Buscar por nome ou e-mail" data-filter-table="radios"></div>
  </div>
  <div class="table-wrap">
    <table class="table table-responsive" id="radios">
      <thead><tr><th>Rádio</th><th>Dono</th><th>Espaço</th><th>Último "no ar"</th><th>Plano e situação</th><th class="actions-col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($stations as $s): $pct = $s['storage_mb'] ? min(100, $s['used'] / ($s['storage_mb'] * 1048576) * 100) : 0; ?>
        <tr data-text="<?= e(mb_strtolower($s['name'] . ' ' . $s['slug'] . ' ' . $s['owner_email'])) ?>">
          <td><b><?= e($s['name']) ?></b><?= $s['status'] !== 'active' ? ' <span class="tag off">suspensa</span>' : '' ?><br><small class="muted"><?= e($s['slug']) ?> · desde <?= e(date('d/m/Y', strtotime((string) $s['created_at']))) ?> · <?= (int) $s['users'] ?> usuário(s)</small></td>
          <td><?= e($s['owner_email'] ?? '—') ?></td>
          <td style="min-width:140px"><small><?= e(format_bytes((int) $s['used'])) ?> de <?= e(format_bytes($s['storage_mb'] * 1048576)) ?></small><div class="bar"><i style="width:<?= round($pct) ?>%"></i></div></td>
          <td><?= $s['np_updated_at'] ? e(date('d/m H:i', strtotime((string) $s['np_updated_at']))) : 'Nunca tocou' ?></td>
          <td>
            <form method="post" action="<?= e(url('/admin/radios/' . $s['id'])) ?>" class="row">
              <?= csrf_field() ?>
              <label class="sr-only" for="pl<?= (int) $s['id'] ?>">Plano</label>
              <select id="pl<?= (int) $s['id'] ?>" name="plan_id" style="width:auto"><?php foreach ($plans as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $s['plan_id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach ?></select>
              <label class="sr-only" for="stt<?= (int) $s['id'] ?>">Situação</label>
              <select id="stt<?= (int) $s['id'] ?>" name="status" style="width:auto"><option value="active" <?= $s['status'] === 'active' ? 'selected' : '' ?>>Ativa</option><option value="suspended" <?= $s['status'] === 'suspended' ? 'selected' : '' ?>>Suspensa</option></select>
              <button class="btn btn-small" data-busy="Salvando…">Salvar</button>
            </form>
          </td>
          <td class="actions-col"><form method="post" action="<?= e(url('/admin/radios/' . $s['id'] . '/abrir')) ?>"><?= csrf_field() ?><button class="btn btn-small btn-ghost" title="Entrar na rádio para dar suporte">Acessar →</button></form></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <p class="empty-state" id="radios-empty" hidden><b>Nenhuma rádio encontrada.</b></p>
  </div>
</section>

<details class="card advanced" style="border-top:1px solid var(--line)">
  <summary>Planos e preços (<?= count($plans) ?>)</summary>
  <div class="stack-sm">
    <?php foreach ($plans as $p): ?>
      <form method="post" action="<?= e(url('/admin/planos/' . $p['id'])) ?>" class="grid3" style="padding-bottom:16px;border-bottom:1px solid var(--line)">
        <?= csrf_field() ?>
        <div class="field"><label>Nome<input name="name" value="<?= e($p['name']) ?>" required></label></div>
        <div class="field"><label>Preço por mês (R$)<input name="price" value="<?= e(number_format($p['price_cents'] / 100, 2, ',', '')) ?>" inputmode="decimal" pattern="\d+([,.]\d{1,2})?" title="Ex.: 49,90"></label></div>
        <div class="field"><label>Espaço (MB)<input type="number" name="storage_mb" value="<?= (int) $p['storage_mb'] ?>" min="50"></label></div>
        <div class="field"><label>Usuários<input type="number" name="max_users" value="<?= (int) $p['max_users'] ?>" min="1"></label></div>
        <div class="field"><label>Players auxiliares<input type="number" name="max_aux_players" value="<?= (int) $p['max_aux_players'] ?>" min="0" max="4"></label></div>
        <div class="field"><label class="check"><input type="checkbox" name="streaming" value="1" <?= $p['streaming'] ? 'checked' : '' ?>> <span>Inclui streaming</span></label>
          <label class="check"><input type="checkbox" name="active" value="1" <?= $p['active'] ? 'checked' : '' ?>> <span>À venda</span></label></div>
        <div><button class="btn" data-busy="Salvando…">Salvar plano <?= e($p['name']) ?></button></div>
      </form>
    <?php endforeach ?>
    <p class="hint">A cobrança ainda não é automática: mude o plano da rádio aqui depois de receber o pagamento.</p>
  </div>
</details>
