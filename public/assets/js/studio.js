// Estúdio de automação: lista de execução, eventos, vinhetas, players auxiliares e transmissão.
import { Engine, Deck, Ticker, Broadcaster } from './audio.js';

const $ = (s, el = document) => el.querySelector(s);
const $$ = (s, el = document) => [...el.querySelectorAll(s)];
const BASE = $('meta[name="base"]').content;
let CSRF = $('meta[name="csrf"]').content;

/* ======================================================================
 * Utilidades
 * ==================================================================== */

async function api(path, { method = 'GET', body } = {}) {
  const headers = { Accept: 'application/json', 'X-CSRF-Token': CSRF };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  let res;
  try {
    res = await fetch(BASE + path, { method, headers, body: body !== undefined ? JSON.stringify(body) : undefined, credentials: 'same-origin' });
  } catch (e) {
    return { ok: false, error: 'Sem conexão com o servidor.' };
  }
  if (res.status === 401) { location.href = `${BASE}/entrar`; return { ok: false }; }
  try { return await res.json(); } catch (e) { return { ok: false, error: `Erro ${res.status}` }; }
}

const pad = (n, l = 2) => String(n).padStart(l, '0');
function fmt(ms) {
  if (!Number.isFinite(ms) || ms <= 0) return '—';
  const s = Math.round(ms / 1000);
  const h = Math.floor(s / 3600);
  return h ? `${h}:${pad(Math.floor((s % 3600) / 60))}:${pad(s % 60)}` : `${Math.floor(s / 60)}:${pad(s % 60)}`;
}
const clone = (o) => JSON.parse(JSON.stringify(o));
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const withTimeout = (p, ms, msg = 'Tempo esgotado') => Promise.race([p, new Promise((_, rej) => setTimeout(() => rej(new Error(msg)), ms))]);
const randHex = (n) => [...crypto.getRandomValues(new Uint8Array(n))].map((b) => b.toString(16).padStart(2, '0')).join('');

function el(tag, attrs = {}, ...children) {
  const e = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (v === undefined || v === null || v === false) continue;
    if (k === 'class') e.className = v;
    else if (k === 'text') e.textContent = v;
    else if (k.startsWith('on')) e.addEventListener(k.slice(2), v);
    else e.setAttribute(k, v === true ? '' : v);
  }
  for (const c of children.flat()) if (c !== null && c !== undefined) e.append(c instanceof Node ? c : document.createTextNode(String(c)));
  return e;
}

function toast(msg, type = 'info') {
  const t = el('div', { class: `toast ${type}`, text: msg });
  $('#toasts').append(t);
  setTimeout(() => t.remove(), type === 'error' ? 9000 : 4500);
}

/* ======================================================================
 * Estado global
 * ==================================================================== */

const S = {};           // dados vindos de /api/bootstrap
let engine;             // motor de áudio principal
let ticker;
let main;               // player principal
let scheduler;
let jingles;
let broadcaster;
let isLeader = false;
let skew = 0;           // diferença entre o relógio do servidor e o do computador
// sessionStorage sobrevive ao recarregar a aba: recarregar não faz a aba perder o posto de estúdio principal.
const TAB_TOKEN = (() => {
  try {
    const t = sessionStorage.getItem('ondaria.tab') || randHex(16);
    sessionStorage.setItem('ondaria.tab', t);
    return t;
  } catch (e) { return randHex(16); }
})();
const auxPanels = new Map();
const store = {
  get(k, d) { try { return JSON.parse(localStorage.getItem(`ondaria.${S.station.id}.${k}`)) ?? d; } catch (e) { return d; } },
  set(k, v) { try { localStorage.setItem(`ondaria.${S.station.id}.${k}`, JSON.stringify(v)); } catch (e) { /* armazenamento indisponível */ } },
};
let devices = { main: '', cue: '', aux: ['', '', '', ''], mic: '', sat: '' };

/** Hora "de parede" no fuso da rádio, como milissegundos em UTC (facilita as contas de agenda). */
let fmtParts;
function stationNow() {
  const parts = Object.fromEntries(fmtParts.formatToParts(new Date(Date.now() + skew)).map((p) => [p.type, p.value]));
  const y = +parts.year; const mo = +parts.month; const d = +parts.day;
  const h = +parts.hour % 24; const mi = +parts.minute; const s = +parts.second;
  return { ms: Date.UTC(y, mo - 1, d, h, mi, s), y, mo, d, h, mi, s, str: `${y}-${pad(mo)}-${pad(d)} ${pad(h)}:${pad(mi)}:${pad(s)}` };
}
const wallStr = (ms) => { const d = new Date(ms); return `${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}:${pad(d.getUTCSeconds())}`; };
function parseWall(str) {
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/.exec(str || '');
  return m ? Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6]) : null;
}

/* ======================================================================
 * Registro de execução (enviado em lotes)
 * ==================================================================== */

const logQueue = [];
function log(source, kind, title, { media_id = null, status = 'ok', detail = null } = {}) {
  logQueue.push({ at: stationNow().str, source, kind, title: String(title).slice(0, 250), media_id, status, detail });
  if (status === 'error') toast(`${title}: ${detail}`, 'error');
}
async function flushLog() {
  if (!logQueue.length) return;
  const entries = logQueue.splice(0, 200);
  const r = await api('/api/log', { method: 'POST', body: { entries } });
  if (!r.ok) logQueue.unshift(...entries);
}

/* ======================================================================
 * Itens
 * ==================================================================== */

function titleOf(it) {
  if (!it) return '—';
  if (it.type !== 'track') return it.title || S.item_types[it.type] || it.type;
  if (S.settings.show_full_name) return it.filename || it.title;
  if (!it.artist) return it.title || it.filename;
  return (S.settings.tags_format || '%artist% - %title%').replace('%artist%', it.artist).replace('%title%', it.title || '');
}
const mediaUrl = (id) => `${BASE}/media/${id}`;
function itemMs(it) {
  if (it.type === 'track') return it.duration_ms || 0;
  if (['pause', 'stream', 'satellite'].includes(it.type)) return (it.seconds || 0) * 1000;
  return 0;
}
async function fetchPlaylist(id) {
  const r = await api(`/api/playlists/${id}`);
  return r.ok ? r.playlist : null;
}
const durationFixed = new Set();

/* ======================================================================
 * Player (principal e auxiliares)
 * ==================================================================== */

class Player {
  constructor({ name, engine: eng, isMain = false, source = 'lista' }) {
    this.name = name;
    this.engine = eng;
    this.isMain = isMain;
    this.source = source;
    this.items = [];
    this.cur = -1;
    this.next = 0;
    this.sel = new Set();
    this.mode = 'normal';
    this.autodelete = false;
    this.loop = false;
    this.stopAfter = false;
    this.sweepersOn = true;
    this.state = 'stopped';
    this.token = 0;
    this.decks = new Set();
    this.display = null;
    this.playingEvent = null;
    this.satByItem = false;
    this.reshuffle = false;
    this.meta = { id: null, name: 'Sem título', kind: 'list' };
    this.dirty = false;
    this.listeners = new Set();
  }

  onChange(fn) { this.listeners.add(fn); }
  changed(structure = true) { for (const fn of this.listeners) fn(structure); }

  /* ----- edição da lista ----- */

  mutate(fn) {
    const curItem = this.items[this.cur];
    const nextItem = this.items[this.next];
    const nextIdx = this.next;
    fn();
    this.cur = curItem ? this.items.indexOf(curItem) : -1;
    this.next = nextItem ? this.items.indexOf(nextItem) : -1;
    if (this.next < 0) this.next = Math.min(Math.max(nextIdx, 0), Math.max(this.items.length - 1, 0));
    for (const s of [...this.sel]) if (!this.items.includes(s)) this.sel.delete(s);
    this.dirty = true;
    this.changed();
  }

  insert(items, at = null) {
    const pos = at ?? this.insertPos();
    this.mutate(() => this.items.splice(pos, 0, ...items.map(clone)));
    this.sel = new Set(this.items.slice(pos, pos + items.length));
    this.changed();
  }

  /** Abaixo do selecionado ou no fim da lista. */
  insertPos() {
    const idx = [...this.sel].map((s) => this.items.indexOf(s)).filter((i) => i >= 0);
    return idx.length ? Math.max(...idx) + 1 : this.items.length;
  }

  removeSelected() {
    if (!this.sel.size) return;
    this.mutate(() => { this.items = this.items.filter((i) => !this.sel.has(i)); });
  }

  moveTo(itemsToMove, at) {
    this.mutate(() => {
      const before = this.items.slice(0, at).filter((i) => !itemsToMove.includes(i));
      const after = this.items.slice(at).filter((i) => !itemsToMove.includes(i));
      this.items = [...before, ...itemsToMove, ...after];
    });
  }

  shuffle() {
    this.mutate(() => {
      for (let i = this.items.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [this.items[i], this.items[j]] = [this.items[j], this.items[i]];
      }
    });
  }

