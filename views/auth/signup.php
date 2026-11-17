<section class="center-card wide">
  <h1>Criar minha rádio</h1>
  <p class="muted">Sua rádio começa no plano Grátis. Você pode mudar de plano depois.</p>
  <form method="post" action="<?= e(url('/cadastro')) ?>" class="form">
    <?= csrf_field() ?>
    <label>Nome da rádio <input name="station" value="<?= e(old('station')) ?>" required maxlength="120"></label>
    <label>Seu nome <input name="name" value="<?= e(old('name')) ?>" required maxlength="120" autocomplete="name"></label>
    <label>E-mail <input type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email"></label>
    <label>Senha (mínimo 10 caracteres) <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
    <label>Fuso horário da rádio
      <select name="timezone">
        <?php foreach ($timezones as $tz): ?>
          <option <?= $tz === (old('timezone') ?: 'America/Sao_Paulo') ? 'selected' : '' ?>><?= e($tz) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <label class="check"><input type="checkbox" name="terms" value="1" required> Li e aceito os termos de uso e declaro ter os direitos de transmissão do conteúdo que vou enviar.</label>
    <button class="btn btn-primary btn-block">Criar rádio</button>
  </form>
</section>
