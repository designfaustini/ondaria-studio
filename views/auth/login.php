<section class="center-card">
  <h1>Entrar no estúdio</h1>
  <form method="post" action="<?= e(url('/entrar')) ?>" class="form">
    <?= csrf_field() ?>
    <label>E-mail <input type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="username" autofocus></label>
    <label>Senha <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary btn-block">Entrar</button>
  </form>
  <p class="muted">Ainda não tem rádio? <a href="<?= e(url('/cadastro')) ?>">Crie grátis</a>.</p>
</section>
