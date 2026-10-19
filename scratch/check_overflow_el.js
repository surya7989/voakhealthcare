const http = require('http');

async function test() {
  const req = http.get('http://127.0.0.1:4192/index.html', async (res) => {
    // server is running or let's connect to Edge debug port
  });
  
  const debugReq = http.get('http://127.0.0.1:9249/json/list', r => {
    let d = ''; r.on('data', c => d += c);
    r.on('end', async () => {
      try {
        const targets = JSON.parse(d);
        const wsUrl = targets[0].webSocketDebuggerUrl;
        const ws = new WebSocket(wsUrl);
        let id = 1;
        const send = (m, p={}) => new Promise((res, rej) => {
          const curId = id++;
          const h = evt => {
            const msg = JSON.parse(evt.data);
            if (msg.id === curId) { ws.removeEventListener('message', h); res(msg.result); }
          };
          ws.addEventListener('message', h);
          ws.send(JSON.stringify({ id: curId, method: m, params: p }));
        });
        await new Promise(r => ws.onopen = r);
        await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
        
        const evalRes = await send('Runtime.evaluate', {
          expression: `(() => {
            const els = Array.from(document.querySelectorAll('*')).filter(el => el.getBoundingClientRect().right > 390);
            return els.slice(0, 10).map(el => ({
              tag: el.tagName,
              cls: el.className,
              id: el.id,
              right: el.getBoundingClientRect().right,
              width: el.getBoundingClientRect().width
            }));
          })()`,
          returnByValue: true
        });
        console.log('Overflowing elements at 390px:');
        console.log(evalRes.result.value);
        ws.close();
      } catch (e) {
        console.error(e);
      }
    });
  });
  debugReq.on('error', (err) => console.log('Could not connect to 9249:', err.message));
}
test();
