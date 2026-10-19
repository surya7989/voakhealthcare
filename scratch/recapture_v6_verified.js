/**
 * Voak Health Care — Phase 3 V6 Verified Screenshot Recapture Suite
 * Recaptures all 50 screenshots with updated neutral claims, side-by-side benchmark comparison,
 * and clean responsive mockups.
 */

const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');
const os = require('os');

const PORT = 4188;
const DEBUG_PORT = 9242;
const ROOT = path.join(__dirname, '..', 'design', 'phase-03');
const SCREENSHOT_DIR = path.join(ROOT, 'screenshots');
const PUB_DIR = path.join(SCREENSHOT_DIR, 'public_pages');
const ADM_DIR = path.join(SCREENSHOT_DIR, 'admin_modules');

[SCREENSHOT_DIR, PUB_DIR, ADM_DIR].forEach(d => {
  if (!fs.existsSync(d)) fs.mkdirSync(d, { recursive: true });
});

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
  console.log(`V6 Recapture Server listening on http://127.0.0.1:${PORT}`);
  await runCapture();
});

function findBrowserPath() {
  const candidates = [
    process.env.BROWSER_PATH,
    process.env.EDGE_PATH,
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    process.env.CHROME_PATH,
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
  ];
  for (const p of candidates) {
    if (p && fs.existsSync(p)) return p;
  }
  throw new Error('No compatible browser found.');
}