  loadList(p) {
    this.items = (p.items || []).map(clone);
    this.meta = { id: p.id ?? null, name: p.name || 'Sem título', kind: p.kind || 'list' };
    this.cur = -1;
    this.next = 0;
    this.sel.clear();
    this.dirty = false;
    this.changed();
  }

  markNext(i) { this.next = i; this.changed(false); }

  /* ----- transporte ----- */

  async play() {
    await this.engine.resume();
    if (this.state === 'paused') {
      for (const d of this.decks) d.el?.play().catch(() => {});
      this.state = 'playing';
      this.changed(false);
      return;
    }
    if (this.state === 'playing' && this.decks.size) return;
    this.state = 'playing';
    this.advance();
  }

  pause() {
    if (this.state !== 'playing') return;
    for (const d of this.decks) d.pause?.();
    this.state = 'paused';
    this.changed(false);
  }

  /** Para tudo. keepTails = deixa terminar o que já está saindo (comando "parar" na lista). */
  stop({ fade = S.settings.fade_on_stop, keepTails = false } = {}) {
    this.token++;
    if (!keepTails) {
      for (const d of this.decks) d.stop(fade ? S.settings.fade_stop_ms : 0);
      this.decks.clear();
    }
    if (this.satByItem) { this.engine.setInputOpen('sat', false); this.satByItem = false; }
    this.state = 'stopped';
    this.cur = -1;
    this.playingEvent = null;
    this.stopAfter = false;
    this.display = null;
    this.changed(false);
  }

  /** Botão "próxima": corta a atual e toca a marcada em verde. */
  skip() {
    this.token++;
    for (const d of this.decks) d.stop(S.settings.fade_on_stop ? Math.min(800, S.settings.fade_stop_ms) : 0);
    this.decks.clear();
    this.playingEvent = null;
    this.state = 'playing';
    this.advance(true);
  }

  playNow(i) { this.next = i; this.skip(); }

  seek(delta) {
    const d = this.display?.deck;
    if (d && !d.disposed) d.seek(d.time + delta);
  }

  /** Decide o que tocar a seguir: eventos pendentes, parar após a atual, ou o item verde. */
  advance(skipEvents = false) {
    if (this.state !== 'playing') return;
    if (this.isMain && !skipEvents && scheduler?.pending.length) {
      this.runEvent(scheduler.pending.shift(), false);
      return;
    }
    if (this.stopAfter) { this.stop({ keepTails: true }); return; }
    if (this.reshuffle) {
      this.reshuffle = false;
      this.shuffle();
      this.next = 0;
    }
    const i = this.next;
    if (i < 0 || i >= this.items.length) { this.stop({ keepTails: true }); return; }
    this.cur = i;
    this.computeNext();
    const item = this.items[i];
    this.changed(false);
    this.runItem(item, this.source, () => this.itemDone(item));
  }

  computeNext() {
    const n = this.items.length;
    if (!n) { this.next = 0; return; }
    if (this.loop) { this.next = this.cur; return; }
    switch (this.mode) {
      case 'repeat': this.next = (this.cur + 1) % n; break;
      case 'shuffle':
        if (this.cur + 1 >= n) { this.reshuffle = true; this.next = 0; } else this.next = this.cur + 1;
        break;
      case 'random': {
        let r = Math.floor(Math.random() * n);
        if (n > 1 && r === this.cur) r = (r + 1) % n;
        this.next = r;
        break;
      }
      default: this.next = this.cur + 1 < n ? this.cur + 1 : n;
    }
  }

  itemDone(item) {
    if (this.autodelete && !this.loop) {
      const i = this.items.indexOf(item);
      if (i >= 0) this.mutate(() => this.items.splice(i, 1));
    }
    if (this.mode === 'manual') {
      this.state = 'stopped';
      this.cur = -1;
      this.changed(false);
      return;
    }
    this.advance();
  }

  async runItem(item, source, done) {
    const token = ++this.token;
    let r;
    try { r = await this.exec(item, token, source); } catch (e) { console.error(e); log(source, item.type, titleOf(item), { status: 'error', detail: e.message }); }
    if (token === this.token && r !== 'stop' && r !== 'jump') done();
  }

  /** Executa o evento (imediato interrompe o que estiver tocando). */
  async runEvent(p, interrupt) {
    const token = ++this.token;
    if (interrupt) {
      for (const d of this.decks) d.stop(Math.min(1000, S.settings.fade_stop_ms || 0));
      this.decks.clear();
    }
    this.state = 'playing';
    this.playingEvent = p;
    this.cur = -1;
    scheduler.render(true);
    this.changed(false);
    log('evento', 'evento', p.ev.name);
    let r;
    try { r = await this.exec(p.ev.item, token, 'evento'); } catch (e) { log('evento', 'evento', p.ev.name, { status: 'error', detail: e.message }); }
    if (token === this.token) {
      this.playingEvent = null;
      scheduler.render(true);
      if (r !== 'stop' && r !== 'jump') this.advance();
    }
  }

  async exec(item, token, source) {
    const alive = () => token === this.token;
    const err = (detail) => log(source, item.type, titleOf(item), { status: 'error', detail });
    switch (item.type) {
      case 'track': return this.playTrack(item, token, source);
      case 'time': case 'temperature': case 'humidity': return this.playTth(item.type, token, source);
      case 'random': {
        const r = await api(`/api/random/${item.folder_id}`);
        if (!alive()) return 'jump';
        if (!r.ok) return err(r.error);
        return this.playTrack(r.item, token, source);
      }
      case 'pause':
        log(source, 'pause', item.title);
        this.setDisplay(item.title, item.seconds * 1000);
        return this.wait(item.seconds * 1000, token);
      case 'stream': return this.playStream(item, token, source);
      case 'satellite': return this.satellite(item.seconds * 1000, token, source);
      case 'satcon': case 'satdis': {
        const on = item.type === 'satcon';
        if (on && !(await this.engine.openInput('sat', devices.sat))) return err('Entrada do satélite indisponível (verifique Dispositivos).');
        this.engine.setInputOpen('sat', on, 300);
        setSatButton(on);
        log(source, item.type, titleOf(item));
        return undefined;
      }
      case 'postit': showPostit(item.text); log(source, 'postit', item.title); return undefined;
      case 'stop': log(source, 'stop', 'Comando parar'); this.stop({ keepTails: true }); return 'stop';
      case 'play': return undefined;
      case 'playlist': {
        const p = await fetchPlaylist(item.playlist_id);
        if (!alive()) return 'jump';
        if (!p) return err('Lista não encontrada.');
        log(source, 'playlist', item.title);
        this.loadList(p);
        if (this.isMain) persistMainList(true);
        this.advance(true);
        return 'jump';
      }
      case 'rotation': {
        const r = await api(`/api/playlists/${item.playlist_id}/rotation-next`, { method: 'POST', body: {} });
        if (!alive()) return 'jump';
        if (!r.ok) return err(r.error);
        return this.exec(r.item, token, source);
      }
      case 'sequence': {
        const p = await fetchPlaylist(item.playlist_id);
        if (!p) return err('Sequência não encontrada.');
        for (const it of p.items) {
          if (!alive()) return 'jump';
          const r = await this.exec(it, token, source);
          if (r === 'stop' || r === 'jump') return r;
        }
        return undefined;
      }
      case 'events':
        await scheduler.loadSet(item.event_set_id);
        log(source, 'events', item.title);
        return undefined;
      default: return undefined;
    }
  }

  setDisplay(title, durMs, deck = null, waitState = null) {
    this.display = { title, durMs, deck, waitState, started: performance.now() };
  }

  playTrack(item, token, source) {
    return this.playAudio({ url: mediaUrl(item.media_id), title: titleOf(item), item }, token, { source });
  }

  async playTth(kind, token, source) {
    const t = stationNow();
    const r = await api(`/api/tth/${kind}?h=${t.h}&m=${t.mi}`);
    if (token !== this.token) return 'jump';
    if (!r.ok) { log(source, kind, S.item_types[kind], { status: 'error', detail: r.error }); return undefined; }
    log(source, kind, r.label);
    for (let k = 0; k < r.items.length; k++) {
      if (token !== this.token) return 'jump';
      const last = k === r.items.length - 1;
      await this.playAudio({ url: mediaUrl(r.items[k].media_id), title: r.label, item: r.items[k] }, token, { source, overlap: last ? null : 0, logIt: false });
    }
    return undefined;
  }

