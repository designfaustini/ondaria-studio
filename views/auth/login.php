<section class="center-card">
  <h1>Entrar</h1>
  <p class="muted">Acesse o estúdio da sua rádio.</p>
  <form method="post" action="<?= e(url('/entrar')) ?>" class="form">
    <?= csrf_field() ?>
    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="username" inputmode="email" autofocus<?= field_attrs('email') ?>>
      <?= field_error('email') ?>
    </div>
    <div class="field">
      <label for="password">Senha</label>
      <div class="pw">
        <input id="password" type="password" name="password" required autocomplete="current-password"<?= field_attrs('password') ?>>
        <button type="button" class="btn btn-ghost btn-small" data-pw-toggle aria-pressed="false">Mostrar</button>
      </div>
      <?= field_error('password') ?>
    </div>
    <button class="btn btn-primary btn-block" data-busy="Entrando…">Entrar</button>
  </form>
  <p class="alt">Esqueceu a senha? Peça ao administrador da sua rádio para criar uma nova em Usuários.</p>
  <p class="alt">Ainda não tem rádio? <a href="<?= e(url('/cadastro')) ?>">Crie grátis</a></p>
</section>