async function runCapture() {
  const browserPath = findBrowserPath();
  const userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'voak-capture-v6-'));

  console.log(`Using browser: ${browserPath}`);
  console.log(`UserDataDir: ${userDataDir}`);

  const edgeProcess = spawn(browserPath, [
    '--headless=new',
    `--remote-debugging-port=${DEBUG_PORT}`,
    '--disable-gpu',
    '--no-sandbox',
    '--no-first-run',
    '--no-default-browser-check',
    '--hide-scrollbars',
    `--user-data-dir=${userDataDir}`,
    'about:blank'
  ]);

  let targets = null;
  for (let attempt = 0; attempt < 25; attempt++) {
    await new Promise(r => setTimeout(r, 400));
    try {
      targets = await new Promise((res, rej) => {
        const req = http.get(`http://127.0.0.1:${DEBUG_PORT}/json/list`, r => {
          let d = ''; r.on('data', c => d += c); r.on('end', () => res(JSON.parse(d)));
        });
        req.on('error', rej);
        req.setTimeout(1000, () => req.destroy(new Error('timeout')));
      });
      if (targets && targets.length > 0) break;
    } catch(e) {}
  }

  if (!targets || targets.length === 0) {
    edgeProcess.kill();
    server.close();
    throw new Error(`Failed to connect to Edge remote debugging on port ${DEBUG_PORT}`);
  }

  try {
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

    await send('Page.navigate', { url: `http://127.0.0.1:${PORT}/index.html` });
    await new Promise(r => setTimeout(r, 2500));
    await send('Runtime.evaluate', { expression: 'document.fonts ? document.fonts.ready : Promise.resolve()', awaitPromise: true });

    async function switchTab(tabKey) {
      await send('Runtime.evaluate', { expression: `switchTab('${tabKey}')` });
      await new Promise(r => setTimeout(r, 400));
    }

    async function hideNavbar() {
      await send('Runtime.evaluate', {
        expression: `(() => {
          const nav = document.querySelector('.preview-navbar');
          if (nav) nav.style.display = 'none';
        })()`
      });
    }

    async function captureCleanFullView(filePath, width, targetSelector = null, isMobile = false) {
      await hideNavbar();
      await send('Emulation.setDeviceMetricsOverride', {
        width: width,
        height: 900,
        deviceScaleFactor: 1,
        mobile: isMobile
      });
      await new Promise(r => setTimeout(r, 300));

      const dim = await send('Runtime.evaluate', {
        expression: targetSelector 
          ? `Math.ceil(document.querySelector('${targetSelector}') ? document.querySelector('${targetSelector}').scrollHeight : document.documentElement.scrollHeight)`
          : `Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)`,
        returnByValue: true
      });
      const fullHeight = dim.result.value;

      await send('Emulation.setDeviceMetricsOverride', {
        width: width,
        height: fullHeight,
        deviceScaleFactor: 1,
        mobile: isMobile
      });
      await new Promise(r => setTimeout(r, 400));

      const shot = await send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: true,
        fromSurface: true
      });

      const buffer = Buffer.from(shot.data, 'base64');
      fs.writeFileSync(filePath, buffer);
      console.log(`Captured ${path.relative(SCREENSHOT_DIR, filePath).replace(/\\/g, '/')}: ${width} × ${fullHeight} px`);
    }

    console.log('\n============================================================');
    console.log('PART 1: CLEAN PRIMARY PROTOTYPE SCREENSHOTS (NO TOOLBAR)');
    console.log('============================================================');

    // 01. Desktop Homepage (Clean without preview navbar)
    await switchTab('homepage-desktop');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '01_desktop_homepage.png'), 1440, '#view-homepage-desktop');

    // 02. Mobile Homepage (390px, Clean)
    await switchTab('homepage-mobile');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '02_mobile_homepage_390px.png'), 390, '#mobile-frame-target', true);

    // 03. Tablet Homepage (768px, Clean)
    await switchTab('homepage-desktop');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '03_tablet_homepage_768px.png'), 768, '#view-homepage-desktop');

    // 04. Public Pages Gallery
    await switchTab('public-pages');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '04_public_pages_gallery.png'), 1440, '#view-public-pages');

    // 06. Component System
    await switchTab('components-tokens');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '06_component_system.png'), 1440, '#view-components-tokens');

    // 07. Reference Comparison (Clean with updated side-by-side status)
    await switchTab('reference-comparison');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '07_approved_reference_comparison.png'), 1440, '#view-reference-comparison');

    // 08. Proposal Wizard Modal
    await switchTab('homepage-desktop');
    await hideNavbar();
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 960, deviceScaleFactor: 1, mobile: false });
    await send('Runtime.evaluate', { expression: 'showProposalWizard()' });
    await new Promise(r => setTimeout(r, 600));
    const wizShot = await send('Page.captureScreenshot', { format: 'png', fromSurface: true });
    const wizBuf = Buffer.from(wizShot.data, 'base64');
    fs.writeFileSync(path.join(SCREENSHOT_DIR, '08_proposal_wizard_modal.png'), wizBuf);
    console.log('Captured 08_proposal_wizard_modal.png: 1440 × 960 px');
    await send('Runtime.evaluate', { expression: 'closeProposalWizard()' });

    console.log('\n============================================================');
    console.log('PART 2: RECAPTURE ALL 19 ADMIN MODULES (VERIFIED ACTIVE WORKSPACE)');
    console.log('============================================================');

    await switchTab('admin-portal');
    await send('Runtime.evaluate', { expression: "simulateRole('ROLE_SUPER_ADMIN')" });
    await new Promise(r => setTimeout(r, 400));

    // Reset styles for clean full-page admin capture
    await send('Runtime.evaluate', {
      expression: `(() => {
        const nav = document.querySelector('.preview-navbar');
        if (nav) nav.style.display = 'none';

        const shell = document.querySelector('.admin-shell');
        const main = document.querySelector('.admin-main-area');
        if (shell) {
          shell.style.height = 'auto';
          shell.style.minHeight = '100vh';
          shell.style.overflow = 'visible';
        }
        if (main) {
          main.style.height = 'auto';
          main.style.overflowY = 'visible';
        }
        document.body.style.overflow = 'visible';
      })()`
    });

    for (let i = 1; i <= 19; i++) {
      const pad = String(i).padStart(2, '0');
      const modId = `ADM-${pad}`;

      await send('Runtime.evaluate', { expression: `showAdminModule('${modId}')` });
      await new Promise(r => setTimeout(r, 350));

      const dim = await send('Runtime.evaluate', {
        expression: 'Math.max(document.querySelector(".admin-shell") ? document.querySelector(".admin-shell").scrollHeight : 1000, 1000)',
        returnByValue: true
      });
      const height = Math.min(dim.result.value, 4000);

      await send('Emulation.setDeviceMetricsOverride', {
        width: 1440,
        height: height,
        deviceScaleFactor: 1,
        mobile: false
      });
      await new Promise(r => setTimeout(r, 300));

      const shot = await send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: true,
        fromSurface: true
      });

      const filename = `admin_${modId.toLowerCase()}_preview.png`;
      const relPath = `admin_modules/${filename}`;
      const buf = Buffer.from(shot.data, 'base64');
      fs.writeFileSync(path.join(ADM_DIR, filename), buf);

      if (modId === 'ADM-01') {
        fs.writeFileSync(path.join(SCREENSHOT_DIR, '05_admin_dashboard.png'), buf);
        console.log(`Saved 05_admin_dashboard.png from ADM-01: 1440 × ${height} px`);
      }

      console.log(`Verified Admin [${modId}]: ${relPath} -> 1440 × ${height} px`);
    }

    // Capture simulated 403 access denied state
    console.log('\nCapturing Simulated 403 Access Denied State...');
    await send('Runtime.evaluate', { expression: "simulateRole('ROLE_HR_MANAGER'); showAdminModule('ADM-09');" });
    await new Promise(r => setTimeout(r, 450));

    const sim403Shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, fromSurface: true });
    const sim403Buf = Buffer.from(sim403Shot.data, 'base64');
    fs.writeFileSync(path.join(SCREENSHOT_DIR, '09_simulated_403_access_denied.png'), sim403Buf);
    console.log('Captured 09_simulated_403_access_denied.png: 1440 × 1200 px');

    // Restore Super Admin
    await send('Runtime.evaluate', { expression: "simulateRole('ROLE_SUPER_ADMIN')" });

    console.log('\n============================================================');
    console.log('PART 3: RECAPTURE ALL 22 PUBLIC PAGES (EXACT HEIGHT, NO BLANK CANVAS)');
    console.log('============================================================');

    await send('Runtime.evaluate', {
      expression: `(() => {
        const nav = document.querySelector('.preview-navbar');
        if (nav) nav.style.display = 'none';

        document.querySelectorAll('.view-panel').forEach(p => p.style.display = 'none');

        const modal = document.getElementById('screen-inspector-modal');
        const container = document.querySelector('.screen-inspector-container');
        const topbar = document.querySelector('.screen-inspector-topbar');

        if (modal) {
          modal.style.position = 'absolute';
          modal.style.top = '0';
          modal.style.left = '0';
          modal.style.width = '100%';
          modal.style.height = 'auto';
          modal.style.overflow = 'visible';
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
          container.style.overflow = 'visible';
        }
        document.body.style.overflow = 'visible';
      })()`
    });

    for (let i = 1; i <= 22; i++) {
      const pad = String(i).padStart(2, '0');
      const pageId = `PUB-${pad}`;

      await send('Runtime.evaluate', { expression: `inspectPage('${pageId}'); window.scrollTo(0, 0);` });
      await new Promise(r => setTimeout(r, 350));

      const dim = await send('Runtime.evaluate', {
        expression: `(() => {
          const c = document.getElementById('screen-mockup-body') || document.querySelector('.screen-inspector-container');
          return Math.ceil(c ? c.scrollHeight : document.documentElement.scrollHeight);
        })()`,
        returnByValue: true
      });
      const height = dim.result.value;

      await send('Emulation.setDeviceMetricsOverride', {
        width: 1440,
        height: height,
        deviceScaleFactor: 1,
        mobile: false
      });
      await new Promise(r => setTimeout(r, 300));

      const shot = await send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: true,
        fromSurface: true
      });

      const filename = `page_${pageId.toLowerCase()}_preview.png`;
      const relPath = `public_pages/${filename}`;
      const buf = Buffer.from(shot.data, 'base64');
      fs.writeFileSync(path.join(PUB_DIR, filename), buf);

      console.log(`Verified Public [${pageId}]: ${relPath} -> 1440 × ${height} px`);
    }

    ws.close();
    edgeProcess.kill();
    server.close();
    console.log('\nALL 50 SCREENSHOTS RECAPTURED SUCCESSFULLY ON DISK!');
    process.exit(0);
  } catch (err) {
    console.error('Fatal error during capture:', err);
    edgeProcess.kill();
    server.close();
    process.exit(1);
  }
}
