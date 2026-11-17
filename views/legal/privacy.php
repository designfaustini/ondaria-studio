<?php use App\Services\Legal as L; $app = e(app_name()); ?>
<article class="legal center-card wide" style="max-width:820px">
  <?= \App\Core\View::partial('legal/_pending') ?>
  <h1>Política de privacidade</h1>
  <p class="muted">Versão <?= e(L::termsVersion()) ?> · Lei 13.709/2018 (LGPD)</p>

  <h2>Quem cuida dos seus dados</h2>
  <p><b>Controlador:</b> <?= L::get('COMPANY_NAME') ?>, <?= L::get('COMPANY_DOC') ?>, <?= L::get('COMPANY_ADDRESS') ?>.<br>
  <b>Encarregado (DPO):</b> <?= L::get('DPO_NAME') ?>, <?= L::get('DPO_EMAIL') ?>.</p>
  <p>Para o conteúdo que cada rádio guarda aqui (áudios, listas, registro de execução e os usuários que ela cadastra), a rádio é a controladora e nós somos o operador.</p>

  <h2>Que dados tratamos e por quê</h2>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Dado</th><th>Para quê</th><th>Base legal</th><th>Por quanto tempo</th></tr></thead>
    <tbody>
      <tr><td>Nome, e-mail e senha (guardada só como hash irreversível)</td><td>Criar e proteger a conta</td><td>Execução de contrato (art. 7º, V)</td><td>Enquanto a conta existir</td></tr>
      <tr><td>Registros de acesso: IP, data, hora e navegador</td><td>Segurança e obrigação legal</td><td>Obrigação legal (Marco Civil, art. 15) e legítimo interesse</td><td><?= (int) env('ACCESS_LOG_DAYS', '180') ?> dias</td></tr>
      <tr><td>Tentativas de login (IP ou e-mail)</td><td>Bloquear ataques de senha</td><td>Legítimo interesse</td><td>24 horas</td></tr>
      <tr><td>Ações administrativas (quem mudou o quê)</td><td>Auditoria e transparência para a rádio</td><td>Legítimo interesse</td><td><?= (int) env('AUDIT_RETENTION_DAYS', '365') ?> dias</td></tr>
      <tr><td>Registro de execução (o que tocou e quando)</td><td>Relatórios da rádio para anunciantes</td><td>Execução de contrato (a rádio é a controladora)</td><td><?= (int) env('LOG_RETENTION_DAYS', '730') ?> dias</td></tr>
      <tr><td>Áudios, listas, eventos e lembretes enviados pela rádio</td><td>Prestar o serviço</td><td>Execução de contrato (a rádio é a controladora)</td><td>Até o encerramento da rádio + <?= (int) env('CLOSING_GRACE_DAYS', '30') ?> dias</td></tr>
      <tr><td>Latitude e longitude da rádio (se você ativar o clima automático)</td><td>Buscar temperatura e umidade</td><td>Execução de contrato</td><td>Até você apagar nas Opções</td></tr>
    </tbody>
  </table></div>

  <h2>Com quem compartilhamos</h2>
  <ul>
    <li><b>Hospedagem:</b> <?= L::get('HOSTING_INFO') ?>, onde ficam o sistema e os arquivos.</li>
    <li><b>Open-Meteo</b> (Suíça/União Europeia): só se a rádio ativar o clima automático. Recebe a latitude, a longitude e o endereço IP do nosso servidor, sem nenhum dado de pessoas. É uma transferência internacional para um país com proteção adequada (art. 33).</li>
    <li><b>Servidores de streaming e rádios online</b> configurados pela própria rádio: o navegador se conecta direto a eles.</li>
  </ul>
  <p>Não vendemos dados e não usamos anúncios nem ferramentas de rastreamento.</p>

  <h2>Cookies e armazenamento no navegador</h2>
  <p>Usamos um único cookie, o da sessão, necessário para você continuar conectado. Ele dispensa consentimento. O estúdio também guarda no seu navegador as preferências deste computador (placas de som, volume, rascunho da lista). Isso não sai do seu computador.</p>

  <h2>Seus direitos</h2>
  <p>Você pode confirmar, acessar, corrigir, levar (portabilidade) e pedir a eliminação dos seus dados, além de saber com quem os compartilhamos (art. 18). A maior parte você faz sozinho em <a href="<?= e(url('/conta')) ?>">Minha conta</a>: corrigir dados, baixar uma cópia e excluir a conta. Para o resto, escreva para <?= L::get('DPO_EMAIL') ?>; respondemos em até 15 dias. Se o assunto for um dado que a rádio controla, encaminhamos o pedido para ela. Você também pode reclamar à ANPD (gov.br/anpd).</p>

  <h2>Segurança e incidentes</h2>
  <p>Usamos HTTPS, senhas com hash, separação total entre rádios, proteção contra ataques comuns e registros de auditoria. Se houver um incidente que possa causar risco ou dano relevante, avisamos a ANPD e as pessoas afetadas em até 3 dias úteis (Resolução CD/ANPD 15/2024).</p>

  <h2>Idade</h2>
  <p>O serviço é para quem tem 18 anos ou mais e age em nome de uma rádio.</p>

  <h2>Mudanças</h2>
  <p>Avisaremos sobre mudanças relevantes nesta política no próximo acesso.</p>
</article>
