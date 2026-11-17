<?php
$labels = [
    'user.create' => 'Criou usuário', 'user.update' => 'Alterou usuário', 'user.delete' => 'Excluiu usuário',
    'options.save' => 'Mudou as opções', 'media.delete' => 'Excluiu arquivos', 'folder.delete' => 'Excluiu pasta',
    'playlist.delete' => 'Excluiu lista', 'events.delete' => 'Excluiu eventos', 'eventset.delete' => 'Excluiu grade',
    'eventset.activate' => 'Trocou a grade no ar', 'support.access' => 'Suporte acessou a rádio', 'admin.station' => 'Plataforma mudou plano/situação',
    'account.profile' => 'Mudou dados pessoais', 'account.password' => 'Trocou a senha', 'account.export' => 'Baixou os dados', 'account.delete' => 'Excluiu a própria conta',
    'station.create' => 'Criou a rádio', 'station.closing' => 'Agendou o encerramento', 'station.closing_cancel' => 'Cancelou o encerramento', 'terms.accept' => 'Aceitou os termos',
];
?>
<div class="page-head">
  <div>
    <h1>Auditoria</h1>
    <p>Quem mudou o quê na rádio, inclusive acessos do suporte. Guardado por 1 ano.</p>
  </div>
</div>
<section class="card stack-sm">
  <div class="toolbar"><div class="search"><label class="sr-only" for="af">Filtrar</label><input id="af" type="search" placeholder="Filtrar por pessoa ou ação" data-filter-table="audit"></div></div>
  <?php if ($rows): ?>
  <div class="table-wrap">
    <table class="table dense table-responsive" id="audit">
      <thead><tr><th>Quando</th><th>Quem</th><th>O quê</th><th>Detalhe</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $who = $r['actor_role'] === 'superadmin' ? 'Suporte da plataforma' : ($r['actor_name'] ?? 'Usuário excluído'); $what = $labels[$r['action']] ?? $r['action']; ?>
        <tr data-text="<?= e(mb_strtolower($who . ' ' . $what . ' ' . $r['detail'])) ?>">
          <td style="white-space:nowrap"><?= e(date('d/m/Y H:i', strtotime((string) $r['created_at']))) ?></td>
          <td><?= e($who) ?></td>
          <td><?= e($what) ?></td>
          <td><?= e($r['detail'] ?? '') ?></td>
          <td><small class="muted"><?= e($r['ip']) ?></small></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <p class="empty-state" id="audit-empty" hidden><b>Nada encontrado.</b></p>
  </div>
  <?php else: ?>
    <p class="empty-state"><b>Nenhuma ação registrada ainda.</b>Alterações de usuários, opções e exclusões aparecem aqui.</p>
  <?php endif ?>
</section>
