/**
 * Voak Health Care — Phase 3 V10 Coordinated Screenshot Recapture Engine
 *
 * Captures all 50 screenshots using Edge CDP with:
 * 1. Explicit 2-phase viewport reflow (set width -> wait reflow -> measure scrollHeight -> set fullHeight -> capture)
 * 2. Binary PNG buffer inspection (readUInt32BE(16/20)) to ensure 100% agreement between manifest and actual disk files
 * 3. Simultaneous generation of tests/screenshot_capture_manifest.json and tests/screenshot_metrics.json
 * 4. Documented legitimate duplicate: 05_admin_dashboard.png === admin_modules/admin_adm-01_preview.png (ADM-01)
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { spawn } = require('child_process');

const PORT = 4210;
const DEBUG_PORT = 9270;
const ROOT = path.join(__dirname, '..', 'design', 'phase-03');
const SCREENSHOT_DIR = path.join(ROOT, 'screenshots');
const ADM_DIR = path.join(SCREENSHOT_DIR, 'admin_modules');
const PUB_DIR = path.join(SCREENSHOT_DIR, 'public_pages');

fs.mkdirSync(ADM_DIR, { recursive: true });
fs.mkdirSync(PUB_DIR, { recursive: true });

const MIME = {
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
    res.writeHead(404); res.end('Not found'); return;
  }
  const ext = path.extname(filePath).toLowerCase();
  res.writeHead(200, { 'Content-Type': MIME[ext] || 'application/octet-stream' });
  fs.createReadStream(filePath).pipe(res);
});

function findBrowser() {
  const candidates = [
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe'
  ];
  for (const c of candidates) {
    if (fs.existsSync(c)) return c;
  }
  return null;
}

server.listen(PORT, '127.0.0.1', async () => {
  console.log(`\n============================================================`);
  console.log(`VOAK HEALTH CARE — PHASE 3 V10 COORDINATED SCREENSHOT ENGINE`);
  console.log(`Server listening on http://127.0.0.1:${PORT}`);
  console.log(`============================================================`);

  const browserPath = findBrowser();
  if (!browserPath) {
    server.close();
    throw new Error('No supported browser found for screenshot capture.');
  }

  const userDataDir = path.join(__dirname, 'edge-qa-v10');
  if (!fs.existsSync(userDataDir)) fs.mkdirSync(userDataDir, { recursive: true });

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
  for (let attempt = 0; attempt < 30; attempt++) {
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
    throw new Error('Could not connect to Edge DevTools Protocol on port ' + DEBUG_PORT);
  }

  const pageTarget = targets.find(t => t.type === 'page') || targets[0];
  const wsUrl = pageTarget.webSocketDebuggerUrl;

  const ws = (typeof WebSocket !== 'undefined') ? new WebSocket(wsUrl) : new (require('ws'))(wsUrl);
  await new Promise(r => ws.onopen = r);

  let id = 1;
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    const curId = id++;
    const handler = (evt) => {
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

  await send('Page.enable');
  await send('DOM.enable');
  await send('Runtime.enable');

  await send('Page.navigate', { url: `http://127.0.0.1:${PORT}/index.html` });
  await new Promise(r => setTimeout(r, 2000));

  console.log('Page loaded successfully. Starting coordinated 50-screenshot sequence...\n');

  const captureManifest = {};
  const metricsData = {};

  try {
    const hideNavbar = async () => {
      await send('Runtime.evaluate', {
        expression: `(() => {
          const nav = document.querySelector('.preview-navbar');
          if (nav) nav.style.display = 'none';
        })()`
      });
    };

    const switchTab = async (tabName) => {
      await send('Runtime.evaluate', {
        expression: `(() => {
          if (typeof switchTab === 'function') switchTab('${tabName}');
          const nav = document.querySelector('.preview-navbar');
          if (nav) nav.style.display = 'none';
        })()`
      });
      await new Promise(r => setTimeout(r, 450));
    };

    // Coordinated Reflow & Measurement Capture
    async function captureCleanFullView(filePath, relPath, width, targetSelector = null, isMobile = false, duplicateAlias = null) {
      await hideNavbar();

      // Step 1: Set viewport width to trigger real browser CSS reflow
      await send('Emulation.setDeviceMetricsOverride', {
        width: width,
        height: 800,
        deviceScaleFactor: 1.0,
        mobile: isMobile
      });
      await new Promise(r => setTimeout(r, 350));

      // Step 2: Measure real post-reflow content height
      const dim = await send('Runtime.evaluate', {
        expression: targetSelector
          ? `Math.ceil(document.querySelector('${targetSelector}') ? document.querySelector('${targetSelector}').scrollHeight : document.documentElement.scrollHeight)`
          : `Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)`,
        returnByValue: true
      });
      const measuredHeight = dim.result.value;

      // Step 3: Set viewport height to measured height
      await send('Emulation.setDeviceMetricsOverride', {
        width: width,
        height: measuredHeight,
        deviceScaleFactor: 1.0,
        mobile: isMobile
      });
      await new Promise(r => setTimeout(r, 350));

      // Step 4: Capture screenshot
      const shot = await send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: true,
        fromSurface: true
      });

      const buffer = Buffer.from(shot.data, 'base64');
      fs.writeFileSync(filePath, buffer);

      // Step 5: Read true IHDR from binary PNG buffer
      const pngWidth = buffer.readUInt32BE(16);
      const pngHeight = buffer.readUInt32BE(20);
      const sha256 = crypto.createHash('sha256').update(buffer).digest('hex');
      const sizeBytes = buffer.length;
      const sizeKb = (sizeBytes / 1024).toFixed(1);

      const record = {
        viewport: {
          cssWidth: width,
          cssHeight: pngHeight,
          deviceScaleFactor: 1.0,
          mobile: isMobile
        },
        ihdr: {
          width: pngWidth,
          height: pngHeight
        },
        sizeBytes: sizeBytes,
        sizeKb: sizeKb,
        sha256: sha256,
        targetSelector: targetSelector,
        timestamp: new Date().toISOString()
      };

      captureManifest[relPath] = record;
      metricsData[relPath] = {
        width: pngWidth,
        height: pngHeight,
        sizeBytes: sizeBytes,
        sizeKb: sizeKb,
        sha256: sha256
      };

      console.log(`[CAPTURED] ${relPath.padEnd(42)} | IHDR: ${pngWidth}×${pngHeight} px | ${sizeKb} KB | SHA: ${sha256.slice(0, 10)}...`);

      if (duplicateAlias) {
        const dupPath = path.join(SCREENSHOT_DIR, duplicateAlias);
        fs.writeFileSync(dupPath, buffer);
        captureManifest[duplicateAlias] = {
          ...record,
          duplicateOf: relPath,
          rationale: "Legitimate duplicate: 05_admin_dashboard.png is the canonical overview screenshot of ADM-01 Executive Dashboard"
        };
        metricsData[duplicateAlias] = {
          width: pngWidth,
          height: pngHeight,
          sizeBytes: sizeBytes,
          sizeKb: sizeKb,
          sha256: sha256
        };
        console.log(`[CAPTURED] ${duplicateAlias.padEnd(42)} | (Legitimate alias of ${relPath})`);
      }
    }

    console.log('\n--- PART 1: PRIMARY OVERVIEW SCREENSHOTS ---');

    // 01. Desktop Homepage
    await switchTab('homepage-desktop');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '01_desktop_homepage.png'), '01_desktop_homepage.png', 1440, '#view-homepage-desktop');

    // 02. Mobile Homepage (390px)
    await switchTab('homepage-mobile');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '02_mobile_homepage_390px.png'), '02_mobile_homepage_390px.png', 390, '#mobile-frame-target', true);

    // 03. Tablet Homepage (768px)
    await switchTab('homepage-desktop');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '03_tablet_homepage_768px.png'), '03_tablet_homepage_768px.png', 768, '#view-homepage-desktop', true);

    // 04. Public Pages Gallery
    await switchTab('public-pages');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '04_public_pages_gallery.png'), '04_public_pages_gallery.png', 1440, '#view-public-pages');

    // 06. Component System
    await switchTab('components-tokens');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '06_component_system.png'), '06_component_system.png', 1440, '#view-components-tokens');

    // 07. Reference Comparison
    await switchTab('reference-comparison');
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '07_approved_reference_comparison.png'), '07_approved_reference_comparison.png', 1440, '#view-reference-comparison');

    // 08. Proposal Wizard Modal
    await switchTab('homepage-desktop');
    await hideNavbar();
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 960, deviceScaleFactor: 1.0, mobile: false });
    await send('Runtime.evaluate', { expression: 'showProposalWizard()' });
    await new Promise(r => setTimeout(r, 600));
    const wizShot = await send('Page.captureScreenshot', { format: 'png', fromSurface: true });
    const wizBuf = Buffer.from(wizShot.data, 'base64');
    fs.writeFileSync(path.join(SCREENSHOT_DIR, '08_proposal_wizard_modal.png'), wizBuf);
    const wizW = wizBuf.readUInt32BE(16);
    const wizH = wizBuf.readUInt32BE(20);
    const wizSha = crypto.createHash('sha256').update(wizBuf).digest('hex');
    const wizKb = (wizBuf.length / 1024).toFixed(1);
    captureManifest['08_proposal_wizard_modal.png'] = {
      viewport: { cssWidth: 1440, cssHeight: 960, deviceScaleFactor: 1.0, mobile: false },
      ihdr: { width: wizW, height: wizH },
      sizeBytes: wizBuf.length,
      sizeKb: wizKb,
      sha256: wizSha,
      targetSelector: '#proposal-wizard-modal',
      timestamp: new Date().toISOString()
    };
    metricsData['08_proposal_wizard_modal.png'] = {
      width: wizW,
      height: wizH,
      sizeBytes: wizBuf.length,
      sizeKb: wizKb,
      sha256: wizSha
    };
    console.log(`[CAPTURED] 08_proposal_wizard_modal.png         | IHDR: ${wizW}×${wizH} px | ${wizKb} KB`);
    await send('Runtime.evaluate', { expression: 'closeProposalWizard()' });

    console.log('\n--- PART 2: ADMIN MODULE WORKSPACES (19 MODULES) ---');
    await switchTab('admin-portal');
    await send('Runtime.evaluate', { expression: "simulateRole('ROLE_SUPER_ADMIN')" });
    await new Promise(r => setTimeout(r, 400));

    // Reset styles for clean full-page admin capture
    await send('Runtime.evaluate', {
      expression: `(() => {
        const nav = document.querySelector('.preview-navbar');
        if (nav) nav.style.display = 'none';

        const shell = document.querySelector('.admin-shell');
        if (shell) {
          shell.style.height = 'auto';
          shell.style.overflow = 'visible';
        }
        const ws = document.querySelector('.admin-workspace');
        if (ws) {
          ws.style.height = 'auto';
          ws.style.overflow = 'visible';
        }
        const main = document.querySelector('.admin-main');
        if (main) {
          main.style.height = 'auto';
          main.style.overflow = 'visible';
        }
      })()`
    });

    for (let i = 1; i <= 19; i++) {
      const pad = String(i).padStart(2, '0');
      const modId = `ADM-${pad}`;

      await send('Runtime.evaluate', { expression: `showAdminModule('${modId}'); window.scrollTo(0, 0);` });
      await new Promise(r => setTimeout(r, 350));

      const filename = `admin_adm-${pad}_preview.png`;
      const relPath = `admin_modules/${filename}`;
      const duplicateAlias = (i === 1) ? '05_admin_dashboard.png' : null;

      await captureCleanFullView(path.join(ADM_DIR, filename), relPath, 1440, '.admin-shell', false, duplicateAlias);
    }

    console.log('\nCapturing Simulated 403 Access Denied State...');
    await send('Runtime.evaluate', { expression: "simulateRole('ROLE_HR_MANAGER'); showAdminModule('ADM-09');" });
    await new Promise(r => setTimeout(r, 450));
    await captureCleanFullView(path.join(SCREENSHOT_DIR, '09_simulated_403_access_denied.png'), '09_simulated_403_access_denied.png', 1440, '.admin-shell', false);

    // Restore Super Admin
    await send('Runtime.evaluate', { expression: "simulateRole('ROLE_SUPER_ADMIN')" });

    console.log('\n--- PART 3: PUBLIC PAGE MOCKUPS (22 PAGES) ---');
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
          modal.style.zIndex = '99999';
          modal.classList.add('active');
        }
        if (container) {
          container.style.maxWidth = '1440px';
          container.style.width = '1440px';
          container.style.height = 'auto';
          container.style.border = 'none';
          container.style.margin = '0';
          container.style.overflow = 'visible';
          container.style.boxShadow = 'none';
        }
        if (topbar) topbar.style.display = 'none';
        document.body.style.overflow = 'visible';
      })()`
    });

    for (let i = 1; i <= 22; i++) {
      const pad = String(i).padStart(2, '0');
      const pageId = `PUB-${pad}`;

      await send('Runtime.evaluate', { expression: `inspectPage('${pageId}'); window.scrollTo(0, 0);` });
      await new Promise(r => setTimeout(r, 350));

      const filename = `page_${pageId.toLowerCase()}_preview.png`;
      const relPath = `public_pages/${filename}`;

      await captureCleanFullView(path.join(PUB_DIR, filename), relPath, 1440, '#screen-mockup-body', false);
    }

    // Save JSON records
    const manifestPath = path.join(__dirname, '..', 'tests', 'screenshot_capture_manifest.json');
    const metricsPath = path.join(__dirname, '..', 'tests', 'screenshot_metrics.json');

    fs.writeFileSync(manifestPath, JSON.stringify(captureManifest, null, 2));
    fs.writeFileSync(metricsPath, JSON.stringify(metricsData, null, 2));

    console.log('\n============================================================');
    console.log(`SUCCESS: All 50 screenshots captured and coordinated!`);
    console.log(`Manifest written to: ${manifestPath} (${Object.keys(captureManifest).length} entries)`);
    console.log(`Metrics written to:  ${metricsPath} (${Object.keys(metricsData).length} entries)`);
    console.log('============================================================');

    ws.close();
  } catch(err) {
    console.error('Recapture Fatal Error:', err);
  } finally {
    edgeProcess.kill();
    server.close();
    process.exit(0);
  }
});
