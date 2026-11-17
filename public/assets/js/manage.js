// Comportamentos compartilhados das telas (componentes reutilizados em todo o produto).
(() => {
  'use strict';
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const csrf = $('meta[name="csrf"]')?.content || '';
  const base = $('meta[name="base"]')?.content || '';

  /* ---------- Menus "Mais": um aberto por vez, fecha ao clicar fora ou com Esc ---------- */
  document.addEventListener('click', (e) => {
    $$('details.dd[open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') $$('details.dd[open]').forEach((d) => { d.open = false; d.querySelector('summary')?.focus(); });
  });
  $$('details.dd').forEach((d) => d.addEventListener('toggle', () => {
    if (d.open) $$('details.dd[open]').forEach((o) => { if (o !== d && !o.contains(d)) o.open = false; });
  }));

  /* ---------- Diálogos ---------- */
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-open]');
    if (opener) {
      const dlg = document.getElementById(opener.dataset.open);
      if (dlg) {
        e.preventDefault();
        opener.closest('details.dd')?.removeAttribute('open');
        if (opener.dataset.fill) fillForm(dlg.querySelector('form'), JSON.parse(opener.dataset.fill));
        if (opener.dataset.action) dlg.querySelector('form').action = opener.dataset.action;
        dlg.showModal();
        dlg.querySelector('input:not([type=hidden]),select,textarea')?.focus();
      }
    }
    if (e.target.closest('[data-close]')) e.target.closest('dialog')?.close();
    if (e.target.closest('[data-back]')) { if (history.length > 1) history.back(); else location.href = `${base}/`; }
  });
  // Diálogo com erro do servidor reabre sozinho, com o que já foi digitado.
  $$('dialog[data-autoopen]').forEach((d) => d.showModal());

  // Usuários: dono não troca de papel nem é excluído; exclusão com confirmação.
  document.addEventListener('click', (e) => {
    const ed = e.target.closest('[data-edit-owner]');
    if (ed) {
      const dlg = $('#dlg-edit');
      $$('[data-not-owner]', dlg).forEach((x) => { x.hidden = ed.dataset.editOwner === '1'; });
      dlg.dataset.del = ed.dataset.del;
      dlg.dataset.name = ed.dataset.name;
      $('#deu-t').textContent = `Editar ${ed.dataset.name}`;
    }
    if (e.target.closest('[data-delete-user]')) {
      const dlg = $('#dlg-edit');
      if (confirm(`Excluir ${dlg.dataset.name}? A pessoa perde o acesso na hora. Isso não pode ser desfeito.`)) {
        const f = $('#deluser');
        f.action = dlg.dataset.del;
        f.submit();
      }
    }
  });

  function fillForm(form, data) {
    for (const [k, v] of Object.entries(data)) {
      const f = form.elements[k];
      if (!f) continue;
      if (f.type === 'checkbox') f.checked = !!Number(v) || v === true; else f.value = v ?? '';
    }
  }

  /* ---------- Confirmação antes do irreversível + resposta imediata ao enviar ---------- */
  document.addEventListener('submit', (e) => {
    const form = e.target;
    const btn = e.submitter;
    const msg = btn?.dataset.confirm || form.dataset.confirm;
    if (msg && !confirm(msg)) { e.preventDefault(); return; }
    if (form.method.toLowerCase() === 'get' && !btn) return;
    if (btn && !btn.matches('[data-no-busy]')) {
      // Limiar de Doherty: o botão muda na hora. O disabled vem depois para o valor do botão ir no POST.
      btn.setAttribute('aria-busy', 'true');
      btn.dataset.label = btn.textContent;
      if (btn.dataset.busy) btn.textContent = btn.dataset.busy;
      setTimeout(() => { btn.disabled = true; }, 0);
      setTimeout(() => { btn.disabled = false; btn.removeAttribute('aria-busy'); if (btn.dataset.label) btn.textContent = btn.dataset.label; }, 8000);
    }
  });
  window.addEventListener('pageshow', () => $$('[aria-busy="true"]').forEach((b) => { b.disabled = false; b.removeAttribute('aria-busy'); }));

  /* ---------- Validação enquanto digita, com a mensagem ao lado do campo ---------- */
  const fieldMsg = (f) => {
    if (f.validity.valueMissing) return f.dataset.msgRequired || 'Preencha este campo.';
    if (f.validity.typeMismatch && f.type === 'email') return 'Digite um e-mail válido, como nome@dominio.com.br.';
    if (f.validity.typeMismatch && f.type === 'url') return 'Digite um endereço completo começando com https://';
    if (f.validity.tooShort) return `Use pelo menos ${f.minLength} caracteres (faltam ${f.minLength - f.value.length}).`;
    if (f.validity.rangeUnderflow) return `O mínimo é ${f.min}.`;
    if (f.validity.rangeOverflow) return `O máximo é ${f.max}.`;
    if (f.validity.patternMismatch) return f.title || 'Formato inválido.';
    if (f.validity.customError) return f.validationMessage;
    return f.validationMessage;
  };
  const errEl = (f) => {
    const id = f.getAttribute('aria-describedby')?.split(' ').find((x) => x.endsWith('-err'));
    let el = id ? document.getElementById(id) : null;
    if (!el) {
      el = document.createElement('p');
      el.className = 'field-error';
      el.id = `${f.name || f.id || 'f'}-${Math.random().toString(36).slice(2, 7)}-err`;
      el.setAttribute('role', 'alert');
      (f.closest('.field') || f.parentElement).append(el);
      f.setAttribute('aria-describedby', `${f.getAttribute('aria-describedby') || ''} ${el.id}`.trim());
    }
    return el;
  };
  const check = (f) => {
    if (!f.willValidate || f.type === 'hidden') return true;
    const ok = f.checkValidity();
    const el = errEl(f);
    el.textContent = ok ? '' : fieldMsg(f);
    el.hidden = ok;
    if (ok) f.removeAttribute('aria-invalid'); else f.setAttribute('aria-invalid', 'true');
    return ok;
  };
  document.addEventListener('blur', (e) => { const f = e.target; if (f.matches?.('form:not([novalidate]) :is(input,select,textarea)') && f.value !== '') { f.dataset.touched = '1'; check(f); } }, true);
  document.addEventListener('input', (e) => { const f = e.target; if (f.dataset?.touched || f.getAttribute('aria-invalid')) check(f); });
  $$('form').forEach((form) => {
    form.setAttribute('novalidate', '');
    form.addEventListener('submit', (e) => {
      const fields = $$('input,select,textarea', form).filter((f) => !f.disabled && !f.closest('[hidden]'));
      const bad = fields.filter((f) => !check(f));
      if (bad.length) { e.preventDefault(); e.stopImmediatePropagation(); bad[0].focus(); }
    }, true);
  });

  /* ---------- Senhas: mostrar/ocultar e gerar ---------- */
  $$('[data-pw-toggle]').forEach((b) => b.addEventListener('click', () => {
    const input = b.parentElement.querySelector('input');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    b.textContent = show ? 'Ocultar' : 'Mostrar';
    b.setAttribute('aria-pressed', String(show));
  }));
  $$('[data-genpw]').forEach((b) => b.addEventListener('click', () => {
    const input = document.getElementById(b.dataset.genpw);
    const chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    const rnd = crypto.getRandomValues(new Uint32Array(14));
    input.value = [...rnd].map((n) => chars[n % chars.length]).join('').replace(/(.{4})(?=.)/g, '$1-');
    input.type = 'text';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    navigator.clipboard?.writeText(input.value).then(() => { b.textContent = 'Copiada ✓'; setTimeout(() => { b.textContent = 'Gerar senha'; }, 2000); });
  }));

  /* ---------- Minha conta: pede a senha só se o e-mail mudar ---------- */
  const pe = $('#p-email');
  if (pe) {
    const box = $('#confirm-email');
    const sync = () => { const changed = pe.value.trim().toLowerCase() !== pe.dataset.original; box.hidden = !changed; $('#p-cur').required = changed; };
    pe.addEventListener('input', sync);
  }

  /* ---------- O sistema descobre: fuso do aparelho ---------- */
  $$('[data-tz-detect]').forEach((sel) => {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (!sel.dataset.keep && [...sel.options].some((o) => o.value === tz)) sel.value = tz;
    const label = document.querySelector(`[data-tz-label="${sel.id}"]`);
    if (label) label.textContent = sel.value;
    sel.addEventListener('change', () => { if (label) label.textContent = sel.value; });
  });

  /* ---------- Sliders com o valor em % ao lado ---------- */
  $$('input[type=range][data-out]').forEach((r) => {
    const out = document.getElementById(r.dataset.out);
    const show = () => { out.textContent = `${Math.round(r.value * 100)}%`; };
    r.addEventListener('input', show);
    show();
  });

  /* ---------- Localização para o clima ---------- */
  $$('[data-geo]').forEach((b) => b.addEventListener('click', () => {
    if (!navigator.geolocation) { b.textContent = 'Navegador sem localização'; return; }
    b.setAttribute('aria-busy', 'true');
    navigator.geolocation.getCurrentPosition((p) => {
      $('[name=weather_lat]').value = p.coords.latitude.toFixed(4);
      $('[name=weather_lon]').value = p.coords.longitude.toFixed(4);
      const src = $('[name=weather_source][value=openmeteo]');
      if (src) src.checked = true;
      b.removeAttribute('aria-busy');
      b.textContent = 'Localização preenchida ✓';
    }, () => { b.removeAttribute('aria-busy'); b.textContent = 'Permissão negada: digite a latitude e a longitude'; }, { timeout: 10000 });
  }));

  /* ---------- Primeiros passos: ocultar sem culpa ---------- */
  const ob = $('[data-onboarding]');
  if (ob) {
    try { if (localStorage.getItem('ondaria.ob.hidden') === '1') ob.hidden = true; } catch (e) { /* sem armazenamento */ }
    $('[data-onboarding-hide]', ob).addEventListener('click', () => { ob.hidden = true; try { localStorage.setItem('ondaria.ob.hidden', '1'); } catch (e) { /* ok */ } });
  }

  /* ---------- Tabelas com seleção: a barra de ações só aparece com algo marcado ---------- */
  $$('[data-selectable]').forEach((form) => {
    const bar = $('[data-selection-bar]', form);
    const all = $('[data-checkall]', form);
    const boxes = () => $$('tbody input[type=checkbox]', form);
    const sync = () => {
      const n = boxes().filter((c) => c.checked).length;
      boxes().forEach((c) => c.closest('tr').classList.toggle('is-selected', c.checked));
      if (bar) { bar.hidden = n === 0; $('[data-count]', bar).textContent = `${n} selecionado${n > 1 ? 's' : ''}`; }
      if (all) { all.checked = n > 0 && n === boxes().length; all.indeterminate = n > 0 && n < boxes().length; }
      $$('[data-confirm-count]', form).forEach((b) => { b.dataset.confirm = b.dataset.confirmCount.replace('{n}', n); });
    };
    all?.addEventListener('change', () => { boxes().forEach((c) => { c.checked = all.checked; }); sync(); });
    form.addEventListener('change', (e) => { if (e.target.matches('tbody input[type=checkbox]')) sync(); });
    sync();
  });

  /* ---------- Filtro rápido em tabelas ---------- */
  $$('[data-filter-table]').forEach((input) => {
    const table = document.getElementById(input.dataset.filterTable);
    const empty = document.getElementById(`${input.dataset.filterTable}-empty`);
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      let shown = 0;
      $$('tbody tr[data-text]', table).forEach((tr) => { const ok = tr.dataset.text.includes(q); tr.hidden = !ok; if (ok) shown++; });
      if (empty) empty.hidden = shown > 0;
    });
  });

  /* ---------- Biblioteca: ouvir ---------- */
  const preview = $('#preview');
  $$('.play-preview').forEach((b) => b.addEventListener('click', () => {
    const playing = preview.dataset.src === b.dataset.src && !preview.paused;
    $$('.play-preview').forEach((x) => { x.textContent = '▶'; x.setAttribute('aria-label', `Ouvir ${x.dataset.name}`); });
    if (playing) { preview.pause(); return; }
    preview.dataset.src = b.dataset.src;
    preview.src = b.dataset.src;
    preview.play().then(() => { b.textContent = '❚❚'; b.setAttribute('aria-label', `Pausar ${b.dataset.name}`); }).catch(() => {});
  }));
  preview?.addEventListener('ended', () => $$('.play-preview').forEach((x) => { x.textContent = '▶'; }));

  /* ---------- Biblioteca: envio em partes com progresso geral, resumo e "tentar de novo" ---------- */
  const zone = $('#dropzone');
  if (zone) {
    const input = $('#fileinput');
    const list = $('#uploads');
    const summary = $('#upsummary');
    const CHUNK = 1024 * 1024;
    const ACCEPT = zone.dataset.accept.split(',');
    const queue = [];
    let busy = false;
    let total = 0;
    let finished = 0;
    let okCount = 0;

    const duration = (file) => new Promise((resolve) => {
      const a = new Audio();
      const url = URL.createObjectURL(file);
      let done = false;
      const end = (ms) => { if (done) return; done = true; URL.revokeObjectURL(url); resolve(ms); };
      a.preload = 'metadata';
      a.onloadedmetadata = () => end(Number.isFinite(a.duration) ? Math.round(a.duration * 1000) : 0);
      a.onerror = () => end(0);
      setTimeout(() => end(0), 15000);
      a.src = url;
    });

    const friendly = (msg) => msg || 'O envio falhou. Verifique a internet e tente de novo.';

    const send = async (file, li) => {
      const bar = $('.bar i', li);
      const st = $('.st', li);
      st.textContent = 'medindo…';
      const ms = await duration(file);
      const id = [...crypto.getRandomValues(new Uint8Array(16))].map((b) => b.toString(16).padStart(2, '0')).join('');
      const parts = Math.max(1, Math.ceil(file.size / CHUNK));
      for (let i = 0; i < parts; i++) {
        const fd = new FormData();
        fd.append('chunk', file.slice(i * CHUNK, (i + 1) * CHUNK), 'chunk');
        Object.entries({ upload_id: id, index: i, total: parts, size: file.size, filename: file.name, folder_id: zone.dataset.folder, duration_ms: ms })
          .forEach(([k, v]) => fd.append(k, String(v)));
        let res = null;
        for (let attempt = 0; attempt < 3 && !res; attempt++) {
          try {
            res = await fetch(`${base}/api/upload`, { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf, Accept: 'application/json' } }).then((r) => r.json());
          } catch (e) { await new Promise((r) => setTimeout(r, 1000 * (attempt + 1))); }
        }
        if (!res) throw new Error('A conexão caiu durante o envio. Clique em "Tentar de novo".');
        if (!res.ok) throw new Error(friendly(res.error));
        const pct = Math.round(((i + 1) / parts) * 100);
        bar.style.width = `${pct}%`;
        st.textContent = `${pct}%`;
      }
      st.textContent = 'Enviado ✓';
      li.className = 'ok';
    };

    const renderSummary = () => {
      summary.hidden = total === 0;
      const errors = $$('li.err', list).length;
      $('[data-sum-text]', summary).textContent = busy
        ? `Enviando ${Math.min(finished + 1, total)} de ${total}…`
        : `${okCount} de ${total} arquivo(s) enviado(s)${errors ? ` · ${errors} com problema` : ''}.`;
      $('.bar i', summary).style.width = `${total ? Math.round((finished / total) * 100) : 0}%`;
    };

    const pump = async () => {
      if (busy) return;
      busy = true;
      renderSummary();
      while (queue.length) {
        const [file, li] = queue.shift();
        try { await send(file, li); okCount++; } catch (e) {
          li.className = 'err';
          $('.st', li).textContent = e.message;
          const retry = document.createElement('button');
          retry.type = 'button';
          retry.className = 'btn btn-small';
          retry.textContent = 'Tentar de novo';
          retry.addEventListener('click', () => { retry.remove(); li.className = ''; $('.st', li).textContent = 'na fila'; finished--; queue.push([file, li]); pump(); });
          li.append(retry);
        }
        finished++;
        renderSummary();
      }
      busy = false;
      renderSummary();
      // Final do fluxo: diz o que aconteceu e o próximo passo (a página mostra o resumo).
      if (okCount && !$$('li.err', list).length) location.href = `${base}/biblioteca?pasta=${zone.dataset.folder}&enviados=${okCount}`;
    };

    const add = (files) => {
      for (const file of files) {
        const li = document.createElement('li');
        li.innerHTML = '<b></b><div class="bar"><i></i></div><span class="st">na fila</span>';
        $('b', li).textContent = file.name;
        list.append(li);
        const ext = `.${file.name.split('.').pop().toLowerCase()}`;
        total++;
        if (!ACCEPT.includes(ext)) {
          li.className = 'err';
          $('.st', li).textContent = ext === '.wma' ? 'WMA não toca em navegadores. Converta para MP3 e envie de novo.' : `Formato ${ext} não aceito. Use MP3, OGG, WAV, FLAC, M4A ou WEBM.`;
          finished++;
          continue;
        }
        queue.push([file, li]);
      }
      renderSummary();
      pump();
    };
    input.addEventListener('change', () => { add(input.files); input.value = ''; });
    $('#pickfiles')?.addEventListener('click', () => input.click());
    ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('over'); }));
    ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, () => zone.classList.remove('over')));
    zone.addEventListener('drop', (e) => { e.preventDefault(); add(e.dataTransfer.files); });
    window.addEventListener('beforeunload', (e) => { if (busy) { e.preventDefault(); e.returnValue = ''; } });
  }

  /* ---------- Formulário de eventos ---------- */
  const ef = $('#eventform');
  if (ef) {
    const type = $('#item_type');
    const startDate = ef.elements.start_date;
    const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    const sync = () => {
      $$('[data-for]', ef).forEach((el) => {
        const on = el.dataset.for.split(' ').includes(type.value);
        el.hidden = !on;
        $$('input,select,textarea', el).forEach((f) => { f.disabled = !on; });
      });
      const per = $('input[name=periodicity]:checked', ef)?.value;
      $('#hoursbox').hidden = per !== 'hours';
      // "Só uma vez" no passado nunca tocaria: bloqueia datas passadas.
      if (per === 'once') startDate.min = today; else startDate.removeAttribute('min');
      const exp = ef.elements.expires.checked;
      $('#expbox').hidden = !exp;
      $$('#expbox input').forEach((f) => { f.disabled = !exp; f.min = startDate.value; });
      const hoursChecked = $$('input[name="hours[]"]:checked', ef).length;
      const hb = $('#hours-err');
      if (hb) hb.hidden = !(per === 'hours' && hoursChecked === 0 && ef.dataset.tried);
    };
    ef.addEventListener('change', sync);
    ef.addEventListener('submit', (e) => {
      ef.dataset.tried = '1';
      if ($('input[name=periodicity]:checked', ef)?.value === 'hours' && !$$('input[name="hours[]"]:checked', ef).length) {
        e.preventDefault(); sync(); $('#hoursbox input').focus();
      }
    });
    sync();
  }

  /* ---------- Registro: campo de busca só no modo "um arquivo"; data final ≥ inicial ---------- */
  const lf = $('#logform');
  if (lf) {
    const sync = () => {
      const isFile = lf.elements.modo.value === 'arquivo';
      $('#qfield').hidden = !isFile;
      lf.elements.q.disabled = !isFile;
      lf.elements.q.required = isFile;
      lf.elements.ate.min = lf.elements.de.value;
    };
    lf.addEventListener('change', sync);
    sync();
  }
})();
