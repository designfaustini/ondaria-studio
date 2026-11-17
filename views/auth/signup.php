<section class="center-card wide">
  <h1>Criar minha rádio</h1>
  <p class="muted">Grátis, em menos de 1 minuto. Você poderá mudar de plano depois.</p>
  <form method="post" action="<?= e(url('/cadastro')) ?>" class="form">
    <?= csrf_field() ?>
    <div class="field">
      <label for="station">Nome da rádio</label>
      <input id="station" name="station" value="<?= e(old('station')) ?>" required minlength="2" maxlength="120" autocomplete="organization" placeholder="Ex.: Rádio Cidade FM"<?= field_attrs('station') ?>>
      <?= field_error('station') ?>
    </div>
    <div class="field">
      <label for="name">Seu nome</label>
      <input id="name" name="name" value="<?= e(old('name')) ?>" required minlength="2" maxlength="120" autocomplete="name"<?= field_attrs('name') ?>>
      <?= field_error('name') ?>
    </div>
    <div class="field">
      <label for="email">E-mail</label>
      <input id="email" type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email" inputmode="email"<?= field_attrs('email') ?>>
      <?= field_error('email') ?>
    </div>
    <div class="field">
      <label for="password">Senha</label>
      <div class="pw">
        <input id="password" type="password" name="password" required minlength="10" autocomplete="new-password"<?= field_attrs('password') ?>>
        <button type="button" class="btn btn-ghost btn-small" data-pw-toggle aria-pressed="false">Mostrar</button>
      </div>
      <small>Pelo menos 10 caracteres. Uma frase curta é fácil de lembrar e segura.</small>
      <?= field_error('password') ?>
    </div>
    <details class="advanced">
      <summary>Fuso horário: <b data-tz-label="timezone"><?= e(old('timezone') ?: 'America/Sao_Paulo') ?></b> (detectado pelo seu computador) — alterar</summary>
      <div class="field">
        <label for="timezone">Fuso horário da rádio</label>
        <select id="timezone" name="timezone" data-tz-detect <?= old('timezone') ? 'data-keep="1"' : '' ?>>
          <?php foreach ($timezones as $tz): ?>
            <option <?= $tz === (old('timezone') ?: 'America/Sao_Paulo') ? 'selected' : '' ?>><?= e($tz) ?></option>
          <?php endforeach ?>
        </select>
        <small>Usado nos horários dos eventos e do registro.</small>
      </div>
    </details>
    <div class="field">
      <label class="check"><input type="checkbox" name="terms" value="1" required data-msg-required="Para continuar, aceite os termos."<?= field_attrs('terms') ?>> <span>Li e aceito os <a href="<?= e(url('/termos')) ?>" target="_blank">termos de uso</a> e a <a href="<?= e(url('/privacidade')) ?>" target="_blank">política de privacidade</a>, e declaro ter os direitos de transmissão do conteúdo que vou enviar.</span></label>
      <?= field_error('terms') ?>
    </div>
    <button class="btn btn-primary btn-block" data-busy="Criando a rádio…">Criar rádio</button>
  </form>
  <p class="alt">Já tem conta? <a href="<?= e(url('/entrar')) ?>">Entrar</a></p>
</section>
