<?php use App\Services\Legal as L; $app = e(app_name()); ?>
<article class="legal center-card wide" style="max-width:820px">
  <?= \App\Core\View::partial('legal/_pending') ?>
  <h1>Termos de uso</h1>
  <p class="muted">Versão <?= e(L::termsVersion()) ?> · <a href="<?= e(url('/privacidade')) ?>">Política de privacidade</a> · <a href="<?= e(url('/direitos-autorais')) ?>">Direitos autorais</a></p>

  <h2>1. Quem oferece o serviço</h2>
  <p>O <?= $app ?> é oferecido por <?= L::get('COMPANY_NAME') ?>, <?= L::get('COMPANY_DOC') ?>, com endereço em <?= L::get('COMPANY_ADDRESS') ?>. Atendimento: <?= L::get('CONTACT_EMAIL') ?>.</p>

  <h2>2. O que é o serviço</h2>
  <p>Um sistema na internet para automatizar a programação de rádios: biblioteca de áudios, listas, eventos agendados, vinhetas e registro de execução. O som é tocado e mixado no navegador do computador da rádio, que precisa ficar ligado e conectado. O envio para servidores de streaming depende de um servidor contratado pela própria rádio.</p>

  <h2>3. Conta e acesso</h2>
  <p>Quem cria a conta da rádio é o <b>dono</b> e responde pelos usuários que adicionar. Cada pessoa deve ter o seu próprio usuário e manter a senha em segredo. Avise-nos imediatamente se suspeitar de acesso indevido.</p>

  <h2>4. Planos, preço e cancelamento</h2>
  <p>Os planos, limites e preços ficam na página inicial. Toda rádio começa no plano Grátis. A troca de plano é combinada com o atendimento, com o preço e a forma de pagamento informados antes da contratação. O dono pode encerrar a rádio a qualquer momento em <i>Minha conta</i>. Nas contratações pagas feitas pela internet, você pode desistir em até 7 dias da contratação, com devolução integral do que pagou (Código de Defesa do Consumidor, art. 49), pelo mesmo canal usado para contratar.</p>

  <h2>5. Conteúdo e direitos autorais</h2>
  <p>A rádio declara que é titular ou tem autorização de uso de todo o conteúdo que envia (músicas, vinhetas, locuções), <b>inclusive para execução pública</b>. O pagamento ao ECAD e a outros titulares de direitos é responsabilidade exclusiva da rádio. O <?= $app ?> não licencia músicas. Conteúdo que infrinja direitos pode ser removido conforme a <a href="<?= e(url('/direitos-autorais')) ?>">política de direitos autorais</a>, e contas reincidentes podem ser encerradas.</p>

  <h2>6. Uso proibido</h2>
  <p>Não é permitido: enviar conteúdo ilegal ou sem autorização; tentar acessar dados de outras rádios; sobrecarregar ou atacar o sistema; e copiar, descompilar ou fazer engenharia reversa do software, ou revendê-lo sem autorização por escrito.</p>

  <h2>7. Propriedade do software</h2>
  <p>O software, a marca e a interface do <?= $app ?> pertencem a <?= L::get('COMPANY_NAME') ?> e são protegidos pela Lei 9.609/1998 (programas de computador) e pela Lei 9.610/1998 (direitos autorais). Estes termos dão apenas o direito de usar o serviço enquanto a conta estiver ativa.</p>

  <h2>8. Dados pessoais</h2>
  <p>Tratamos os dados conforme a <a href="<?= e(url('/privacidade')) ?>">Política de privacidade</a> e a Lei 13.709/2018 (LGPD). Para o conteúdo que a rádio guarda no sistema (áudios, listas, registro de execução e dados dos usuários que ela cadastra), a rádio é a controladora e nós somos o operador: tratamos esses dados só para prestar o serviço, mantemos sigilo, avisamos sobre incidentes e apagamos tudo ao fim do contrato, depois da carência.</p>

  <h2>9. Disponibilidade e responsabilidade</h2>
  <p>Trabalhamos para manter o serviço disponível, mas pode haver interrupções para manutenção ou por falhas de internet, do provedor ou do computador da rádio. Recomendamos manter uma cópia própria dos áudios. Não respondemos por perdas causadas por conteúdo enviado pela rádio, por falha no computador ou na conexão dela, ou por serviços de terceiros que ela usar (por exemplo, servidores de streaming).</p>

  <h2>10. Suspensão e encerramento</h2>
  <p>Podemos suspender a rádio em caso de violação destes termos ou de falta de pagamento, com aviso prévio quando possível. Quando o dono encerra a rádio, os dados ficam disponíveis por <?= (int) env('CLOSING_GRACE_DAYS', '30') ?> dias para exportação e depois são apagados.</p>

  <h2>11. Mudanças nestes termos</h2>
  <p>Avisaremos sobre mudanças relevantes. A nova versão vale a partir do novo aceite, pedido no próximo acesso.</p>

  <h2>12. Lei e foro</h2>
  <p>Valem as leis brasileiras. Fica eleito o foro de <?= L::get('LEGAL_FORO') ?>, sem prejuízo do foro do domicílio do consumidor quando a lei garantir.</p>
</article>
