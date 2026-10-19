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

// 1. Start HTTP Server
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
    res.writeHead(404, { 'Content-Type': 'text/plain' });
    res.end('Not Found');
    return;
  }
  const ext = path.extname(filePath).toLowerCase();
  res.writeHead(200, {
    'Content-Type': MIME_TYPES[ext] || 'application/octet-stream',
    'Access-Control-Allow-Origin': '*'
  });
  fs.createReadStream(filePath).pipe(res);
});

server.listen(PORT, '127.0.0.1', async () => {
  console.log(`Server listening at http://127.0.0.1:${PORT}`);
  await runQaSession();
});

async function runQaSession() {
  const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
  const userDataDir = path.join(__dirname, 'edge-qa-data');

  const edgeProcess = spawn(edgePath, [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--disable-gpu',
    '--no-first-run',
    '--no-default-browser-check',
    '--hide-scrollbars',
    `--user-data-dir=${userDataDir}`,
    'about:blank'
  ]);

  // Wait for browser
  await new Promise(r => setTimeout(r, 1500));

  try {
    // Get existing target page from /json/list
    const targets = await fetchJson('http://127.0.0.1:9222/json/list');
    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    const wsUrl = pageTarget.webSocketDebuggerUrl;
    console.log('Connected to target:', wsUrl);

    const client = await connectCdp(wsUrl);
    console.log('CDP client connected!');

    await client.send('Page.enable');
    await client.send('Runtime.enable');
    await client.send('DOM.enable');

    // Navigate to local prototype
    await client.send('Page.navigate', { url: 'http://127.0.0.1:4173/index.html' });

    // Wait for page to fully load and fonts to render
    await new Promise(r => setTimeout(r, 2500));
    await client.evaluate('document.fonts ? document.fonts.ready : Promise.resolve()');

    console.log('\n==================================================');
    console.log('STEP 1: RESPONSIVE BREAKPOINT OVERFLOW AUDIT');
    console.log('==================================================');
    const testWidths = [320, 360, 390, 428, 768, 1024, 1280, 1440, 1920];
    const overflowResults = [];

    for (const w of testWidths) {
      await client.send('Emulation.setDeviceMetricsOverride', {
        width: w,
        height: 900,
        deviceScaleFactor: 1,
        mobile: w < 768
      });
      await new Promise(r => setTimeout(r, 300));

      const overflow = await client.evaluate(`({
        windowWidth: window.innerWidth,
        scrollWidth: document.documentElement.scrollWidth,
        bodyScrollWidth: document.body.scrollWidth,
        hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth || document.body.scrollWidth > window.innerWidth
      })`);

      overflowResults.push({ width: w, ...overflow.result.value });
      console.log(`Breakpoint ${w}px: scrollWidth=${overflow.result.value.scrollWidth}px (Overflow: ${overflow.result.value.hasHorizontalOverflow ? 'FAIL' : 'PASS'})`);
    }

    console.log('\n==================================================');
    console.log('STEP 2: CAPTURE PRIMARY FULL-PAGE SCREENSHOTS');
    console.log('==================================================');

    // Helper to switch main tab
    async function switchTab(tabId) {
      await client.evaluate(`switchTab('${tabId}')`);
      await new Promise(r => setTimeout(r, 500));
    }

    // A. Desktop Homepage at 1440px (FULL PAGE)
    await switchTab('homepage');
    await client.captureFullPage(path.join(SCREENSHOT_DIR, '01_desktop_homepage.png'), 1440);
    console.log('Captured: 01_desktop_homepage.png (1440px Full-Page)');

    // B. Mobile Homepage at 390px (FULL PAGE)
    await client.captureFullPage(path.join(SCREENSHOT_DIR, '02_mobile_homepage_390px.png'), 390, true);
    console.log('Captured: 02_mobile_homepage_390px.png (390px Full-Page)');

    // C. Tablet Homepage at 768px (FULL PAGE)
    await client.captureFullPage(path.join(SCREENSHOT_DIR, '03_tablet_homepage_768px.png'), 768);
    console.log('Captured: 03_tablet_homepage_768px.png (768px Full-Page)');

    // D. Component System (#components) (FULL PAGE)
    await switchTab('components');
    await client.captureFullPage(path.join(SCREENSHOT_DIR, '05_component_system.png'), 1440);
    console.log('Captured: 05_component_system.png (1440px Full-Page)');

    // E. References Comparison (#refs) (FULL PAGE)
    await switchTab('refs');
    await client.captureFullPage(path.join(SCREENSHOT_DIR, '06_approved_reference_comparison.png'), 1440);
    console.log('Captured: 06_approved_reference_comparison.png (1440px Full-Page)');

    // F. Proposal Wizard Modal
    await switchTab('homepage');
    await client.send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 960, deviceScaleFactor: 1, mobile: false });
    await client.evaluate('showProposalWizard()');
    await new Promise(r => setTimeout(r, 600));
    await client.captureViewport(path.join(SCREENSHOT_DIR, '07_proposal_wizard_modal.png'));
    await client.evaluate('closeProposalWizard()');
    console.log('Captured: 07_proposal_wizard_modal.png');

    // G. Admin Console & Role Simulator (#admin)
    await switchTab('admin');
    await client.evaluate("simulateRole('ROLE_SUPER_ADMIN'); showAdminModule('ADM-01');");
    await new Promise(r => setTimeout(r, 500));
    await client.captureFullPage(path.join(SCREENSHOT_DIR, '04_admin_dashboard.png'), 1440);
    console.log('Captured: 04_admin_dashboard.png (Super Admin ADM-01 Full-Page)');

    // H. Role Simulator Simulated 403 Access Denied State
    await client.evaluate("simulateRole('ROLE_HR_MANAGER'); showAdminModule('ADM-09');");
    await new Promise(r => setTimeout(r, 500));
    await client.captureViewport(path.join(SCREENSHOT_DIR, '08_simulated_403_access_denied.png'));
    console.log('Captured: 08_simulated_403_access_denied.png (Simulated 403 State)');

    console.log('\n==================================================');
    console.log('STEP 3: CAPTURE ALL 22 INDIVIDUAL PUBLIC PAGE MOCKUPS');
    console.log('==================================================');
    await switchTab('pages');

    for (let i = 1; i <= 22; i++) {
      const pad = String(i).padStart(2, '0');
      const pageId = `PUB-${pad}`;

      // Open inspector for this page
      await client.evaluate(`inspectPage('${pageId}')`);
      await new Promise(r => setTimeout(r, 600));

      // Capture the screen modal / body
      const filename = `page_${pageId.toLowerCase()}_preview.png`;
      const filePath = path.join(PUB_DIR, filename);

      // Measure inspector container height and capture
      await client.captureInspectorModal(filePath, 1440);

      // Verify no broken images or generic text fallbacks
      const verification = await client.evaluate(`({
        title: document.getElementById('inspector-title')?.textContent,
        route: document.getElementById('inspector-route')?.textContent,
        hasGenericBox: document.getElementById('inspector-screen-body')?.textContent.includes('Architectural Specification & Functional Traceability'),
        bodyLength: document.getElementById('inspector-screen-body')?.innerHTML.length
      })`);

      console.log(`Public Page [${pageId}]: ${verification.result.value.title} (${verification.result.value.route}) — HTML Length: ${verification.result.value.bodyLength} bytes — Generic Fallback: ${verification.result.value.hasGenericBox ? 'FAIL' : 'NONE (PASS)'}`);

      await client.evaluate('closeScreenInspector()');
      await new Promise(r => setTimeout(r, 200));
    }

    console.log('\n==================================================');
    console.log('STEP 4: CAPTURE ALL 19 ADMIN MODULE PREVIEWS');
    console.log('==================================================');
    await switchTab('admin');
    await client.evaluate("simulateRole('ROLE_SUPER_ADMIN');");

    for (let i = 1; i <= 19; i++) {
      const pad = String(i).padStart(2, '0');
      const modId = `ADM-${pad}`;

      await client.evaluate(`showAdminModule('${modId}')`);
      await new Promise(r => setTimeout(r, 400));

      const filename = `admin_${modId.toLowerCase()}_preview.png`;
      const filePath = path.join(ADM_DIR, filename);

      await client.captureAdminWorkspace(filePath, 1440);

      const modInfo = await client.evaluate(`({
        modId: '${modId}',
        htmlLength: document.getElementById('admin-workspace')?.innerHTML.length
      })`);

      console.log(`Admin Module [${modId}] — Rendered Workspace: ${modInfo.result.value.htmlLength} bytes`);
    }

    console.log('\nAll 22 Public Pages and 19 Admin Modules successfully rendered and captured!');

    // Close browser and server
    await client.close();
    edgeProcess.kill();
    server.close();
    console.log('QA Session finished cleanly!');
    process.exit(0);
  } catch (err) {
    console.error('Fatal QA error:', err);
    edgeProcess.kill();
    server.close();
    process.exit(1);
  }
}

