const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 4198;
const DEBUG_PORT = 9251;
const ROOT = path.join(__dirname, '..', 'design', 'phase-03');

const server = http.createServer((req, res) => {
  let reqPath = decodeURI(req.url.split('?')[0]);
  if (reqPath === '/') reqPath = '/index.html';
  const filePath = path.join(ROOT, reqPath);
  if (!fs.existsSync(filePath)) { res.writeHead(404); res.end(); return; }
  res.writeHead(200);
  fs.createReadStream(filePath).pipe(res);
});

server.listen(PORT, '127.0.0.1', async () => {
  const browserPath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
  const userDataDir = path.join(__dirname, 'edge-debug-overflow-2');
  const browser = spawn(browserPath, [
    '--headless=new',
    `--remote-debugging-port=${DEBUG_PORT}`,
    '--disable-gpu',
    '--no-first-run',
    `--user-data-dir=${userDataDir}`,
    'about:blank'
  ]);

  await new Promise(r => setTimeout(r, 1500));
  http.get(`http://127.0.0.1:${DEBUG_PORT}/json/list`, r => {
    let d = ''; r.on('data', c => d += c);
    r.on('end', async () => {
      const targets = JSON.parse(d);
      const ws = new WebSocket(targets[0].webSocketDebuggerUrl);
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
      await send('Page.enable');

      // Set 390px override BEFORE navigating
      await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: false });
      await send('Page.navigate', { url: `http://127.0.0.1:${PORT}/index.html` });
      await new Promise(r => setTimeout(r, 2000));

      await send('Runtime.evaluate', {
        expression: `(() => {
          const nav = document.querySelector('.preview-navbar');
          if (nav) nav.style.display = 'none';
          switchTab('admin-portal');
          simulateRole('ROLE_SUPER_ADMIN');
          showAdminModule('ADM-01');
        })()`
      });
      await new Promise(r => setTimeout(r, 500));

      const evalRes = await send('Runtime.evaluate', {
        expression: `(() => {
          const innerW = window.innerWidth;
          const scrollW = document.documentElement.scrollWidth;
          const clientW = document.documentElement.clientWidth;
          const els = Array.from(document.querySelectorAll('*')).filter(el => {
            const rect = el.getBoundingClientRect();
            return rect.right > innerW + 1;
          });
          return {
            innerW,
            scrollW,
            clientW,
            overflowCount: els.length,
            sample: els.slice(0, 10).map(el => ({
              tag: el.tagName,
              cls: el.className,
              id: el.id,
              right: el.getBoundingClientRect().right,
              width: el.getBoundingClientRect().width
            }))
          };
        })()`,
        returnByValue: true
      });
      console.log('Result at 390px:', JSON.stringify(evalRes.result.value, null, 2));

      ws.close();
      browser.kill();
      server.close();
      process.exit(0);
    });
  });
});
