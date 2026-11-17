<section class="center-card">
  <h1>Os termos mudaram</h1>
  <p class="muted">Para continuar, leia e aceite a versão <?= e(\App\Services\Legal::termsVersion()) ?>. A rádio continua tocando normalmente enquanto isso.</p>
  <form method="post" action="<?= e(url('/aceitar-termos')) ?>" class="form">
    <?= csrf_field() ?>
    <div class="field">
      <label class="check"><input type="checkbox" name="terms" value="1" required data-msg-required="Para continuar, marque que leu e aceita."<?= field_attrs('terms') ?>> <span>Li e aceito os <a href="<?= e(url('/termos')) ?>" target="_blank">termos de uso</a> e a <a href="<?= e(url('/privacidade')) ?>" target="_blank">política de privacidade</a>.</span></label>
      <?= field_error('terms') ?>
    </div>
    <button class="btn btn-primary btn-block" data-busy="Registrando…">Aceitar e continuar</button>
  </form>
  <form method="post" action="<?= e(url('/sair')) ?>" class="alt"><?= csrf_field() ?><button class="link">Agora não, sair</button></form>
</section>