  /**
   * Toca um áudio e resolve no ponto de sobreposição (ou no fim). O deck continua tocando
   * a "cauda" enquanto o próximo item começa — é assim que o crossfade acontece.
   */
  playAudio(part, token, { source, overlap = null, logIt = true }) {
    return new Promise((resolve) => {
      const deck = new Deck(this.engine, this.engine.programBus);
      this.decks.add(deck);
      let triggered = false;
      let unsub = () => {};
      const finish = () => { if (!triggered) { triggered = true; resolve(); } };
      const cleanup = () => { unsub(); deck.stop(); this.decks.delete(deck); };
      const media = part.item?.media_id || null;

      (async () => {
        try {
          await withTimeout(deck.load(part.url), 20000, 'Arquivo demorou demais para carregar');
          if (token !== this.token) { cleanup(); finish(); return; }
          await deck.play();
          if (this.state === 'paused') deck.pause();
        } catch (e) {
          log(source, 'track', part.title, { media_id: media, status: 'error', detail: e.message || 'Falha ao tocar' });
          cleanup();
          finish();
          return;
        }
        this.setDisplay(part.title, deck.duration * 1000, deck);
        if (logIt) log(source, part.item?.type || 'track', part.title, { media_id: media });
        this.onStart(part, deck);
        this.changed(false);

        // A duração medida pelo navegador corrige a da biblioteca ("Atualizar duração").
        const realMs = Math.round(deck.duration * 1000);
        if (media && realMs > 0 && Math.abs(realMs - (part.item.duration_ms || 0)) > 1500 && !durationFixed.has(media)) {
          durationFixed.add(media);
          part.item.duration_ms = realMs;
          for (const p of allPlayers()) for (const it of p.items) if (it.media_id === media) it.duration_ms = realMs;
          api(`/api/media/${media}/duration`, { method: 'POST', body: { duration_ms: realMs } });
          this.changed();
        }

        const ov = Math.max(0, overlap ?? (part.item?.overlap_s ?? S.settings.overlap_s));
        const sweeps = this.isMain ? (S.sweepers[media] || []).map((s) => ({ ...s, done: false })) : [];
        let silentSince = null;
        let quietSince = null;
        unsub = ticker.on(() => {
          if (deck.disposed) { unsub(); this.decks.delete(deck); finish(); return; }
          if (deck.paused) return;
          const d = deck.duration;
          const t = deck.time;
          if (this.sweepersOn) {
            for (const s of sweeps) if (!s.done && t >= s.offset_s) { s.done = true; jingles.start(`sw${randHex(4)}`, s.item, 'sweeper', 'Sweeper'); }
          }
          if (triggered || !d) return;
          const remaining = d - t;
          const now = performance.now();
          const db = deck.rmsDb();
          if (remaining <= ov) {
            // Detector de fim: dentro da janela de sobreposição, começa a próxima quando o volume cai.
            const quiet = db < S.settings.end_threshold_db;
            quietSince = quiet ? (quietSince ?? now) : null;
            if (!S.settings.end_detect || (quietSince && now - quietSince > 250)) {
              if (S.settings.fade_on_overlap && remaining > 0.05) deck.fadeTo(0, remaining * 1000);
              finish();
            }
          } else if (S.settings.silence_detect && t > 2) {
            silentSince = db < S.settings.silence_db ? (silentSince ?? now) : null;
            if (silentSince && now - silentSince > S.settings.silence_s * 1000) {
              log(source, 'track', part.title, { media_id: media, status: 'error', detail: 'Silêncio detectado; faixa pulada' });
              deck.stop(300);
              finish();
            }
          }
        });
        deck.el.addEventListener('ended', () => { cleanup(); finish(); }, { once: true });
      })();
    });
  }

  wait(ms, token) {
    return new Promise((resolve) => {
      const ws = { elapsed: 0 };
      if (this.display) this.display.waitState = ws;
      let last = performance.now();
      const unsub = ticker.on(() => {
        const now = performance.now();
        if (this.state === 'playing') ws.elapsed += now - last;
        last = now;
        if (token !== this.token || (ms > 0 && ws.elapsed >= ms)) { unsub(); resolve(); }
      });
    });
  }

  async playStream(item, token, source) {
    let handle = null;
    let deck = new Deck(this.engine, this.engine.programBus, { crossOrigin: true });
    try {
      await withTimeout(deck.load(item.url), 15000, 'A rádio online não respondeu');
      await deck.play();
      this.decks.add(deck);
      handle = deck;
    } catch (e) {
      deck.stop();
      deck = null;
      // Sem CORS o áudio não entra no mixer; toca direto na saída escolhida.
      const a = new Audio(item.url);
      try {
        if (devices.main && a.setSinkId) await a.setSinkId(devices.main);
        await withTimeout(a.play(), 15000, 'A rádio online não respondeu');
        handle = { el: a, pause: () => a.pause(), stop: () => { a.pause(); a.removeAttribute('src'); a.load(); }, disposed: false };
        this.decks.add(handle);
        toast('Esta rádio online não libera CORS: ela toca, mas fora dos medidores e da transmissão.', 'warn');
      } catch (e2) {
        log(source, 'stream', item.title, { status: 'error', detail: e2.message });
        return undefined;
      }
    }
    if (token !== this.token) { handle.stop(); this.decks.delete(handle); return 'jump'; }
    log(source, 'stream', item.title);
    this.setDisplay(item.title, item.seconds * 1000);
    this.onStart({ title: item.title, item }, null);
    await this.wait(item.seconds * 1000, token);
    if (this.decks.has(handle)) { handle.stop(1500); this.decks.delete(handle); }
    return undefined;
  }

  async satellite(ms, token, source) {
    if (!(await this.engine.openInput('sat', devices.sat))) {
      log(source, 'satellite', 'Satélite', { status: 'error', detail: 'Entrada do satélite indisponível (verifique Dispositivos).' });
      return undefined;
    }
    this.engine.setInputOpen('sat', true, 300);
    this.satByItem = true;
    setSatButton(true);
    log(source, 'satellite', `Satélite ${fmt(ms)}`);
    this.setDisplay('Satélite', ms);
    await this.wait(ms, token);
    if (this.satByItem) { this.engine.setInputOpen('sat', false, 300); this.satByItem = false; setSatButton(false); }
    return undefined;
  }

  onStart(part, deck) {
    if (!this.isMain) return;
    const it = part.item || {};
    const next = this.items[this.next];
    api('/api/nowplaying', { method: 'POST', body: { title: it.title || part.title, artist: it.artist || '', duration_ms: deck ? Math.round(deck.duration * 1000) : (it.seconds || 0) * 1000, next: next ? titleOf(next) : '' } });
    broadcaster?.metadata(it.title || part.title, it.artist || '');
  }

  toJSON() { return { meta: this.meta, items: this.items, mode: this.mode, dirty: this.dirty }; }
}

function allPlayers() { return [main, ...[...auxPanels.values()].map((a) => a.player)].filter(Boolean); }

/* ======================================================================
 * Eventos agendados
 * ==================================================================== */

class Scheduler {
  constructor() {
    this.enabled = true;
    this.pending = [];
    this.fired = new Set();
    this.lastCheck = null;
    this.lastRender = '';
  }

  occurrences(ev, from, to) {
    const start = parseWall(ev.start_at);
    if (start === null) return [];
    const exp = ev.expires_at ? parseWall(ev.expires_at) : null;
    const sd = new Date(start);
    const hms = (h) => h * 3600000 + sd.getUTCMinutes() * 60000 + sd.getUTCSeconds() * 1000;
    const out = [];
    const day0 = Math.floor(from / 86400000) * 86400000;
    for (let day = day0; day <= to; day += 86400000) {
      if (!(ev.weekdays_mask & (1 << new Date(day).getUTCDay()))) continue;
      let times = [];
      if (ev.periodicity === 'once') times = day === Math.floor(start / 86400000) * 86400000 ? [start] : [];
      else if (ev.periodicity === 'daily') times = [day + hms(sd.getUTCHours())];
      else for (let h = 0; h < 24; h++) if (ev.periodicity === 'hourly' || (ev.hours_mask & (1 << h))) times.push(day + hms(h));
      for (const t of times) if (t >= start && t > from && t <= to && (exp === null || t <= exp)) out.push(t);
    }
    return out;
  }

  async loadSet(id) {
    const r = await api(`/api/events?set=${id}`);
    if (!r.ok) { toast(r.error || 'Grade não encontrada', 'error'); return; }
    S.events = r.events;
    S.event_set_id = id;
    api('/api/events/active', { method: 'POST', body: { event_set_id: id } });
    this.render(true);
  }

  tick() {
    const now = stationNow().ms;
    if (this.lastCheck === null || now - this.lastCheck > 6 * 3600000 || now < this.lastCheck) this.lastCheck = now - 1000;
    const prio = (e) => (e.priority === 'high' ? 1 : 0);
    if (this.enabled && isLeader) {
      for (const ev of S.events) {
        if (!ev.enabled) continue;
        for (const at of this.occurrences(ev, this.lastCheck, now)) {
          const key = `${ev.id}@${at}`;
          if (this.fired.has(key)) continue;
          this.fired.add(key);
          if (now - at > Math.max(10000, ev.max_wait_s * 1000)) { log('evento', 'evento', ev.name, { status: 'error', detail: 'Perdido (estúdio fechado ou computador suspenso)' }); continue; }
          this.fire(ev, at, prio);
        }
      }
      // Espera máxima: pendentes antigos saem da fila sem tocar.
      this.pending = this.pending.filter((p) => {
        if (now - p.at <= p.ev.max_wait_s * 1000) return true;
        log('evento', 'evento', p.ev.name, { status: 'error', detail: 'Descartado: passou da espera máxima' });
        return false;
      });
    }
    this.lastCheck = now;
    if (this.fired.size > 5000) this.fired = new Set([...this.fired].slice(-1000));
    this.render();
  }

