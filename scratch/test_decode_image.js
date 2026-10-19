const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 4199;
const server = http.createServer((req, res) => {
  const filePath = path.join(__dirname, '..', 'design', 'phase-03', 'references', 'approved_homepage_reference_image_2.png');
  const buf = fs.readFileSync(filePath);
  res.writeHead(200, { 'Content-Type': 'image/png' });
  res.end(buf);
});

server.listen(PORT, '127.0.0.1', async () => {
  const browserPath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
  const proc = spawn(browserPath, [
    '--headless=new',
    '--remote-debugging-port=9249',
    'about:blank'
  ]);
  await new Promise(r => setTimeout(r, 1200));

  const listReq = http.get('http://127.0.0.1:9249/json/list', r => {
    let d = ''; r.on('data', c => d += c);
    r.on('end', async () => {
      const targets = JSON.parse(d);
      const wsUrl = targets[0].webSocketDebuggerUrl;
      const ws = new WebSocket(wsUrl);
      await new Promise(res => ws.onopen = res);
      
      let id = 1;
      const send = (method, params={}) => new Promise(res => {
        const cur = id++;
        const h = e => {
          const m = JSON.parse(e.data);
          if (m.id === cur) { ws.removeEventListener('message', h); res(m.result); }
        };
        ws.addEventListener('message', h);
        ws.send(JSON.stringify({ id: cur, method, params }));
      });

      await send('Page.enable');
      await send('Page.navigate', { url: `http://127.0.0.1:${PORT}/` });
      await new Promise(r => setTimeout(r, 1500));

      const evalRes = await send('Runtime.evaluate', {
        expression: `(() => {
          const img = document.querySelector('img');
          return {
            complete: img ? img.complete : false,
            naturalWidth: img ? img.naturalWidth : 0,
            naturalHeight: img ? img.naturalHeight : 0,
            error: !img || img.naturalWidth === 0
          };
        })()`,
        returnByValue: true
      });

      console.log('BROWSER_DECODER_RESULT:', evalRes.result.value);
      ws.close();
      proc.kill();
      server.close();
      process.exit(0);
    });
  });
});
