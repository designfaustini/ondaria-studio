// Páginas de gestão: confirmações, seleção em massa, upload em partes e formulário de eventos.
(() => {
  'use strict';
  const csrf = document.querySelector('meta[name="csrf"]')?.content || '';
  const base = document.querySelector('meta[name="base"]')?.content || '';

  document.addEventListener('submit', (ev) => {
    const msg = ev.target.dataset.confirm;
    if (msg && !ev.submitter?.dataset.confirmClick && !confirm(msg)) ev.preventDefault();
  });
  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-confirm-click]');
    if (btn && !confirm(btn.dataset.confirmClick)) ev.preventDefault();
  });
  document.querySelectorAll('[data-checkall]').forEach((all) => all.addEventListener('change', () => {
    all.closest('table').querySelectorAll('tbody input[type=checkbox]').forEach((c) => { c.checked = all.checked; });
  }));

  // ----- Biblioteca: ouvir, editar e enviar -----
  const preview = document.getElementById('preview');
  document.querySelectorAll('.play-preview').forEach((b) => b.addEventListener('click', () => {
    if (preview.dataset.src === b.dataset.src && !preview.paused) { preview.pause(); return; }
    preview.dataset.src = b.dataset.src;
    preview.src = b.dataset.src;
    preview.play().catch(() => {});
  }));

  const dlg = document.getElementById('editdlg');
  if (dlg) {
    document.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => {
      const d = JSON.parse(b.dataset.edit);
      const f = document.getElementById('editform');
      f.action = `${base}/biblioteca/midias/${d.id}`;
      for (const k of ['filename', 'title', 'artist', 'overlap_s', 'folder_id']) f.elements[k].value = d[k] ?? '';
      dlg.showModal();
    }));
    dlg.querySelector('[data-close]').addEventListener('click', () => dlg.close());
  }

  const zone = document.getElementById('dropzone');
  if (zone) {
    const input = document.getElementById('fileinput');
    const list = document.getElementById('uploads');
    const CHUNK = 1024 * 1024;
    const queue = [];
    let busy = false;

    const duration = (file) => new Promise((resolve) => {
      const a = new Audio();
      const url = URL.createObjectURL(file);
      const done = (ms) => { URL.revokeObjectURL(url); resolve(ms); };
      a.preload = 'metadata';
      a.onloadedmetadata = () => done(Number.isFinite(a.duration) ? Math.round(a.duration * 1000) : 0);
      a.onerror = () => done(0);
      setTimeout(() => done(0), 15000);
      a.src = url;
    });

    const send = async (file, li) => {
      const bar = li.querySelector('i');
      const status = li.querySelector('span');
      const ms = await duration(file);
      const id = [...crypto.getRandomValues(new Uint8Array(16))].map((b) => b.toString(16).padStart(2, '0')).join('');
      const total = Math.max(1, Math.ceil(file.size / CHUNK));
      for (let i = 0; i < total; i++) {
        const fd = new FormData();
        fd.append('chunk', file.slice(i * CHUNK, (i + 1) * CHUNK), 'chunk');
        Object.entries({ upload_id: id, index: i, total, size: file.size, filename: file.name, folder_id: zone.dataset.folder, duration_ms: ms })
          .forEach(([k, v]) => fd.append(k, String(v)));
        let res;
        for (let attempt = 0; attempt < 3; attempt++) {
          try {
            res = await fetch(`${base}/api/upload`, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' } }).then((r) => r.json());
            break;
          } catch (e) { if (attempt === 2) res = { ok: false, error: 'Falha de conexão.' }; }
        }
        if (!res.ok) throw new Error(res.error || 'Erro no envio.');
        bar.style.width = `${Math.round(((i + 1) / total) * 100)}%`;
        status.textContent = `${Math.round(((i + 1) / total) * 100)}%`;
      }
      status.textContent = 'ok';
      li.classList.add('ok');
    };

    const pump = async () => {
      if (busy) return;
      busy = true;
      let sent = 0;
      while (queue.length) {
        const [file, li] = queue.shift();
        try { await send(file, li); sent++; } catch (e) { li.classList.add('err'); li.querySelector('span').textContent = e.message; }
      }
      busy = false;
      if (sent && !list.querySelector('.err')) location.reload();
    };

    const add = (files) => {
      for (const file of files) {
        const li = document.createElement('li');
        li.innerHTML = '<b></b><div class="bar"><i></i></div><span>na fila</span>';
        li.querySelector('b').textContent = file.name;
        list.append(li);
        queue.push([file, li]);
      }
      pump();
    };
    input.addEventListener('change', () => { add(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, () => zone.classList.remove('over')));
    zone.addEventListener('drop', (e) => { e.preventDefault(); add(e.dataTransfer.files); });
    window.addEventListener('beforeunload', (e) => { if (busy) { e.preventDefault(); e.returnValue = ''; } });
  }

  // ----- Formulário de eventos: mostra só os campos do tipo escolhido -----
  const ef = document.getElementById('eventform');
  if (ef) {
    const type = document.getElementById('item_type');
    const sync = () => {
      ef.querySelectorAll('[data-for]').forEach((el) => { el.hidden = !el.dataset.for.split(' ').includes(type.value); });
      const per = ef.querySelector('input[name=periodicity]:checked')?.value;
      document.getElementById('hoursbox').hidden = per !== 'hours';
      document.getElementById('expbox').hidden = !ef.elements.expires.checked;
    };
    ef.addEventListener('change', sync);
    sync();
  }
})();
