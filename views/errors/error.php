<?php
$what = [
    403 => 'Você não tem permissão para esta área. Se precisar dela, peça acesso ao administrador da rádio.',
    404 => 'O endereço pode ter mudado ou o item foi apagado.',
    405 => 'Volte e use o botão da página.',
    419 => 'Por segurança, a página ficou aberta tempo demais. Recarregue e envie de novo; o que você digitou pode precisar ser refeito.',
    429 => 'Espere alguns minutos e tente de novo.',
    500 => 'O problema foi registrado. Tente de novo em instantes.',
][$code] ?? '';
$retry = in_array($code, [419, 429, 500], true);
?>
<section class="center-card">
  <p class="error-code" aria-hidden="true"><?= (int) $code ?></p>
  <h1><?= e($message) ?></h1>
  <p class="muted"><?= e($what) ?></p>
  <div class="actions">
    <?php if ($retry): ?>
      <button type="button" class="btn btn-primary" data-back>Voltar e tentar de novo</button>
      <a class="btn" href="<?= e(url('/')) ?>">Ir para o início</a>
    <?php else: ?>
      <a class="btn btn-primary" href="<?= e(url(user() ? '/estudio' : '/')) ?>">Ir para o início</a>
    <?php endif ?>
  </div>
</section>
