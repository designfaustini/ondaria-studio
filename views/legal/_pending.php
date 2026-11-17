<?php $missing = \App\Services\Legal::missing(); if ($missing): ?>
  <div class="flash flash-warn" role="note"><span class="flash-icon" aria-hidden="true">!</span>
    <div><p><b>Rascunho:</b> faltam dados do fornecedor (<?= e(implode(', ', $missing)) ?>). Preencha no arquivo .env e revise o texto com um advogado antes de abrir o cadastro ao público.</p></div>
  </div>
<?php endif ?>
