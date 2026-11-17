// Timers dentro de um Worker não são estrangulados quando a aba do estúdio fica em segundo plano.
let timer = null;
self.onmessage = (ev) => {
  clearInterval(timer);
  timer = setInterval(() => self.postMessage(0), Number(ev.data) || 100);
};
