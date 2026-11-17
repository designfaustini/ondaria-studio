<?php
$roles = ['owner' => 'Dono', 'admin' => 'Administrador', 'operator' => 'Operador'];
$hasErrors = (bool) \App\Core\Session::get('_errors');
$full = count($users) >= $max;
?>
<div class="page-head">
  <div>
    <h1>Usuários</h1>
    <p><b>Operadores</b> usam o estúdio e enviam arquivos. <b>Administradores</b> também mudam eventos, vinhetas, opções e usuários.</p>
  </div>
  <?php if (!$full): ?><button type="button" class="btn btn-primary" data-open="dlg-new">+ Adicionar usuário</button><?php endif ?>
</div>

<section class="card stack-sm">
  <div class="quota" style="max-width:320px">
    <span><b><?= count($users) ?> de <?= (int) $max ?></b> usuários do seu plano</span>
    <div class="bar"><i style="width:<?= min(100, round(count($users) / max(1, $max) * 100)) ?>%"></i></div>
    <?php if ($full): ?><span>Limite atingido. Para mais pessoas, fale com o suporte sobre outro plano.</span><?php endif ?>
  </div>
  <div class="table-wrap">
    <table class="table table-responsive">
      <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Último acesso</th><th class="actions-col"><span class="sr-only">Ações</span></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><b><?= e($u['name']) ?></b><?= (int) $u['id'] === (int) user()['id'] ? ' <span class="tag">você</span>' : '' ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($roles[$u['role']] ?? $u['role']) ?><?= $u['active'] ? '' : ' <span class="tag off">bloqueado</span>' ?></td>
          <td><?= $u['last_login_at'] ? e(date('d/m/Y H:i', strtotime((string) $u['last_login_at']))) : 'Nunca entrou' ?></td>
          <td class="actions-col">
            <?php if ($u['role'] !== 'owner' || (int) $u['id'] === (int) user()['id']): ?>
              <button type="button" class="btn btn-small" data-open="dlg-edit" data-fill='<?= e(json_encode(['id' => $u['id'], 'role' => $u['role'] === 'owner' ? 'admin' : $u['role'], 'active' => $u['active'], 'password' => ''])) ?>' data-edit-owner="<?= $u['role'] === 'owner' ? '1' : '0' ?>" data-del="<?= e(url('/usuarios/' . $u['id'] . '/excluir')) ?>" data-name="<?= e($u['name']) ?>">Editar</button>
            <?php endif ?>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</section>

<dialog class="modal" id="dlg-new" aria-labelledby="dnu-t" <?= $hasErrors ? 'data-autoopen' : '' ?>>
  <form method="post" action="<?= e(url('/usuarios')) ?>" class="form">
    <?= csrf_field() ?>
    <h2 id="dnu-t">Adicionar usuário</h2>
    <div class="field"><label for="nu-name">Nome</label><input id="nu-name" name="name" required maxlength="120" value="<?= e(old('name')) ?>" autocomplete="off"<?= field_attrs('name') ?>><?= field_error('name') ?></div>
    <div class="field"><label for="nu-email">E-mail</label><input id="nu-email" type="email" name="email" required value="<?= e(old('email')) ?>" autocomplete="off" inputmode="email"<?= field_attrs('email') ?>><?= field_error('email') ?></div>
    <div class="field"><label for="nu-pass">Senha inicial</label>
      <div class="input-group"><input id="nu-pass" type="text" name="password" required minlength="10" autocomplete="new-password"<?= field_attrs('password') ?>><button type="button" class="btn" data-genpw="nu-pass">Gerar senha</button></div>
      <small>"Gerar senha" cria uma senha forte e copia. Envie à pessoa por um canal seguro.</small>
      <?= field_error('password') ?></div>
    <div class="radio-cards" role="radiogroup" aria-label="Papel">
      <label><input type="radio" name="role" value="operator" <?= old('role', 'operator') === 'operator' ? 'checked' : '' ?>><span><b>Operador</b><small>Usa o estúdio e envia arquivos.</small></span></label>
      <label><input type="radio" name="role" value="admin" <?= old('role') === 'admin' ? 'checked' : '' ?>><span><b>Administrador</b><small>Também muda eventos, opções e usuários.</small></span></label>
    </div>
    <div class="actions"><button type="button" class="btn btn-ghost" data-close>Cancelar</button><button class="btn btn-primary" data-busy="Criando…">Adicionar</button></div>
  </form>
</dialog>

<dialog class="modal" id="dlg-edit" aria-labelledby="deu-t">
  <form method="post" action="<?= e(url('/usuarios')) ?>" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="id">
    <h2 id="deu-t">Editar usuário</h2>
    <div data-not-owner class="stack-sm">
      <div class="field"><label for="eu-role">Papel</label><select id="eu-role" name="role"><option value="operator">Operador</option><option value="admin">Administrador</option></select></div>
      <label class="check"><input type="checkbox" name="active" value="1"> <span>Pode entrar no sistema<small class="muted" style="display:block">Desmarque para bloquear sem apagar o histórico.</small></span></label>
    </div>
    <div class="field"><label for="eu-pass">Nova senha (opcional)</label>
      <div class="input-group"><input id="eu-pass" type="text" name="password" minlength="10" autocomplete="new-password" placeholder="Deixe vazio para manter"><button type="button" class="btn" data-genpw="eu-pass">Gerar senha</button></div></div>
    <div class="actions">
      <button type="button" class="btn btn-danger" data-not-owner data-delete-user>Excluir usuário</button>
      <span class="spacer"></span>
      <button type="button" class="btn btn-ghost" data-close>Cancelar</button>
      <button class="btn btn-primary" data-busy="Salvando…">Salvar</button>
    </div>
  </form>
  <form method="post" id="deluser" hidden><?= csrf_field() ?></form>
</dialog>
