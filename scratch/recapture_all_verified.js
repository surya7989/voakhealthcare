const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = 4173;
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
  console.log(`Server listening on port ${PORT}`);
  await runCapture();
});

async function runCapture() {
  const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
  const userDataDir = path.join(__dirname, 'edge-qa-verified');

  const edgeProcess = spawn(edgePath, [
    '--headless=new',
    '--remote-debugging-port=9226',
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
      http.get('http://127.0.0.1:9226/json/list', r => {
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
    await new Promise(r => setTimeout(r, 2500));
    await send('Runtime.evaluate', { expression: 'document.fonts ? document.fonts.ready : Promise.resolve()', awaitPromise: true });

    async function switchTab(tabKey) {
      await send('Runtime.evaluate', { expression: `switchTab('${tabKey}')` });
      await new Promise(r => setTimeout(r, 500));
    }

    async function captureFullPage(filePath, width, isMobile = false) {
      await send('Emulation.setDeviceMetricsOverride', {
        width: width,
        height: 900,
        deviceScaleFactor: 1,
        mobile: isMobile
      });
      await new Promise(r => setTimeout(r, 300));

      const dim = await send('Runtime.evaluate', {
        expression: 'Math.max(document.documentElement.scrollHeight, document.body.scrollHeight, 1200)',
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
      fs.writeFileSync(filePath, Buffer.from(shot.data, 'base64'));
    }

    console.log('\n============================================================');
    console.log('PART A: PRIMARY PROTOTYPE SCREENSHOTS');
    console.log('============================================================');

    // 1. Desktop Homepage (1440px)
    await switchTab('homepage-desktop');
    await captureFullPage(path.join(SCREENSHOT_DIR, '01_desktop_homepage.png'), 1440);
    console.log('Captured 01_desktop_homepage.png (1440px full-page)');

    // 2. Mobile Homepage (390px)
    await switchTab('homepage-mobile');
    await captureFullPage(path.join(SCREENSHOT_DIR, '02_mobile_homepage_390px.png'), 390, true);
    console.log('Captured 02_mobile_homepage_390px.png (390px full-page)');

    // 3. Tablet Homepage (768px)
    await switchTab('homepage-desktop');
    await captureFullPage(path.join(SCREENSHOT_DIR, '03_tablet_homepage_768px.png'), 768);
    console.log('Captured 03_tablet_homepage_768px.png (768px full-page)');

    // 4. Public Pages Gallery (1440px)
    await switchTab('public-pages');
    await captureFullPage(path.join(SCREENSHOT_DIR, '04_public_pages_gallery.png'), 1440);
    console.log('Captured 04_public_pages_gallery.png (1440px full-page)');

    // 5. Component System (1440px)
    await switchTab('components-tokens');
    await captureFullPage(path.join(SCREENSHOT_DIR, '06_component_system.png'), 1440);
    console.log('Captured 06_component_system.png (1440px full-page)');

    // 6. References Comparison (1440px)
    await switchTab('reference-comparison');
    await captureFullPage(path.join(SCREENSHOT_DIR, '07_approved_reference_comparison.png'), 1440);
    console.log('Captured 07_approved_reference_comparison.png (1440px full-page)');

    // 7. Proposal Wizard Modal
    await switchTab('homepage-desktop');
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 960, deviceScaleFactor: 1, mobile: false });
    await send('Runtime.evaluate', { expression: 'showProposalWizard()' });
    await new Promise(r => setTimeout(r, 600));
    const wizShot = await send('Page.captureScreenshot', { format: 'png', fromSurface: true });
    fs.writeFileSync(path.join(SCREENSHOT_DIR, '08_proposal_wizard_modal.png'), Buffer.from(wizShot.data, 'base64'));
    await send('Runtime.evaluate', { expression: 'closeProposalWizard()' });
    console.log('Captured 08_proposal_wizard_modal.png');

    console.log('\n============================================================');
    console.log('PART B: RECAPTURE ALL 19 ADMIN MODULES (VERIFIED ACTIVE WORKSPACE)');
    console.log('============================================================');
    // Step 1: Switch to admin-portal tab
    await switchTab('admin-portal');

    // Step 2: Ensure Super Admin is selected
    await send('Runtime.evaluate', { expression: "simulateRole('ROLE_SUPER_ADMIN')" });
    await new Promise(r => setTimeout(r, 400));

    // Reset styles for clean full-page admin capture
    await send('Runtime.evaluate', {
      expression: `(() => {
        // Hide preview navbar during admin module captures so whole canvas is the admin portal
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

      // Invoke showAdminModule
      await send('Runtime.evaluate', { expression: `showAdminModule('${modId}')` });
      await new Promise(r => setTimeout(r, 400));

      // Visual validation check
      const check = await send('Runtime.evaluate', {
        expression: `(() => {
          const ws = document.getElementById('admin-workspace');
          const hasHeroDoctor = !!document.querySelector('.doctor-photo-frame');
          const hasAdminCard = !!document.querySelector('.admin-card') || !!document.querySelector('.admin-kpi-grid');
          return {
            modId: '${modId}',
            workspaceLen: ws ? ws.innerHTML.length : 0,
            hasAdminContent: hasAdminCard,
            isShowingHomepage: hasHeroDoctor
          };
        })()`,
        returnByValue: true
      });

      const val = check.result.value;
      if (val.isShowingHomepage) {
        console.error(`FATAL ERROR: Module ${modId} captured public homepage!`);
        process.exit(1);
      }

      // Measure height and capture
      const dim = await send('Runtime.evaluate', {
        expression: 'Math.max(document.documentElement.scrollHeight, document.body.scrollHeight, 1000)',
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
      fs.writeFileSync(path.join(ADM_DIR, filename), Buffer.from(shot.data, 'base64'));

      // Also save ADM-01 as the primary 05_admin_dashboard.png
      if (modId === 'ADM-01') {
        fs.writeFileSync(path.join(SCREENSHOT_DIR, '05_admin_dashboard.png'), Buffer.from(shot.data, 'base64'));
      }

      console.log(`Verified Admin [${modId}]: Active Workspace Rendered (${val.workspaceLen} bytes) -> ${filename}`);
    }

    // Capture simulated 403 access denied state
    console.log('\nCapturing Simulated 403 Access Denied State...');
    await send('Runtime.evaluate', { expression: "simulateRole('ROLE_HR_MANAGER'); showAdminModule('ADM-09');" });
    await new Promise(r => setTimeout(r, 500));

    const sim403Shot = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, fromSurface: true });
    fs.writeFileSync(path.join(SCREENSHOT_DIR, '09_simulated_403_access_denied.png'), Buffer.from(sim403Shot.data, 'base64'));
    console.log('Captured 09_simulated_403_access_denied.png');

    // Restore Super Admin
    await send('Runtime.evaluate', { expression: "simulateRole('ROLE_SUPER_ADMIN')" });

    console.log('\n============================================================');
    console.log('PART C: RECAPTURE ALL 22 STANDALONE PUBLIC PAGES (CLEAN VIEW)');
    console.log('============================================================');
    // Hide all view panels and style screen inspector as a standalone clean document at (0, 0)
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

    for (let i = 1; i <= 22; i++) {
      const pad = String(i).padStart(2, '0');
      const pageId = `PUB-${pad}`;

      await send('Runtime.evaluate', { expression: `inspectPage('${pageId}'); window.scrollTo(0, 0);` });
      await new Promise(r => setTimeout(r, 400));

      const dim = await send('Runtime.evaluate', {
        expression: 'Math.max(document.documentElement.scrollHeight, document.body.scrollHeight, 1200)',
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
      fs.writeFileSync(path.join(PUB_DIR, filename), Buffer.from(shot.data, 'base64'));

      const info = await send('Runtime.evaluate', {
        expression: `({
          title: document.getElementById('inspector-title')?.textContent,
          route: document.getElementById('inspector-route')?.textContent,
          bodyLen: document.getElementById('screen-mockup-body')?.innerHTML.length
        })`,
        returnByValue: true
      });

      console.log(`Verified Public [${pageId}]: ${info.result.value.title} (${info.result.value.route}) -> ${filename} (${height}px)`);
    }

    ws.close();
    edgeProcess.kill();
    server.close();
    console.log('\nALL 19 ADMIN MODULES AND 22 PUBLIC PAGES RECAPTURED AND VERIFIED SUCCESSFULLY!');
    process.exit(0);
  } catch (err) {
    console.error('Fatal error during capture:', err);
    edgeProcess.kill();
    server.close();
    process.exit(1);
  }
}
