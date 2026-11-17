// Motor de áudio do estúdio (Web Audio API).
//
//  deck/stream ─▶ programBus ─▶ duck ─┐
//  vinhetas ────▶ jingleBus ──────────┼─▶ master ─▶ medidores L/R ─▶ saída (placa escolhida)
//  microfone ───▶ micGain (talk over) ┤                         └──▶ streamDest (transmissão)
//  entrada ─────▶ satGain (satélite) ─┘

export const dbOf = (rms) => (rms > 0 ? 20 * Math.log10(rms) : -120);

export class Engine {
  constructor() {
    this.ctx = new (window.AudioContext || window.webkitAudioContext)({ latencyHint: 'playback' });
    const c = this.ctx;
    this.programBus = c.createGain();
    this.duck = c.createGain();
    this.jingleBus = c.createGain();
    this.micGain = c.createGain();
    this.satGain = c.createGain();
    this.master = c.createGain();
    this.micGain.gain.value = 0;
    this.satGain.gain.value = 0;
    this.programBus.connect(this.duck).connect(this.master);
    this.jingleBus.connect(this.master);
    this.micGain.connect(this.master);
    this.satGain.connect(this.master);

    const split = c.createChannelSplitter(2);
    this.meterL = c.createAnalyser();
    this.meterR = c.createAnalyser();
    this.meterL.fftSize = this.meterR.fftSize = 1024;
    this.master.connect(split);
    split.connect(this.meterL, 0);
    split.connect(this.meterR, 1);
    this.master.connect(c.destination);
    this.streamDest = c.createMediaStreamDestination();
    this.master.connect(this.streamDest);

    this.buf = new Float32Array(1024);
    this.duckLevels = new Map();
    this.micStream = null;
    this.satStream = null;
  }

  async resume() {
    if (this.ctx.state !== 'running') await this.ctx.resume();
  }

  /** Placa de som de saída (Chrome/Edge 110+). */
  async setOutput(deviceId) {
    if (!this.ctx.setSinkId) return false;
    try { await this.ctx.setSinkId(deviceId || ''); return true; } catch (e) { console.warn(e); return false; }
  }

  setMaster(v) { this.master.gain.setTargetAtTime(v, this.ctx.currentTime, 0.02); }

  /** Vários motivos podem abaixar a programação (talk over, sweeper); vale o menor nível. */
  setDuck(reason, level, ms = 300) {
    if (level === null || level >= 1) this.duckLevels.delete(reason); else this.duckLevels.set(reason, level);
    const target = Math.min(1, ...this.duckLevels.values());
    const t = this.ctx.currentTime;
    this.duck.gain.cancelScheduledValues(t);
    this.duck.gain.setValueAtTime(this.duck.gain.value, t);
    this.duck.gain.linearRampToValueAtTime(target, t + Math.max(0.01, ms / 1000));
  }

  levels() {
    const peak = (an) => {
      an.getFloatTimeDomainData(this.buf);
      let p = 0;
      for (let i = 0; i < this.buf.length; i++) p = Math.max(p, Math.abs(this.buf[i]));
      return p;
    };
    return [peak(this.meterL), peak(this.meterR)];
  }

  async openInput(kind, deviceId) {
    const key = kind === 'mic' ? 'micStream' : 'satStream';
    if (this[key]) return true;
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        audio: { deviceId: deviceId ? { exact: deviceId } : undefined, echoCancellation: false, noiseSuppression: false, autoGainControl: false },
      });
      this[key] = stream;
      this.ctx.createMediaStreamSource(stream).connect(kind === 'mic' ? this.micGain : this.satGain);
      return true;
    } catch (e) {
      console.warn(e);
      return false;
    }
  }

  setInputOpen(kind, open, ms = 200) {
    const g = (kind === 'mic' ? this.micGain : this.satGain).gain;
    const t = this.ctx.currentTime;
    g.cancelScheduledValues(t);
    g.setValueAtTime(g.value, t);
    g.linearRampToValueAtTime(open ? 1 : 0, t + ms / 1000);
  }
}

/** Um "toca-discos": um elemento <audio> ligado ao grafo, com ganho próprio e medidor. */
export class Deck {
  constructor(engine, bus, { crossOrigin = false } = {}) {
    this.engine = engine;
    this.el = new Audio();
    this.el.preload = 'auto';
    if (crossOrigin) this.el.crossOrigin = 'anonymous';
    const c = engine.ctx;
    this.src = c.createMediaElementSource(this.el);
    this.gain = c.createGain();
    this.analyser = c.createAnalyser();
    this.analyser.fftSize = 2048;
    this.src.connect(this.gain).connect(bus);
    this.gain.connect(this.analyser);
    this.buf = new Float32Array(2048);
    this.disposed = false;
  }

