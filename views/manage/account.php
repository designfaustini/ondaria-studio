<div class="page-head"><h1>Minha conta</h1></div>
<form method="post" action="<?= e(url('/conta')) ?>" class="card form narrow">
  <?= csrf_field() ?>
  <p><b><?= e(user()['name']) ?></b><br><span class="muted"><?= e(user()['email']) ?></span></p>
  <label>Senha atual <input type="password" name="current" required autocomplete="current-password"></label>
  <label>Nova senha (10+ caracteres) <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
  <label>Repita a nova senha <input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
  <button class="btn btn-primary">Alterar senha</button>
</form>
