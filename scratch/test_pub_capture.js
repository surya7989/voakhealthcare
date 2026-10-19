const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 4173;
const ROOT = path.join(__dirname, '..', 'design', 'phase-03');

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
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
  const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
  const userDataDir = path.join(__dirname, 'edge-test-pub');

  const edgeProcess = spawn(edgePath, [
    '--headless=new',
    '--remote-debugging-port=9225',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    '--hide-scrollbars',
    `--user-data-dir=${userDataDir}`,
    'about:blank'
  ]);

  await new Promise(r => setTimeout(r, 1500));

  try {
    const targets = await new Promise((res, rej) => {
      http.get('http://127.0.0.1:9225/json/list', r => {
        let d = ''; r.on('data', c => d += c); r.on('end', () => res(JSON.parse(d)));
      }).on('error', rej);
    });
    const wsUrl = (targets.find(t => t.type === 'page') || targets[0]).webSocketDebuggerUrl;

    const ws = new WebSocket(wsUrl);
    let id = 1;
    const send = (method, params = {}) => new Promise((resolve, reject) => {
      const curId = id++;
      const handler = evt => {
        const msg = JSON.parse(evt.data);
        if (msg.id === curId) {
          ws.removeEventListener('message', handler);
          if (msg.error) reject(new Error(msg.error.message));
          else resolve(msg.result);
        }
      };
      ws.addEventListener('message', handler);
      ws.send(JSON.stringify({ id: curId, method, params }));
    });

    await new Promise(r => ws.onopen = r);
    await send('Page.enable');
    await send('Runtime.enable');

    await send('Page.navigate', { url: 'http://127.0.0.1:4173/index.html' });
    await new Promise(r => setTimeout(r, 2000));

    // Inspect PUB-10
    await send('Runtime.evaluate', { expression: "inspectPage('PUB-10')" });
    await new Promise(r => setTimeout(r, 500));

    // Style as standalone clean view
    await send('Runtime.evaluate', {
      expression: `(() => {
        // Hide preview navbar so it's a true standalone page
        const nav = document.querySelector('.preview-navbar');
        if (nav) nav.style.display = 'none';

        // Hide other view panels
        document.querySelectorAll('.view-panel').forEach(p => p.style.display = 'none');

        const modal = document.getElementById('screen-inspector-modal');
        const container = document.querySelector('.screen-inspector-container');
        const topbar = document.querySelector('.screen-inspector-topbar');

        if (modal) {
          modal.style.position = 'static';
          modal.style.background = 'var(--voak-canvas)';
          modal.style.padding = '0';
          modal.style.backdropFilter = 'none';
          modal.style.display = 'block';
        }
        if (topbar) topbar.style.display = 'none';
        if (container) {
          container.style.maxWidth = '100%';
          container.style.borderRadius = '0';
          container.style.boxShadow = 'none';
          container.style.border = 'none';
          container.style.margin = '0';
        }
        document.body.style.overflow = 'visible';
      })()`
    });
    await new Promise(r => setTimeout(r, 400));

    // Get total scroll height
    const dim = await send('Runtime.evaluate', {
      expression: 'document.documentElement.scrollHeight',
      returnByValue: true
    });
    const fullHeight = dim.result.value;

    await send('Emulation.setDeviceMetricsOverride', {
      width: 1440,
      height: fullHeight,
      deviceScaleFactor: 1,
      mobile: false
    });
    await new Promise(r => setTimeout(r, 400));

    const shot = await send('Page.captureScreenshot', {
      format: 'png',
      captureBeyondViewport: true,
      fromSurface: true
    });
    const testOut = path.join(ROOT, '..', '..', 'scratch', 'test_pub_10.png');
    fs.writeFileSync(testOut, Buffer.from(shot.data, 'base64'));
    console.log('Saved test standalone PUB-10 screenshot to:', testOut, 'Height:', fullHeight);

    ws.close();
    edgeProcess.kill();
    server.close();
    process.exit(0);
  } catch (err) {
    console.error(err);
    edgeProcess.kill();
    server.close();
    process.exit(1);
  }
});
