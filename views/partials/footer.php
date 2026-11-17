<?php use App\Services\Legal as L; ?>
<footer class="site-footer">
  <span>© <?= date('Y') ?> <?= e(app_name()) ?><?= L::raw('COMPANY_NAME') !== '' ? ' · ' . e(L::raw('COMPANY_NAME')) : '' ?><?= L::raw('COMPANY_DOC') !== '' ? ' · ' . e(L::raw('COMPANY_DOC')) : '' ?></span>
  <a href="<?= e(url('/termos')) ?>">Termos de uso</a>
  <a href="<?= e(url('/privacidade')) ?>">Privacidade</a>
  <a href="<?= e(url('/direitos-autorais')) ?>">Direitos autorais</a>
  <a href="<?= e(url('/contato')) ?>">Contato</a>
</footer>