  fire(ev, at, prio) {
    const p = { ev, at };
    const playing = main.playingEvent;
    if (playing) {
      if (prio(ev) < prio(playing.ev)) { log('evento', 'evento', ev.name, { status: 'error', detail: 'Descartado: prioridade menor que o evento no ar' }); return; }
      this.pending.push(p);
      return;
    }
    if (ev.wait_mode === 'immediate') {
      main.runEvent(p, true);
    } else {
      this.pending.push(p);
      // Com a lista tocando um "aguardar" sem áudio (nada no ar), o evento espera o fim do item atual.
    }
  }

  playPending() {
    if (!this.pending.length) { main.play(); return; }
    if (main.state === 'playing' && main.decks.size) { main.runEvent(this.pending.shift(), true); return; }
    main.state = 'playing';
    main.advance();
  }

  render(force = false) {
    const now = stationNow().ms;
    const rows = [];
    for (const p of this.pending) rows.push({ at: p.at, name: p.ev.name, cls: 'pending' });
    if (main?.playingEvent) rows.unshift({ at: main.playingEvent.at, name: main.playingEvent.ev.name, cls: 'onair' });
    if (this.enabled) {
      for (const ev of S.events) {
        if (!ev.enabled) continue;
        for (const at of this.occurrences(ev, now, now + 15 * 60000)) rows.push({ at, name: ev.name, cls: ev.wait_mode === 'immediate' ? 'imm' : '' });
      }
    }
    rows.sort((a, b) => a.at - b.at);
    const sig = JSON.stringify(rows) + this.enabled + isLeader;
    if (!force && sig === this.lastRender) return;
    this.lastRender = sig;
    const ul = $('#evlist');
    ul.replaceChildren(...rows.slice(0, 40).map((r) => el('li', { class: r.cls }, el('time', { text: wallStr(r.at) }), el('span', { text: r.name }))));
    if (!rows.length) ul.append(el('li', { class: 'empty', text: this.enabled ? 'Nenhum evento nos próximos 15 minutos.' : 'Eventos desativados.' }));
    if (!isLeader) ul.prepend(el('li', { class: 'warn', text: 'Estúdio secundário: os eventos são disparados no estúdio principal.' }));
    $('#evToggle').classList.toggle('on', this.enabled);
    $('#evToggle').textContent = this.enabled ? 'Ativos' : 'Inativos';
  }
}

/* ======================================================================
 * Vinhetas (10 páginas × 9 botões) e sweepers
 * ==================================================================== */

class Jingles {
  constructor() { this.page = store.get('jpage', 0); this.active = new Map(); }

  buttons() { return S.jingles.find((p) => p.page_no === this.page)?.buttons || []; }

  render() {
    const sel = $('#jpage');
    sel.replaceChildren(...S.jingles.map((p) => new Option(`${p.page_no + 1}. ${p.name}`, p.page_no, false, p.page_no === this.page)));
    const box = $('#jbuttons');
    box.replaceChildren();
    for (let slot = 0; slot < 9; slot++) {
      const b = this.buttons().find((x) => x.slot === slot);
      const key = `${this.page}:${slot}`;
      const btn = el('button', { class: `jb ${b ? '' : 'empty'} ${this.active.has(key) ? 'playing' : ''} ${b?.mode || ''}`, title: b ? `${titleOf(b.item)} (${b.mode})` : 'Arraste um arquivo da biblioteca para cá' },
        el('kbd', { text: slot + 1 }), el('span', { text: b?.label || '' }));
      btn.addEventListener('click', () => this.press(slot));
      btn.addEventListener('dragover', (e) => { if (drag?.items) { e.preventDefault(); btn.classList.add('over'); } });
      btn.addEventListener('dragleave', () => btn.classList.remove('over'));
      btn.addEventListener('drop', (e) => { e.preventDefault(); btn.classList.remove('over'); this.assign(slot, drag?.items?.[0]); });
      box.append(btn);
    }
  }

  async assign(slot, item) {
    if (!item || !['track', 'time', 'temperature', 'humidity'].includes(item.type)) return;
    const page = S.jingles.find((p) => p.page_no === this.page);
    const old = page.buttons.find((x) => x.slot === slot);
    page.buttons = page.buttons.filter((x) => x.slot !== slot);
    page.buttons.push({ slot, label: (item.title || '').slice(0, 24), mode: old?.mode || 'normal', item: clone(item) });
    this.render();
    if (S.user.manager) {
      const r = await api('/api/jingles', { method: 'POST', body: { page_no: this.page, buttons: page.buttons } });
      if (!r.ok) toast(r.error || 'Não foi possível salvar a vinheta.', 'error');
    } else {
      toast('Vinheta trocada só nesta sessão (apenas administradores salvam).');
    }
  }

  press(slot) {
    const key = `${this.page}:${slot}`;
    if (this.active.has(key)) { this.active.get(key).stop(); return; }
    const b = this.buttons().find((x) => x.slot === slot);
    if (b) this.start(key, b.item, b.mode, b.label);
  }

  /** mode: normal | loop | sweeper (abaixa a programação enquanto toca). */
  async start(key, item, mode, label) {
    await engine.resume();
    const duckKey = `j:${key}`;
    const handle = { stopped: false, deck: null, wake: null };
    const end = () => {
      if (this.active.get(key) === handle) this.active.delete(key);
      engine.setDuck(duckKey, null, 400);
      this.render();
    };
    handle.stop = () => { handle.stopped = true; handle.deck?.stop(150); handle.wake?.(); end(); };
    this.active.set(key, handle);
    this.render();
    if (mode === 'sweeper' || (mode === 'normal' && S.settings.jingle_duck)) engine.setDuck(duckKey, S.settings.sweeper_duck_level, 250);

    let parts = [];
    if (item.type === 'track') parts = [{ url: mediaUrl(item.media_id), media_id: item.media_id }];
    else {
      const t = stationNow();
      const r = await api(`/api/tth/${item.type}?h=${t.h}&m=${t.mi}`);
      if (!r.ok) { log('vinheta', item.type, label, { status: 'error', detail: r.error }); end(); return; }
      parts = r.items.map((i) => ({ url: mediaUrl(i.media_id), media_id: i.media_id }));
    }
    log(mode === 'sweeper' && key.startsWith('sw') ? 'sweeper' : 'vinheta', item.type, label || titleOf(item), { media_id: item.media_id || null });
    for (const p of parts) {
      if (handle.stopped) return;
      const d = new Deck(engine, engine.jingleBus);
      handle.deck = d;
      try {
        await d.load(p.url);
        d.el.loop = mode === 'loop' && parts.length === 1;
        await d.play();
        await new Promise((resolve) => {
          handle.wake = resolve;
          d.el.addEventListener('ended', resolve, { once: true });
          d.el.addEventListener('error', resolve, { once: true });
        });
      } catch (e) {
        log('vinheta', item.type, label, { media_id: p.media_id, status: 'error', detail: e.message });
      }
      d.stop();
    }
    if (!handle.stopped) end();
  }
}

/* ======================================================================
 * Visualização da lista (principal e auxiliares)
 * ==================================================================== */

let drag = null;   // { items } vindo da biblioteca, ou { player, items } vindo de uma lista

class ListView {
  constructor(player, table, { info = null, compact = false } = {}) {
    this.p = player;
    this.tbody = table.tBodies[0];
    this.info = info;
    this.compact = compact;
    this.anchor = null;
    this.filter = '';
    player.onChange((structure) => (structure ? this.render() : this.paint()));
    this.bind();
  }

  render() {
    const p = this.p;
    const frag = document.createDocumentFragment();
    p.items.forEach((it, i) => {
      frag.append(el('tr', { 'data-i': i, draggable: 'true', class: it.type !== 'track' ? 'cmd' : '' },
        el('td', { class: 'n', text: i + 1 }), el('td', { class: 't', text: titleOf(it) }), el('td', { class: 'd', text: fmt(itemMs(it)) })));
    });
    this.tbody.replaceChildren(frag);
    if (!p.items.length) this.tbody.append(el('tr', { class: 'empty' }, el('td', { colspan: 3, text: 'Lista vazia. Arraste arquivos da biblioteca para cá.' })));
    this.paint();
  }

