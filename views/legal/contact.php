<?php use App\Services\Legal as L; ?>
<article class="legal center-card">
  <?= \App\Core\View::partial('legal/_pending') ?>
  <h1>Contato</h1>
  <dl class="defs">
    <dt>Fornecedor</dt><dd><?= L::get('COMPANY_NAME') ?></dd>
    <dt>CPF/CNPJ</dt><dd><?= L::get('COMPANY_DOC') ?></dd>
    <dt>Endereço</dt><dd><?= L::get('COMPANY_ADDRESS') ?></dd>
    <dt>Atendimento</dt><dd><?= L::get('CONTACT_EMAIL') ?></dd>
    <dt>Privacidade (encarregado)</dt><dd><?= L::get('DPO_EMAIL') ?></dd>
    <dt>Direitos autorais</dt><dd><?= L::get('COPYRIGHT_EMAIL') ?></dd>
  </dl>
</article>
