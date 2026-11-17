<?php use App\Services\Legal as L; $app = e(app_name()); ?>
<article class="legal center-card wide" style="max-width:820px">
  <?= \App\Core\View::partial('legal/_pending') ?>
  <h1>Direitos autorais e remoção de conteúdo</h1>
  <p>O <?= $app ?> é uma ferramenta: cada rádio envia o próprio conteúdo e é responsável por ter as autorizações, inclusive a de execução pública junto ao ECAD (Lei 9.610/1998). Respeitamos os titulares de direitos e removemos conteúdo infrator quando recebemos uma notificação válida.</p>

  <h2>Como notificar</h2>
  <p>Envie para <?= L::get('COPYRIGHT_EMAIL') ?>:</p>
  <ol>
    <li>seu nome, documento e contato, e se você é o titular ou representante;</li>
    <li>a obra protegida;</li>
    <li>a rádio e o conteúdo apontado (nome do arquivo ou endereço público "no ar");</li>
    <li>uma declaração de que as informações são verdadeiras e de que o uso não foi autorizado.</li>
  </ol>

  <h2>O que acontece depois</h2>
  <ol>
    <li>Confirmamos o recebimento e analisamos a notificação em até 5 dias úteis.</li>
    <li>Se ela for válida, suspendemos o acesso ao conteúdo e avisamos a rádio.</li>
    <li>A rádio pode responder com uma contranotificação e documentos de autorização.</li>
    <li>Contas com infrações repetidas são encerradas, conforme os <a href="<?= e(url('/termos')) ?>">termos de uso</a>.</li>
  </ol>
  <p>Notificações de má-fé podem gerar responsabilidade de quem as envia.</p>
</article>