  /** Só cores e seleção (barato, roda a cada mudança de estado). */
  paint() {
    const p = this.p;
    const q = this.filter.toLowerCase();
    for (const tr of this.tbody.rows) {
      const i = +tr.dataset.i;
      if (Number.isNaN(i)) continue;
      const it = p.items[i];
      tr.classList.toggle('playing', i === p.cur && p.state !== 'stopped');
      tr.classList.toggle('next', i === p.next);
      tr.classList.toggle('sel', p.sel.has(it));
      tr.classList.toggle('match', !!q && titleOf(it).toLowerCase().includes(q));
    }
    if (this.info) {
      const total = p.items.reduce((a, it) => a + itemMs(it), 0);
      const unknown = p.items.filter((it) => !itemMs(it) && !['stop', 'play', 'postit', 'satcon', 'satdis', 'events'].includes(it.type)).length;
      const sel = [...p.sel].reduce((a, it) => a + itemMs(it), 0);
      this.info.textContent = `${p.items.length} itens · ${fmt(total)}${unknown ? ` (+${unknown} sem duração)` : ''}${p.sel.size > 1 ? ` · seleção ${fmt(sel)}` : ''}`;
    }
    if (p === main) {
      $('#listname').textContent = `${p.meta.kind === 'rotation' ? 'Rotação: ' : p.meta.kind === 'sequence' ? 'Sequência: ' : ''}${p.meta.name}`;
      $('#dirty').hidden = !p.dirty;
      persistMainList();
    } else {
      p.panel?.persist();
    }
  }

  rowIndex(target) {
    const tr = target.closest('tr');
    return tr && tr.dataset.i !== undefined ? +tr.dataset.i : -1;
  }

  bind() {
    const tb = this.tbody;
    const p = this.p;
    tb.addEventListener('click', (e) => {
      const i = this.rowIndex(e.target);
      if (i < 0) return;
      const it = p.items[i];
      if (e.shiftKey && this.anchor !== null) {
        const [a, b] = [Math.min(this.anchor, i), Math.max(this.anchor, i)];
        p.sel = new Set(p.items.slice(a, b + 1));
      } else if (e.ctrlKey || e.metaKey) {
        p.sel.has(it) ? p.sel.delete(it) : p.sel.add(it);
        this.anchor = i;
      } else {
        p.sel = new Set([it]);
        this.anchor = i;
      }
      activeView = this;
      this.paint();
    });
    tb.addEventListener('dblclick', (e) => {
      const i = this.rowIndex(e.target);
      if (i < 0) return;
      if (S.settings.dblclick_plays) p.playNow(i); else p.markNext(i);
    });
    tb.addEventListener('contextmenu', (e) => {
      const i = this.rowIndex(e.target);
      if (i < 0) return;
      e.preventDefault();
      activeView = this;
      if (!p.sel.has(p.items[i])) { p.sel = new Set([p.items[i]]); this.paint(); }
      contextMenu(e.clientX, e.clientY, [
        ['Marcar como próxima', () => p.markNext(i)],
        ['Tocar agora', () => p.playNow(i)],
        ['Pré-escuta (cue)', () => cue(p.items[i])],
        null,
        ['Copiar', () => copySel(p)],
        ['Colar abaixo', () => pasteInto(p)],
        ['Remover', () => p.removeSelected()],
        null,
        ['Duração da seleção', () => toast(`Seleção: ${fmt([...p.sel].reduce((a, it) => a + itemMs(it), 0))}`)],
        ['Atualizar duração', () => updateLengths(p)],
      ]);
    });
    tb.addEventListener('dragstart', (e) => {
      const i = this.rowIndex(e.target);
      if (i < 0) return;
      if (!p.sel.has(p.items[i])) { p.sel = new Set([p.items[i]]); this.paint(); }
      drag = { player: p, items: p.items.filter((it) => p.sel.has(it)) };
      e.dataTransfer.effectAllowed = 'copyMove';
      e.dataTransfer.setData('text/plain', 'ondaria');
    });
    tb.addEventListener('dragend', () => { drag = null; });
    const wrap = tb.closest('.list-scroll') || tb.parentElement;
    wrap.addEventListener('dragover', (e) => {
      if (!drag) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = drag.player === p && !e.ctrlKey ? 'move' : 'copy';
      $$('tr.drop', tb).forEach((r) => r.classList.remove('drop'));
      e.target.closest('tr')?.classList.add('drop');
    });
    wrap.addEventListener('dragleave', () => $$('tr.drop', tb).forEach((r) => r.classList.remove('drop')));
    wrap.addEventListener('drop', (e) => {
      if (!drag) return;
      e.preventDefault();
      $$('tr.drop', tb).forEach((r) => r.classList.remove('drop'));
      let at = this.rowIndex(e.target);
      if (at < 0) at = p.items.length;
      if (drag.player === p && !e.ctrlKey) p.moveTo(drag.items, at);
      else p.insert(drag.items, at);
      drag = null;
    });
  }
}
let activeView = null;

let clipboard = [];
function copySel(p) { clipboard = p.items.filter((it) => p.sel.has(it)).map(clone); toast(`${clipboard.length} item(ns) copiado(s).`); }
function pasteInto(p) { if (clipboard.length) p.insert(clipboard); }

async function updateLengths(p) {
  const targets = [...p.sel].filter((it) => it.type === 'track');
  for (const it of targets) {
    const ms = await new Promise((resolve) => {
      const a = new Audio();
      a.preload = 'metadata';
      a.onloadedmetadata = () => resolve(Number.isFinite(a.duration) ? Math.round(a.duration * 1000) : 0);
      a.onerror = () => resolve(0);
      a.src = mediaUrl(it.media_id);
    });
    if (ms > 0) {
      it.duration_ms = ms;
      api(`/api/media/${it.media_id}/duration`, { method: 'POST', body: { duration_ms: ms } });
    }
  }
  p.changed();
}

/* ======================================================================
 * Biblioteca lateral
 * ==================================================================== */

const library = {
  async load() {
    const folder = $('#libfolder').value;
    const q = $('#libq').value.trim();
    const r = await api(`/api/library?recursive=1&folder=${encodeURIComponent(folder)}&q=${encodeURIComponent(q)}`);
    const ul = $('#liblist');
    ul.replaceChildren();
    if (!r.ok) return;
    for (const it of r.items) {
      const li = el('li', { draggable: 'true', title: it.filename }, el('span', { text: titleOf(it) }), el('small', { text: fmt(it.duration_ms) }));
      li.addEventListener('dragstart', (e) => { drag = { items: [it] }; e.dataTransfer.effectAllowed = 'copy'; e.dataTransfer.setData('text/plain', 'ondaria'); });
      li.addEventListener('dragend', () => { drag = null; });
      li.addEventListener('dblclick', () => (activeView?.p || main).insert([it]));
      li.addEventListener('contextmenu', (e) => { e.preventDefault(); contextMenu(e.clientX, e.clientY, [['Adicionar à lista', () => main.insert([it])], ['Pré-escuta (cue)', () => cue(it)]]); });
      ul.append(li);
    }
    if (!r.items.length) ul.append(el('li', { class: 'empty', text: 'Nada encontrado.' }));
  },
  folders() {
    const sel = $('#libfolder');
    const keep = sel.value;
    const kids = {};
    for (const f of S.folders) (kids[f.parent_id || 0] ||= []).push(f);
    const opts = [new Option('Todas as pastas', '0')];
    const walk = (parent, depth) => {
      for (const f of kids[parent] || []) {
        opts.push(new Option(`${'  '.repeat(depth)}${f.name}`, f.id));
        walk(f.id, depth + 1);
      }
    };
    walk(0, 0);
    sel.replaceChildren(...opts);
    sel.value = keep || store.get('libfolder', '0');
  },
};

/* ======================================================================
 * Diálogos, menus, post-its
 * ==================================================================== */

function ask(title, fields) {
  const dlg = $('#dlg');
  $('#dlgtitle').textContent = title;
  const body = $('#dlgbody');
  body.replaceChildren(...fields);
  return new Promise((resolve) => {
    dlg.onclose = () => {
      if (dlg.returnValue !== 'ok') { resolve(null); return; }
      const out = {};
      for (const f of $$('input,select,textarea', body)) if (f.name) out[f.name] = f.type === 'checkbox' ? f.checked : f.value;
      resolve(out);
    };
    dlg.showModal();
    body.querySelector('input,select,textarea')?.focus();
  });
}
const field = (label, input) => el('label', {}, label, input);
const select = (name, options) => { const s = el('select', { name }); options.forEach(([v, t]) => s.append(new Option(t, v))); return s; };

function contextMenu(x, y, entries) {
  const m = $('#ctxmenu');
  m.replaceChildren(...entries.map((en) => (en === null ? el('li', { class: 'sep' }) : el('li', { onclick: () => { m.hidden = true; en[1](); } }, en[0]))));
  m.hidden = false;
  m.style.left = `${Math.min(x, innerWidth - 220)}px`;
  m.style.top = `${Math.min(y, innerHeight - m.offsetHeight - 10)}px`;
}
document.addEventListener('click', (e) => {
  if (!e.target.closest('#ctxmenu')) $('#ctxmenu').hidden = true;
  if (!e.target.closest('.dropdown')) $('#insertmenu').hidden = true;
});

