<?php $owner = $me['role'] === 'owner'; $closing = $station && $station['status'] === 'closing'; ?>
<div class="page-head">
  <div>
    <h1>Minha conta</h1>
    <p>Seus dados, sua senha e o que guardamos sobre você.</p>
  </div>
</div>

<div class="grid2">
  <form method="post" action="<?= e(url('/conta/perfil')) ?>" class="card form">
    <h2>Seus dados</h2>
    <?= csrf_field() ?>
    <div class="field"><label for="p-name">Nome</label><input id="p-name" name="name" value="<?= e(old('name', $me['name'])) ?>" required maxlength="120" autocomplete="name"<?= field_attrs('name') ?>><?= field_error('name') ?></div>
    <div class="field"><label for="p-email">E-mail (usado para entrar)</label><input id="p-email" type="email" name="email" value="<?= e(old('email', $me['email'])) ?>" required autocomplete="email" inputmode="email" data-original="<?= e($me['email']) ?>"<?= field_attrs('email') ?>><?= field_error('email') ?></div>
    <div class="field" id="confirm-email" <?= \App\Core\Session::get('_errors')['current_profile'] ?? false ? '' : 'hidden' ?>><label for="p-cur">Sua senha atual (para confirmar a troca de e-mail)</label><input id="p-cur" type="password" name="current_profile" autocomplete="current-password"<?= field_attrs('current_profile') ?>><?= field_error('current_profile') ?></div>
    <div class="actions"><button class="btn btn-primary" data-busy="Salvando…">Salvar dados</button></div>
  </form>

  <form method="post" action="<?= e(url('/conta/senha')) ?>" class="card form">
    <h2>Senha</h2>
    <?= csrf_field() ?>
    <input type="email" name="username" value="<?= e($me['email']) ?>" autocomplete="username" hidden>
    <div class="field"><label for="cur">Senha atual</label><div class="pw"><input id="cur" type="password" name="current" required autocomplete="current-password"<?= field_attrs('current') ?>><button type="button" class="btn btn-ghost btn-small" data-pw-toggle aria-pressed="false">Mostrar</button></div><?= field_error('current') ?></div>
    <div class="field"><label for="new">Nova senha</label><div class="pw"><input id="new" type="password" name="password" required minlength="10" autocomplete="new-password"<?= field_attrs('password') ?>><button type="button" class="btn btn-ghost btn-small" data-pw-toggle aria-pressed="false">Mostrar</button></div><small>Pelo menos 10 caracteres. Os outros computadores conectados serão desconectados.</small><?= field_error('password') ?></div>
    <div class="actions"><button class="btn" data-busy="Alterando…">Alterar senha</button></div>
  </form>

  <section class="card stack-sm">
    <h2>Seus dados guardados</h2>
    <p class="muted">Baixe uma cópia de tudo o que guardamos sobre você: conta, acessos e ações<?= $owner ? ', e também os dados da rádio (listas, eventos, registro de 12 meses e lista de arquivos)' : '' ?>.</p>
    <p class="small muted">Conta criada em <?= e(date('d/m/Y', strtotime((string) $me['created_at']))) ?><?= $me['terms_accepted_at'] ? ' · termos aceitos em ' . e(date('d/m/Y H:i', strtotime((string) $me['terms_accepted_at']))) . ' (versão ' . e($me['terms_version']) . ')' : '' ?>.</p>
    <div class="actions"><a class="btn" href="<?= e(url('/conta/exportar')) ?>" download>Baixar meus dados (.json)</a><a class="btn btn-ghost" href="<?= e(url('/privacidade')) ?>">Como usamos seus dados</a></div>
  </section>

  <section class="card stack-sm" id="excluir">
    <?php if ($closing && $owner): ?>
      <h2>Encerramento agendado</h2>
      <p>A rádio <b><?= e($station['name']) ?></b> será apagada em <b><?= e(date('d/m/Y', strtotime((string) $station['closing_at']))) ?></b>, com arquivos, listas, registros e usuários.</p>
      <form method="post" action="<?= e(url('/conta/encerramento/cancelar')) ?>"><?= csrf_field() ?><button class="btn">Cancelar o encerramento</button></form>
    <?php elseif ($me['role'] === 'superadmin'): ?>
      <h2>Conta da plataforma</h2>
      <p class="muted">A conta de administrador da plataforma não é excluída por aqui.</p>
    <?php else: ?>
      <h2><?= $owner ? 'Encerrar a rádio' : 'Excluir minha conta' ?></h2>
      <p class="muted"><?= $owner
          ? 'Apaga a rádio inteira depois de ' . (int) $grace . ' dias. Até lá ela continua funcionando e você pode cancelar.'
          : 'Você perde o acesso na hora. A rádio e os arquivos dela continuam com o dono.' ?></p>
      <details class="advanced" <?= isset(\App\Core\Session::get('_errors')['current_delete']) ? 'open' : '' ?>>
        <summary><?= $owner ? 'Quero encerrar a rádio' : 'Quero excluir minha conta' ?></summary>
        <form method="post" action="<?= e(url('/conta/excluir')) ?>" class="form">
          <?= csrf_field() ?>
          <div class="field"><label for="d-cur">Digite sua senha para confirmar</label><input id="d-cur" type="password" name="current_delete" required autocomplete="current-password"<?= field_attrs('current_delete') ?>><?= field_error('current_delete') ?></div>
          <button class="btn btn-danger" data-confirm="<?= $owner ? 'Agendar o apagamento da rádio ' . e($station['name'] ?? '') . ' para daqui a ' . (int) $grace . ' dias?' : 'Excluir sua conta agora? Isso não pode ser desfeito.' ?>"><?= $owner ? 'Agendar encerramento' : 'Excluir minha conta' ?></button>
        </form>
      </details>
    <?php endif ?>
  </section>
</div>
