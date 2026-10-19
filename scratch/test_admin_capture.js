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
  console.log('Server started on port 4173');
  const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
  const userDataDir = path.join(__dirname, 'edge-test-admin');

  const edgeProcess = spawn(edgePath, [
    '--headless=new',
    '--remote-debugging-port=9224',
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
      http.get('http://127.0.0.1:9224/json/list', r => {
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
    await send('DOM.enable');

    await send('Page.navigate', { url: 'http://127.0.0.1:4173/index.html' });
    await new Promise(r => setTimeout(r, 2000));

    // STEP 3: Switch to admin-portal tab using data-tab="admin-portal"
    console.log('Switching to admin-portal...');
    await send('Runtime.evaluate', { expression: "switchTab('admin-portal')" });
    await new Promise(r => setTimeout(r, 500));

    // STEP 4: Verify admin panel is visible
    const checkPanel = await send('Runtime.evaluate', {
      expression: `(() => {
        const p = document.getElementById('view-admin-portal');
        return {
          exists: !!p,
          isActive: p ? p.classList.contains('active') : false,
          display: p ? window.getComputedStyle(p).display : 'none'
        };
      })()`,
      returnByValue: true
    });
    console.log('Admin panel visibility:', checkPanel.result.value);

    // STEP 5: Select Super Admin simulator role
    await send('Runtime.evaluate', { expression: "simulateRole('ROLE_SUPER_ADMIN')" });
    await new Promise(r => setTimeout(r, 300));

    // STEP 6: Invoke showAdminModule('ADM-01')
    await send('Runtime.evaluate', { expression: "showAdminModule('ADM-01')" });
    await new Promise(r => setTimeout(r, 500));

    // STEP 7: Verify displayed module title matches ADM-01
    const titleCheck = await send('Runtime.evaluate', {
      expression: `(() => {
        const titleEl = document.getElementById('admin-module-title');
        const workspace = document.getElementById('admin-workspace');
        return {
          title: titleEl ? titleEl.textContent : 'none',
          workspaceHtmlLen: workspace ? workspace.innerHTML.length : 0,
          kpisPresent: !!document.querySelector('.admin-kpi-grid')
        };
      })()`,
      returnByValue: true
    });
    console.log('Module check:', titleCheck.result.value);

    // STEP 8: Set viewport and capture
    await send('Emulation.setDeviceMetricsOverride', {
      width: 1440,
      height: 1000,
      deviceScaleFactor: 1,
      mobile: false
    });
    await new Promise(r => setTimeout(r, 400));

    const shot = await send('Page.captureScreenshot', {
      format: 'png',
      fromSurface: true
    });
    const testOut = path.join(ROOT, '..', '..', 'scratch', 'test_adm_01.png');
    fs.writeFileSync(testOut, Buffer.from(shot.data, 'base64'));
    console.log('Saved test admin screenshot to:', testOut);

    ws.close();
    edgeProcess.kill();
    server.close();
    process.exit(0);
  } catch (err) {
    console.error('Error:', err);
    edgeProcess.kill();
    server.close();
    process.exit(1);
  }
});