function showPostit(text) {
  const note = el('div', { class: 'postit' }, el('button', { class: 'x', title: 'Fechar', onclick: () => note.remove() }, '×'), el('pre', { text }));
  $('#postits').append(note);
}

/* ======================================================================
 * Pré-escuta (cue), dispositivos, satélite, talk over
 * ==================================================================== */

const cueEl = new Audio();
async function cue(item) {
  if (item?.type !== 'track') { toast('Pré-escuta só funciona com arquivos de áudio.'); return; }
  if (cueEl.dataset.id === String(item.media_id) && !cueEl.paused) { cueEl.pause(); return; }
  if (!devices.cue) toast('Sem saída de pré-escuta definida: o cue sai na mesma placa do ar. Configure em Dispositivos.', 'warn');
  try { if (cueEl.setSinkId) await cueEl.setSinkId(devices.cue || ''); } catch (e) { /* placa indisponível */ }
  cueEl.dataset.id = item.media_id;
  cueEl.src = mediaUrl(item.media_id);
  cueEl.play().catch((e) => toast(e.message, 'error'));
}

async function devicesDialog() {
  const list = async () => (await navigator.mediaDevices?.enumerateDevices?.()) || [];
  let all = await list();
  if (all.some((d) => d.kind === 'audiooutput' && !d.label)) {
    try { (await navigator.mediaDevices.getUserMedia({ audio: true })).getTracks().forEach((t) => t.stop()); all = await list(); } catch (e) { /* sem permissão: nomes ficam ocultos */ }
  }
  const outs = [['', 'Padrão do sistema'], ...all.filter((d) => d.kind === 'audiooutput' && d.deviceId !== 'default').map((d) => [d.deviceId, d.label || 'Saída'])];
  const ins = [['', 'Padrão do sistema'], ...all.filter((d) => d.kind === 'audioinput' && d.deviceId !== 'default').map((d) => [d.deviceId, d.label || 'Entrada'])];
  const sel = (name, opts, value) => { const s = select(name, opts); s.value = value || ''; return s; };
  const fields = [
    field('Saída principal (no ar)', sel('main', outs, devices.main)),
    field('Pré-escuta (cue)', sel('cue', outs, devices.cue)),
    ...[0, 1, 2, 3].slice(0, S.station.max_aux_players).map((i) => field(`Player auxiliar ${i + 1}`, sel(`aux${i}`, outs, devices.aux[i]))),
    field('Microfone (talk over)', sel('mic', ins, devices.mic)),
    field('Entrada do satélite (linha)', sel('sat', ins, devices.sat)),
    el('p', { class: 'small', text: AudioContext.prototype.setSinkId ? 'As escolhas ficam salvas neste computador.' : 'Este navegador não permite escolher a placa de saída (use Chrome ou Edge). O áudio sai na placa padrão.' }),
  ];
  const r = await ask('Dispositivos de áudio', fields);
  if (!r) return;
  devices = { main: r.main, cue: r.cue, aux: [0, 1, 2, 3].map((i) => r[`aux${i}`] ?? devices.aux[i]), mic: r.mic, sat: r.sat };
  store.set('devices', devices);
  await applyDevices();
  toast('Dispositivos aplicados.');
}

async function applyDevices() {
  if (devices.main && !(await engine.setOutput(devices.main))) toast('A placa principal escolhida não está disponível.', 'warn');
  for (const [n, a] of auxPanels) await a.engine?.setOutput(devices.aux[n - 1]);
}

function setSatButton(on) { $('[data-toggle="satellite"]')?.classList.toggle('on', on); }

let talkOn = false;
async function talkOver() {
  talkOn = !talkOn;
  $('#btnTalk').classList.toggle('on', talkOn);
  engine.setDuck('talk', talkOn ? S.settings.talkover_level : null, S.settings.talkover_ms);
  if (talkOn) {
    if (await engine.openInput('mic', devices.mic)) engine.setInputOpen('mic', true, S.settings.talkover_ms);
    else toast('Microfone indisponível: só a música foi abaixada.', 'warn');
  } else {
    engine.setInputOpen('mic', false, S.settings.talkover_ms);
  }
}

/* ======================================================================
 * Players auxiliares
 * ==================================================================== */

class AuxPanel {
  constructor(n) {
    this.n = n;
    this.engine = new Engine();
    this.player = new Player({ name: `Aux ${n}`, engine: this.engine, source: `aux ${n}` });
    this.player.panel = this;
    const table = el('table', { class: 'pl compact' }, el('thead', {}, el('tr', {}, el('th', { class: 'n', text: '#' }), el('th', { text: 'Título' }), el('th', { class: 'd', text: 'Dur.' }))), el('tbody'));
    const mode = select('mode', [['normal', 'Normal'], ['repeat', 'Repetir'], ['shuffle', 'Embaralhar'], ['random', 'Aleatório'], ['manual', 'Manual']]);
    mode.addEventListener('change', () => { this.player.mode = mode.value; this.persist(); });
    this.now = el('div', { class: 'auxnow', text: '—' });
    this.box = el('section', { class: 'aux' },
      el('div', { class: 'pane-head' }, el('b', { text: `Aux ${n}` }), mode,
        el('button', { title: 'Abrir lista', onclick: () => openDialog(this.player) }, 'Abrir'),
        el('button', { title: 'Limpar', onclick: () => { this.player.stop(); this.player.loadList({ items: [], name: 'Sem título' }); } }, 'Limpar'),
        el('button', { title: 'Fechar', onclick: () => this.toggle(false) }, '×')),
      this.now,
      el('div', { class: 'list-scroll' }, table),
      el('div', { class: 'auxctl' },
        el('button', { onclick: () => this.player.play() }, '▶'),
        el('button', { onclick: () => this.player.pause() }, '❚❚'),
        el('button', { onclick: () => this.player.stop() }, '■'),
        el('button', { onclick: () => this.player.skip() }, '⏭')));
    this.view = new ListView(this.player, table);
    const saved = store.get(`aux${n}`, null);
    if (saved) { this.player.loadList({ ...saved.meta, items: saved.items }); this.player.mode = saved.mode || 'normal'; mode.value = this.player.mode; }
    this.view.render();
    this.engine.setOutput(devices.aux[n - 1]);
    this.player.onChange(() => { this.now.textContent = this.player.display?.title || '—'; });
    $('#auxpane').append(this.box);
  }

  persist() { store.set(`aux${this.n}`, { meta: this.player.meta, items: this.player.items, mode: this.player.mode }); }

  toggle(show = this.box.hidden) {
    this.box.hidden = !show;
    $('#auxpane').hidden = ![...auxPanels.values()].some((a) => !a.box.hidden);
    $(`[data-aux="${this.n}"]`)?.classList.toggle('on', show);
  }
}

function toggleAux(n) {
  if (n > S.station.max_aux_players) { toast(`Seu plano tem ${S.station.max_aux_players} player(s) auxiliar(es).`); return; }
  if (!auxPanels.has(n)) { auxPanels.set(n, new AuxPanel(n)); auxPanels.get(n).toggle(true); return; }
  auxPanels.get(n).toggle();
}

/* ======================================================================
 * Abrir / salvar listas
 * ==================================================================== */

let mainListTimer = null;
function persistMainList(now = false) {
  clearTimeout(mainListTimer);
  mainListTimer = setTimeout(() => store.set('mainlist', main.toJSON()), now ? 0 : 400);
}

async function openDialog(player = main) {
  const kinds = { list: 'Lista', rotation: 'Rotação', sequence: 'Sequência' };
  const r = await ask('Abrir', [field('Lista', select('id', S.playlists.map((p) => [p.id, `${kinds[p.kind]}: ${p.name}`])))]);
  if (!r) return;
  if (player.dirty && player.items.length && !confirm('A lista atual tem alterações não salvas. Abrir outra mesmo assim?')) return;
  const p = await fetchPlaylist(r.id);
  if (!p) { toast('Lista não encontrada.', 'error'); return; }
  player.loadList(p);
  if (player === main) { api('/api/state', { method: 'POST', body: { last_playlist_id: p.id } }); persistMainList(true); }
}

async function save(player = main, asNew = false) {
  let { id, name, kind } = player.meta;
  if (!id || asNew) {
    const r = await ask('Salvar como', [field('Nome', el('input', { name: 'name', value: asNew ? `${name} (cópia)` : name, required: true, maxlength: 120 })), field('Tipo', select('kind', [['list', 'Lista'], ['rotation', 'Rotação'], ['sequence', 'Sequência']]))]);
    if (!r) return;
    id = null;
    name = r.name;
    kind = r.kind;
  }
  const res = await api('/api/playlists', { method: 'POST', body: { id, name, kind, items: player.items } });
  if (!res.ok) { toast(res.error || 'Erro ao salvar.', 'error'); return; }
  S.playlists = res.playlists;
  player.meta = { id: res.id, name, kind };
  player.dirty = false;
  player.changed(false);
  if (player === main) api('/api/state', { method: 'POST', body: { last_playlist_id: res.id } });
  toast('Lista salva.');
}

