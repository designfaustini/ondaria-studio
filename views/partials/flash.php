<?php foreach (flashes() as $f): [$type, $msg] = $f; $extra = $f[2] ?? null; ?>
  <div class="flash flash-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>">
    <span class="flash-icon" aria-hidden="true"><?= ['success' => '✓', 'error' => '!', 'info' => 'i', 'warn' => '!'][$type] ?? 'i' ?></span>
    <div>
      <p><?= e($msg) ?></p>
      <?php if (is_array($extra) && isset($extra['href'], $extra['label'])): ?><a href="<?= e(url($extra['href'])) ?>"><?= e($extra['label']) ?> →</a><?php endif ?>
    </div>
  </div>
<?php endforeach ?>
