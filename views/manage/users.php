<?php $roles = ['owner' => 'Dono', 'admin' => 'Administrador', 'operator' => 'Operador']; ?>
<div class="page-head"><h1>Usuários</h1><span class="muted"><?= count($users) ?> de <?= (int) $max ?> no seu plano</span></div>
<p class="muted">Operadores usam o estúdio e enviam arquivos. Administradores também editam eventos, vinhetas, opções e usuários.</p>
<div class="card">
  <table class="table">
    <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Último acesso</th><th>Alterar</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($roles[$u['role']] ?? $u['role']) ?><?= $u['active'] ? '' : ' <span class="tag">inativo</span>' ?></td>
        <td><?= $u['last_login_at'] ? e(date('d/m/Y H:i', strtotime((string) $u['last_login_at']))) : '—' ?></td>
        <td>
          <form method="post" action="<?= e(url('/usuarios')) ?>" class="row gap">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <?php if ($u['role'] !== 'owner'): ?>
              <select name="role"><option value="operator" <?= $u['role'] === 'operator' ? 'selected' : '' ?>>Operador</option><option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option></select>
              <label class="check"><input type="checkbox" name="active" value="1" <?= $u['active'] ? 'checked' : '' ?>> ativo</label>
            <?php endif ?>
            <input type="password" name="password" placeholder="nova senha (opcional)" autocomplete="new-password" minlength="10">
            <button class="btn btn-small">Salvar</button>
          </form>
        </td>
        <td>
          <?php if ($u['role'] !== 'owner' && (int) $u['id'] !== (int) user()['id']): ?>
            <form method="post" action="<?= e(url('/usuarios/' . $u['id'] . '/excluir')) ?>" data-confirm="Excluir <?= e($u['name']) ?>?"><?= csrf_field() ?><button class="btn btn-small btn-danger">Excluir</button></form>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
</div>
<?php if (count($users) < $max): ?>
<form method="post" action="<?= e(url('/usuarios')) ?>" class="card form grid3">
  <?= csrf_field() ?>
  <h2 class="full">Novo usuário</h2>
  <label>Nome <input name="name" required maxlength="120"></label>
  <label>E-mail <input type="email" name="email" required></label>
  <label>Senha inicial <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
  <label>Papel <select name="role"><option value="operator">Operador</option><option value="admin">Administrador</option></select></label>
  <div class="full"><button class="btn btn-primary">Criar usuário</button></div>
</form>
<?php endif ?>