/* ======================================================================
 * Inserir comandos
 * ==================================================================== */

async function insertCommand(type, player = main) {
  const T = S.item_types;
  let item = null;
  const lists = (kind) => S.playlists.filter((p) => !kind || p.kind === kind).map((p) => [p.id, p.name]);
  switch (type) {
    case 'pause': {
      const r = await ask('Inserir pausa', [field('Duração (0 a 3600 segundos)', el('input', { name: 's', type: 'number', min: 0, max: 3600, value: 5 }))]);
      if (r) item = { type, seconds: Math.max(0, Math.min(3600, +r.s || 0)), title: `Pausa de ${+r.s || 0} s` };
      break;
    }
    case 'random': {
      const r = await ask('Faixa aleatória', [field('Pasta (inclui subpastas)', select('f', S.folders.filter((f) => f.kind === 'normal').map((f) => [f.id, f.name])))]);
      if (r) item = { type, folder_id: +r.f, title: `Aleatória: ${S.folders.find((f) => f.id == r.f)?.name}` };
      break;
    }
    case 'stream': {
      const r = await ask('Rádio online', [
        field('Endereço real do stream (não use .m3u, .pls ou .asx)', el('input', { name: 'url', type: 'url', required: true, placeholder: 'https://servidor:8000/stream' })),
        field('Nome', el('input', { name: 't', value: 'Rádio online' })),
        field('Tempo conectado (segundos, 0 = até parar)', el('input', { name: 's', type: 'number', min: 0, value: 3600 })),
      ]);
      if (r) item = { type, url: r.url, seconds: +r.s || 0, title: r.t || 'Rádio online' };
      break;
    }
    case 'satellite': {
      const r = await ask('Satélite por tempo', [field('Tempo com a entrada aberta (segundos)', el('input', { name: 's', type: 'number', min: 1, value: 300 }))]);
      if (r) item = { type, seconds: +r.s || 0, title: `Satélite por ${fmt((+r.s || 0) * 1000)}` };
      break;
    }
    case 'postit': {
      const r = await ask('Lembrete', [field('Texto que vai aparecer na tela', el('textarea', { name: 't', rows: 5 }))]);
      if (r) item = { type, text: r.t, title: `Lembrete: ${r.t.replace(/\s+/g, ' ').slice(0, 60)}` };
      break;
    }
    case 'playlist': case 'rotation': case 'sequence': {
      const opts = lists(type === 'playlist' ? 'list' : type);
      if (!opts.length) { toast(`Nenhuma ${T[type].toLowerCase()} salva ainda. Crie em Listas.`); return; }
      const r = await ask(T[type], [field('Escolha', select('id', opts))]);
      if (r) item = { type, playlist_id: +r.id, title: `${T[type]}: ${S.playlists.find((p) => p.id == r.id)?.name}` };
      break;
    }
    case 'events': {
      const r = await ask('Carregar grade de eventos', [field('Grade', select('id', S.event_sets.map((s) => [s.id, s.name])))]);
      if (r) item = { type, event_set_id: +r.id, title: `Grade: ${S.event_sets.find((s) => s.id == r.id)?.name}` };
      break;
    }
    default: item = { type, title: T[type] };
  }
  if (item) player.insert([item]);
}

/* ======================================================================
 * Tela: relógio, no ar, medidores
 * ==================================================================== */

function updateOnAir() {
  const t = stationNow();
  const h12 = S.settings.time_12h;
  $('#clock').textContent = h12 ? `${((t.h + 11) % 12) + 1}:${pad(t.mi)}:${pad(t.s)} ${t.h < 12 ? 'AM' : 'PM'}` : `${pad(t.h)}:${pad(t.mi)}:${pad(t.s)}`;
  $('#date').textContent = `${pad(t.d)}/${pad(t.mo)}/${t.y}`;
  const d = main.display;
  let remaining = null;
  let total = null;
  if (d && main.state !== 'stopped') {
    if (d.deck && !d.deck.disposed) { total = d.deck.duration * 1000; remaining = total - d.deck.time * 1000; } else if (d.durMs) { total = d.durMs; remaining = d.durMs - (d.waitState?.elapsed || 0); }
  }
  $('#nowtitle').textContent = main.state === 'stopped' ? '—' : (main.playingEvent ? `⏰ ${d?.title || main.playingEvent.ev.name}` : d?.title || '—');
  $('#remain').textContent = remaining !== null ? fmt(Math.max(0, remaining)).replace('—', '0:00') : '--:--';
  $('#endat').textContent = remaining !== null ? wallStr(t.ms + remaining) : '--:--:--';
  $('#progress').style.width = total ? `${Math.min(100, (1 - remaining / total) * 100)}%` : '0%';
  const nx = main.items[main.next];
  $('#nexttitle').textContent = scheduler.pending.length ? `⏰ ${scheduler.pending[0].ev.name}` : nx ? titleOf(nx) : '—';
  document.body.classList.toggle('onair', main.state === 'playing');
  $('#btnPause').classList.toggle('on', main.state === 'paused');
  $('#btnStopAfter').classList.toggle('on', main.stopAfter);
}

function updateMeters() {
  const [l, r] = engine.levels();
  const pct = (v) => `${Math.max(0, Math.min(100, (20 * Math.log10(v || 1e-6) + 48) / 48 * 100))}%`;
  $('#vuL').style.width = pct(l);
  $('#vuR').style.width = pct(r);
  $('#vuL').classList.toggle('clip', l > 0.98);
  $('#vuR').classList.toggle('clip', r > 0.98);
  requestAnimationFrame(updateMeters);
}

function updateWeather() {
  const w = S.weather;
  $('#weather').textContent = w && w.valid && w.temp !== null ? `${w.temp}°${w.unit} · ${w.hum}%` : '';
}

/* ======================================================================
 * Instância principal (só um estúdio dispara eventos)
 * ==================================================================== */

async function heartbeat(claim = false) {
  const r = await api('/api/heartbeat', { method: 'POST', body: { token: TAB_TOKEN, claim } });
  if (!r.ok) return;
  skew = r.server_now - Date.now();
  const was = isLeader;
  isLeader = r.leader;
  const pill = $('#leader');
  pill.textContent = isLeader ? 'principal' : 'secundário';
  pill.className = `pill ${isLeader ? 'ok' : 'warn'}`;
  pill.title = isLeader ? 'Este estúdio dispara os eventos.' : 'Outro estúdio está aberto e dispara os eventos. Clique para assumir.';
  if (!isLeader && was) toast('Outro computador assumiu o estúdio principal. Os eventos não serão disparados aqui.', 'warn');
  if (!isLeader && !S.settings.allow_multi_instance && !was && !claim) {
    if (confirm('O estúdio desta rádio já está aberto em outro computador ou aba. Assumir como estúdio principal aqui? (O outro deixa de disparar eventos.)')) heartbeat(true);
  }
  scheduler.render(true);
}

async function refresh() {
  const r = await api('/api/bootstrap');
  if (!r.ok) return;
  CSRF = r.csrf;
  Object.assign(S, { settings: r.settings, folders: r.folders, playlists: r.playlists, jingles: r.jingles, event_sets: r.event_sets, sweepers: r.sweepers, weather: r.weather, station: r.station });
  // A grade carregada por comando continua valendo até o servidor confirmar a troca.
  if (r.event_set_id === S.event_set_id || !S.event_set_id) { S.events = r.events; S.event_set_id = r.event_set_id; }
  library.folders();
  jingles.render();
  updateWeather();
  scheduler.render(true);
}

/* ======================================================================
 * Transmissão
 * ==================================================================== */

function setupStream() {
  const btn = $('#btnStream');
  const s = S.settings;
  btn.hidden = !(S.station.streaming && s.stream_enabled && s.stream_url);
  broadcaster = new Broadcaster(engine, (state, msg) => {
    btn.className = `pill ${state === 'live' ? 'live' : state === 'error' ? 'warn' : ''}`;
    btn.textContent = { live: '● AO VIVO', connecting: 'Conectando…', error: 'Falha', off: 'Transmitir' }[state] || 'Transmitir';
    if (state === 'error') toast(msg, 'error');
    if (state === 'off' && msg) toast(`Transmissão encerrada: ${msg}`, 'warn');
  });
  btn.addEventListener('click', () => {
    if (broadcaster.live || btn.textContent === 'Conectando…') { broadcaster.stop(); return; }
    broadcaster.start({ url: s.stream_url, user: s.stream_user, password: s.stream_pass, bitrate: s.stream_bitrate });
  });
}

/* ======================================================================
 * Inicialização
 * ==================================================================== */