  load(url) {
    return new Promise((resolve, reject) => {
      const ok = () => { cleanup(); resolve(); };
      const fail = () => { cleanup(); reject(new Error(this.el.error?.message || 'Arquivo não pôde ser aberto')); };
      const cleanup = () => { this.el.removeEventListener('canplay', ok); this.el.removeEventListener('error', fail); };
      this.el.addEventListener('canplay', ok);
      this.el.addEventListener('error', fail);
      this.el.src = url;
      this.el.load();
    });
  }

  async play() { await this.engine.resume(); await this.el.play(); }
  pause() { this.el.pause(); }
  get time() { return this.el.currentTime || 0; }
  get duration() { return Number.isFinite(this.el.duration) ? this.el.duration : 0; }
  get paused() { return this.el.paused; }
  seek(t) { this.el.currentTime = Math.max(0, Math.min(t, Math.max(0, this.duration - 0.2))); }

  rmsDb() {
    this.analyser.getFloatTimeDomainData(this.buf);
    let s = 0;
    for (let i = 0; i < this.buf.length; i++) s += this.buf[i] * this.buf[i];
    return dbOf(Math.sqrt(s / this.buf.length));
  }

  fadeTo(level, ms) {
    const g = this.gain.gain;
    const t = this.engine.ctx.currentTime;
    g.cancelScheduledValues(t);
    g.setValueAtTime(g.value, t);
    g.linearRampToValueAtTime(level, t + Math.max(0.01, ms / 1000));
  }

  /** Para com fade opcional e libera o elemento. */
  stop(fadeMs = 0) {
    if (this.disposed) return;
    this.disposed = true;
    const end = () => {
      this.el.pause();
      this.el.removeAttribute('src');
      this.el.load();
      try { this.src.disconnect(); this.gain.disconnect(); } catch (e) { /* já desconectado */ }
    };
    if (fadeMs > 0 && !this.el.paused) {
      this.fadeTo(0, fadeMs);
      setTimeout(end, fadeMs + 50);
    } else {
      end();
    }
  }
}

/** Relógio que não é freado quando a aba fica em segundo plano (timers de Worker). */
export class Ticker {
  constructor(ms = 100) {
    this.subs = new Set();
    const url = new URL('./tick-worker.js', import.meta.url);
    this.worker = new Worker(url);
    this.worker.onmessage = () => { for (const fn of this.subs) { try { fn(); } catch (e) { console.error(e); } } };
    this.worker.postMessage(ms);
  }
  on(fn) { this.subs.add(fn); return () => this.subs.delete(fn); }
}

/** Envio para Liquidsoap harbor / AzuraCast Web DJ pelo protocolo "webcast" (WebSocket). */
export class Broadcaster {
  constructor(engine, onState) {
    this.engine = engine;
    this.onState = onState;
    this.ws = null;
    this.rec = null;
  }

  get live() { return this.ws?.readyState === WebSocket.OPEN; }

  start({ url, user, password, bitrate }) {
    this.stop();
    const mime = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/webm'].find((m) => MediaRecorder.isTypeSupported(m));
    if (!mime) { this.onState('error', 'Este navegador não codifica Opus. Use Chrome, Edge ou Firefox.'); return; }
    this.onState('connecting');
    const ws = new WebSocket(url, 'webcast');
    this.ws = ws;
    ws.onopen = () => {
      ws.send(JSON.stringify({ type: 'hello', data: { mime, user, password, audio: { channels: 2, samplerate: this.engine.ctx.sampleRate, bitrate: bitrate * 1000, encoder: 'MediaRecorder' } } }));
      this.rec = new MediaRecorder(this.engine.streamDest.stream, { mimeType: mime, audioBitsPerSecond: bitrate * 1000 });
      this.rec.ondataavailable = async (ev) => {
        if (ev.data.size > 0 && ws.readyState === WebSocket.OPEN) ws.send(await ev.data.arrayBuffer());
      };
      this.rec.start(250);
      this.onState('live');
    };
    ws.onerror = () => this.onState('error', 'Não foi possível conectar ao servidor de transmissão.');
    ws.onclose = (ev) => {
      if (this.ws === ws) { this.stopRecorder(); this.ws = null; this.onState('off', ev.reason || ''); }
    };
  }

  metadata(title, artist) {
    if (this.live) this.ws.send(JSON.stringify({ type: 'metadata', data: { title, artist } }));
  }

  stopRecorder() {
    if (this.rec && this.rec.state !== 'inactive') this.rec.stop();
    this.rec = null;
  }

  stop() {
    this.stopRecorder();
    if (this.ws) { const ws = this.ws; this.ws = null; ws.close(); }
    this.onState('off');
  }
}
