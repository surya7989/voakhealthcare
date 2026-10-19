const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 4195;
const DEBUG_PORT = 9255;
const ROOT = path.join(__dirname, '..', 'design', 'phase-03');
const browserPath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.svg': 'image/svg+xml'
};

const server = http.createServer((req, res) => {
  let reqPath = decodeURI(req.url.split('?')[0]);
  if (reqPath === '/') reqPath = '/index.html';
  const filePath = path.join(ROOT, reqPath);
  if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
    res.writeHead(404); res.end(); return;
  }
  const ext = path.extname(filePath).toLowerCase();
  res.writeHead(200, { 'Content-Type': MIME_TYPES[ext] || 'application/octet-stream' });
  fs.createReadStream(filePath).pipe(res);
});

server.listen(PORT, '127.0.0.1', async () => {
  console.log(`Diagnostic Server listening on port ${PORT}`);
  await test();
});

async function test() {
  const browser = spawn(browserPath, [
    `--remote-debugging-port=${DEBUG_PORT}`,
    '--headless=new',
    '--disable-gpu',
    '--no-sandbox',
    'about:blank'
  ]);

  await new Promise(r => setTimeout(r, 1200));

  const httpGet = (url) => new Promise((resolve, reject) => {
    http.get(url, res => {
      let d = ''; res.on('data', c => d += c); res.on('end', () => resolve(JSON.parse(d)));
    }).on('error', reject);
  });

  const targets = await httpGet(`http://127.0.0.1:${DEBUG_PORT}/json`);
  const pageTarget = targets.find(t => t.type === 'page');
  const wsUrl = pageTarget.webSocketDebuggerUrl;

  const WebSocket = require('ws');
  const ws = new WebSocket(wsUrl);
  await new Promise(r => ws.on('open', r));

  let id = 1;
  const send = (method, params = {}) => new Promise((resolve) => {
    const curId = id++;
    const handler = (data) => {
      const msg = JSON.parse(data);
      if (msg.id === curId) {
        ws.off('message', handler);
        resolve(msg);
      }
    };
    ws.on('message', handler);
    ws.send(JSON.stringify({ id: curId, method, params }));
  });

  await send('Page.enable');
  await send('Runtime.enable');
  await send('Page.navigate', { url: `http://127.0.0.1:${PORT}/` });
  await new Promise(r => setTimeout(r, 1500));

  // Remove preview-navbar for testing
  await send('Runtime.evaluate', {
    expression: `(() => {
      const nav = document.querySelector('.preview-navbar');
      if (nav) nav.style.display = 'none';
      const modal = document.getElementById('screen-inspector-modal');
      const container = document.querySelector('.screen-inspector-container');
      const topbar = document.querySelector('.screen-inspector-topbar');
      if (modal) {
        modal.style.position = 'fixed';
        modal.style.top = '0';
        modal.style.left = '0';
        modal.style.width = '100vw';
        modal.style.height = '100vh';
        // DO NOT set overflowX = 'hidden'
        modal.style.padding = '0';
      }
      if (topbar) topbar.style.display = 'none';
      if (container) {
        container.style.maxWidth = '100%';
        container.style.width = '100%';
        container.style.borderRadius = '0';
        container.style.boxShadow = 'none';
        container.style.border = 'none';
        container.style.margin = '0';
      }
    })()`
  });

  const breakpoints = [320, 360, 390, 428, 768, 1024, 1280, 1440, 1920];

  console.log('Testing PUB-01 to PUB-04 at 320px:');
  for (let p = 1; p <= 4; p++) {
    const pageId = `PUB-${String(p).padStart(2, '0')}`;
    await send('Runtime.evaluate', { expression: `inspectPage('${pageId}')` });
    await new Promise(r => setTimeout(r, 200));

    await send('Emulation.setDeviceMetricsOverride', {
      width: 320,
      height: 568,
      deviceScaleFactor: 1,
      mobile: false
    });
    await new Promise(r => setTimeout(r, 150));

    const evalRes = await send('Runtime.evaluate', {
      expression: `(() => {
        const body = document.getElementById('screen-mockup-body');
        const modal = document.getElementById('screen-inspector-modal');
        const innerW = window.innerWidth;
        const docScrollW = document.documentElement.scrollWidth;
        const modalScrollW = modal ? modal.scrollWidth : 0;
        const bodyScrollW = body ? body.scrollWidth : 0;
        const maxScrollW = Math.max(docScrollW, modalScrollW, bodyScrollW);

        // check elements
        const offscreen = [];
        if (body) {
          const els = body.querySelectorAll('*');
          for (let el of els) {
            const r = el.getBoundingClientRect();
            if (r.right > innerW + 2) {
              offscreen.push({ tag: el.tagName, class: el.className, right: r.right });
              if (offscreen.length >= 3) break;
            }
          }
        }
        return {
          innerW,
          docScrollW,
          modalScrollW,
          bodyScrollW,
          maxScrollW,
          overflow: maxScrollW > innerW + 1.5,
          offscreen
        };
      })()`,
      returnByValue: true
    });

    console.log(`Page [${pageId}]:`, evalRes.result.value);
  }

  ws.close();
  browser.kill();
  server.close();
  process.exit(0);
}