function bindUi() {
  $('#btnPlay').addEventListener('click', () => main.play());
  $('#btnPause').addEventListener('click', () => (main.state === 'paused' ? main.play() : main.pause()));
  $('#btnStop').addEventListener('click', () => main.stop());
  $('#btnNext').addEventListener('click', () => main.skip());
  $('#btnRew').addEventListener('click', () => main.seek(-30));
  $('#btnFwd').addEventListener('click', () => main.seek(30));
  $('#btnStopAfter').addEventListener('click', () => { main.stopAfter = !main.stopAfter; updateOnAir(); });
  $('#btnTalk').addEventListener('click', talkOver);
  $('#btnTime').addEventListener('click', () => jingles.start(`tth${randHex(3)}`, { type: 'time' }, 'normal', 'Hora certa'));
  $('#btnTemp').addEventListener('click', () => jingles.start(`tth${randHex(3)}`, { type: 'temperature' }, 'normal', 'Temperatura'));
  $('#btnHum').addEventListener('click', () => jingles.start(`tth${randHex(3)}`, { type: 'humidity' }, 'normal', 'Umidade'));
  $('#btnDevices').addEventListener('click', devicesDialog);
  $('#master').addEventListener('input', (e) => { engine.setMaster(+e.target.value); store.set('master', +e.target.value); });
  $('#mode').addEventListener('change', (e) => { main.mode = e.target.value; main.computeNext(); main.changed(false); });
  $('#jpage').addEventListener('change', (e) => { jingles.page = +e.target.value; store.set('jpage', jingles.page); jingles.render(); });
  $('#evPlay').addEventListener('click', () => scheduler.playPending());
  $('#evDiscard').addEventListener('click', () => {
    for (const p of scheduler.pending) log('evento', 'evento', p.ev.name, { status: 'error', detail: 'Descartado pelo operador' });
    scheduler.pending = [];
    scheduler.render(true);
  });
  $('#evToggle').addEventListener('click', () => { scheduler.enabled = !scheduler.enabled; scheduler.render(true); });
  $('#leader').addEventListener('click', () => { if (!isLeader) heartbeat(true); });

  $$('[data-toggle]').forEach((b) => b.addEventListener('click', () => {
    const k = b.dataset.toggle;
    if (k === 'sweepers') main.sweepersOn = !main.sweepersOn; else main[k] = !main[k];
    b.classList.toggle('on', k === 'sweepers' ? main.sweepersOn : main[k]);
    if (k === 'loop') main.computeNext();
    main.changed(false);
  }));

  const actions = {
    new: () => { if (main.dirty && main.items.length && !confirm('Descartar as alterações da lista atual?')) return; main.loadList({ items: [], name: 'Sem título' }); },
    open: () => openDialog(main),
    save: () => save(main),
    saveas: () => save(main, true),
    remove: () => (activeView?.p || main).removeSelected(),
    copy: () => copySel(activeView?.p || main),
    paste: () => pasteInto(activeView?.p || main),
    find: () => { const f = $('#findbox'); f.hidden = !f.hidden; if (!f.hidden) f.focus(); else { listView.filter = ''; listView.paint(); } },
    insertmenu: () => { $('#insertmenu').hidden = !$('#insertmenu').hidden; },
    shuffle: () => main.shuffle(),
    sellength: () => toast(`Seleção: ${fmt([...main.sel].reduce((a, it) => a + itemMs(it), 0))}`),
    updlength: () => updateLengths(main),
  };
  $$('[data-act]').forEach((b) => b.addEventListener('click', () => actions[b.dataset.act]?.()));
  $$('[data-insert]').forEach((b) => b.addEventListener('click', () => { $('#insertmenu').hidden = true; insertCommand(b.dataset.insert, activeView?.p || main); }));
  $('#findbox').addEventListener('input', (e) => {
    listView.filter = e.target.value;
    listView.paint();
    $('#playlist tr.match')?.scrollIntoView({ block: 'center' });
  });

  let libTimer;
  $('#libq').addEventListener('input', () => { clearTimeout(libTimer); libTimer = setTimeout(() => library.load(), 300); });
  $('#libfolder').addEventListener('change', (e) => { store.set('libfolder', e.target.value); library.load(); });

  const auxBox = $('#auxbtns');
  for (let n = 1; n <= Math.min(4, S.station.max_aux_players); n++) {
    auxBox.append(el('button', { 'data-aux': n, title: `Player auxiliar ${n} (Alt+${n})`, onclick: () => toggleAux(n) }, `Aux ${n}`));
  }
  auxBox.append(el('button', { 'data-toggle': 'satellite', title: 'Abrir/fechar a entrada do satélite' }, 'Satélite'));
  $('[data-toggle="satellite"]').addEventListener('click', (e) => {
    const b = e.currentTarget;
    const on = !b.classList.contains('on');
    (on ? engine.openInput('sat', devices.sat) : Promise.resolve(true)).then((ok) => {
      if (!ok) { toast('Entrada do satélite indisponível (verifique Dispositivos).', 'error'); return; }
      engine.setInputOpen('sat', on, 300);
      setSatButton(on);
    });
  });

  document.addEventListener('keydown', (e) => {
    const typing = e.target.closest('input,textarea,select,[contenteditable]') || $('#dlg').open;
    if (typing) return;
    if (e.altKey && /^[1-4]$/.test(e.key)) { e.preventDefault(); toggleAux(+e.key); return; }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(main); return; }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c') { copySel(activeView?.p || main); return; }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'v') { pasteInto(activeView?.p || main); return; }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') { e.preventDefault(); actions.find(); return; }
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    if (/^[1-9]$/.test(e.key)) { e.preventDefault(); jingles.press(+e.key - 1); return; }
    if (e.key === 'Delete') (activeView?.p || main).removeSelected();
    if (e.key === 'Escape') { $('#ctxmenu').hidden = true; $('#insertmenu').hidden = true; }
  });

  window.addEventListener('beforeunload', (e) => {
    flushLog();
    if (S.settings.confirm_close && main.state === 'playing') { e.preventDefault(); e.returnValue = ''; }
  });
}

let listView;

async function boot() {
  const r = await api('/api/bootstrap');
  if (!r.ok) { $('#bootmsg').textContent = r.error || 'Não foi possível carregar o estúdio.'; return; }
  Object.assign(S, r);
  CSRF = r.csrf;
  skew = r.server_now - Date.now();
  fmtParts = new Intl.DateTimeFormat('en-US', { timeZone: S.station.timezone, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
  devices = { ...devices, ...store.get('devices', {}) };
  $('#bootmsg').textContent = `${S.station.name} — plano ${S.station.plan}`;
  $('#bootbtn').hidden = false;
  $('#bootbtn').addEventListener('click', start, { once: true });
}

async function start() {
  $('#boot').hidden = true;
  $('#studio').hidden = false;
  $('#stname').textContent = S.station.name;
  document.title = `${S.station.name} — Estúdio`;
  ticker = new Ticker(100);
  engine = new Engine();
  await engine.resume();
  engine.setMaster(store.get('master', 1));
  $('#master').value = store.get('master', 1);
  main = new Player({ name: 'Principal', engine, isMain: true });
  scheduler = new Scheduler();
  jingles = new Jingles();
  listView = new ListView(main, $('#playlist'), { info: $('#listinfo') });
  activeView = listView;

  // Lista inicial: link "#lista=ID", rascunho local, ou a última lista usada.
  const hash = /lista=(\d+)/.exec(location.hash);
  const local = store.get('mainlist', null);
  let loaded = false;
  if (hash) {
    const p = await fetchPlaylist(+hash[1]);
    if (p) { main.loadList(p); loaded = true; history.replaceState(null, '', location.pathname); }
  }
  if (!loaded && local && S.settings.open_last_list) {
    main.loadList({ ...local.meta, items: local.items });
    main.dirty = !!local.dirty;
    main.mode = local.mode || 'normal';
    loaded = true;
  }
  if (!loaded && S.settings.last_playlist_id) {
    const p = await fetchPlaylist(S.settings.last_playlist_id);
    if (p) main.loadList(p);
  }
  if (!main.items.length && !loaded && S.playlists[0]) {
    const p = await fetchPlaylist(S.playlists[0].id);
    if (p) main.loadList(p);
  }
  $('#mode').value = main.mode;
  listView.render();

  bindUi();
  library.folders();
  library.load();
  jingles.render();
  updateWeather();
  setupStream();
  await applyDevices();
  await heartbeat();
  scheduler.render(true);

  // Tudo pendurado no relógio do Worker: timers comuns são freados com a aba em segundo plano.
  let ticks = 0;
  ticker.on(() => {
    updateOnAir();
    ticks++;
    if (ticks % 10 === 0) scheduler.tick();
    if (ticks % 50 === 0) flushLog();
    if (ticks % 100 === 0) heartbeat();
    if (ticks % 600 === 0) refresh();
  });
  requestAnimationFrame(updateMeters);

  if (S.settings.autostart) main.play();
}

boot();