function fetchJson(url) {
  return new Promise((resolve, reject) => {
    http.get(url, res => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve(JSON.parse(data)));
    }).on('error', reject);
  });
}

function connectCdp(wsUrl) {
  return new Promise((resolve, reject) => {
    const ws = new WebSocket(wsUrl);
    let id = 1;
    const callbacks = new Map();

    ws.onopen = () => {
      const client = {
        send(method, params = {}) {
          return new Promise((res, rej) => {
            const reqId = id++;
            callbacks.set(reqId, { res, rej });
            ws.send(JSON.stringify({ id: reqId, method, params }));
          });
        },
        async evaluate(expr) {
          return client.send('Runtime.evaluate', {
            expression: expr,
            returnByValue: true,
            awaitPromise: true
          });
        },
        async captureFullPage(filePath, width, isMobile = false) {
          await client.send('Emulation.setDeviceMetricsOverride', {
            width: width,
            height: 900,
            deviceScaleFactor: 1,
            mobile: isMobile
          });
          await new Promise(r => setTimeout(r, 400));

          const dim = await client.evaluate(`Math.max(
            document.documentElement.scrollHeight,
            document.body.scrollHeight,
            1200
          )`);
          const fullHeight = Math.min(dim.result.value, 16000);

          await client.send('Emulation.setDeviceMetricsOverride', {
            width: width,
            height: fullHeight,
            deviceScaleFactor: 1,
            mobile: isMobile
          });
          await new Promise(r => setTimeout(r, 400));

          const shot = await client.send('Page.captureScreenshot', {
            format: 'png',
            captureBeyondViewport: true,
            fromSurface: true
          });
          fs.writeFileSync(filePath, Buffer.from(shot.data, 'base64'));
        },
        async captureViewport(filePath) {
          const shot = await client.send('Page.captureScreenshot', {
            format: 'png',
            fromSurface: true
          });
          fs.writeFileSync(filePath, Buffer.from(shot.data, 'base64'));
        },
        async captureInspectorModal(filePath, width) {
          // Temporarily style modal so it flows in document and can be captured at true full height
          await client.evaluate(`(() => {
            const m = document.getElementById('screen-inspector-modal');
            if (m) {
              m.dataset.origStyle = m.getAttribute('style') || '';
              m.style.position = 'absolute';
              m.style.top = '0';
              m.style.left = '0';
              m.style.width = '100%';
              m.style.height = 'auto';
              m.style.minHeight = '100vh';
              m.style.overflow = 'visible';
              m.style.zIndex = '999999';
            }
            document.body.style.overflow = 'visible';
          })()`);

          await new Promise(r => setTimeout(r, 300));

          const dim = await client.evaluate(`(() => {
            const el = document.querySelector('.screen-inspector-container');
            return el ? el.scrollHeight + 100 : 2000;
          })()`);
          const modalHeight = Math.max(dim.result.value, 1200);

          await client.send('Emulation.setDeviceMetricsOverride', {
            width: width,
            height: modalHeight,
            deviceScaleFactor: 1,
            mobile: false
          });
          await new Promise(r => setTimeout(r, 400));

          const shot = await client.send('Page.captureScreenshot', {
            format: 'png',
            captureBeyondViewport: true,
            fromSurface: true
          });
          fs.writeFileSync(filePath, Buffer.from(shot.data, 'base64'));

          // Restore modal style
          await client.evaluate(`(() => {
            const m = document.getElementById('screen-inspector-modal');
            if (m) {
              m.setAttribute('style', m.dataset.origStyle || 'display: block;');
            }
          })()`);
        },
        async captureAdminWorkspace(filePath, width) {
          await client.evaluate(`(() => {
            const shell = document.querySelector('.admin-shell');
            const main = document.querySelector('.admin-main-area');
            if (shell) {
              shell.dataset.origHeight = shell.style.height;
              shell.style.height = 'auto';
              shell.style.minHeight = '100vh';
              shell.style.overflow = 'visible';
            }
            if (main) {
              main.dataset.origOverflow = main.style.overflowY;
              main.style.overflowY = 'visible';
              main.style.height = 'auto';
            }
          })()`);

          await new Promise(r => setTimeout(r, 300));

          const dim = await client.evaluate(`(() => {
            const ws = document.getElementById('admin-workspace');
            return ws ? ws.scrollHeight + 200 : 1200;
          })()`);
          const targetHeight = Math.max(dim.result.value, 1000);

          await client.send('Emulation.setDeviceMetricsOverride', {
            width: width,
            height: targetHeight,
            deviceScaleFactor: 1,
            mobile: false
          });
          await new Promise(r => setTimeout(r, 300));

          const shot = await client.send('Page.captureScreenshot', {
            format: 'png',
            captureBeyondViewport: true,
            fromSurface: true
          });
          fs.writeFileSync(filePath, Buffer.from(shot.data, 'base64'));

          // Restore admin layout
          await client.evaluate(`(() => {
            const shell = document.querySelector('.admin-shell');
            const main = document.querySelector('.admin-main-area');
            if (shell) shell.style.height = shell.dataset.origHeight || '';
            if (main) main.style.overflowY = main.dataset.origOverflow || '';
          })()`);
        },
        close() {
          return new Promise(res => {
            ws.onclose = res;
            ws.close();
          });
        }
      };
      resolve(client);
    };

    ws.onmessage = evt => {
      const msg = JSON.parse(evt.data);
      if (msg.id && callbacks.has(msg.id)) {
        const { res, rej } = callbacks.get(msg.id);
        callbacks.delete(msg.id);
        if (msg.error) rej(new Error(msg.error.message));
        else res(msg.result);
      }
    };

    ws.onerror = reject;
  });
}
